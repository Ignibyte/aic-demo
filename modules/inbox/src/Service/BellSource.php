<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use AIC\Tessera\Domain\TesseraEvents;
use InvalidArgumentException;
use Override;

use function array_slice;
use function count;
use function usleep;

/**
 * One connection of the bell's stream (#19), as Tessera events. It starts with a prepend of each notification after
 * the one the page last saw, its id the notification's, then the unread count with no id. It then looks once a
 * second: a new notification sends its prepend and the count, a count changed elsewhere (a read in another tab) the
 * count, and nothing new a keep-alive comment, so a client that has gone frees the worker at the next write. After
 * `LOOKS` looks it ends the connection with `reconnect()`, and Tessera comes back with the last id it saw (AIC #202).
 */
final class BellSource implements EventSource
{
    /**
     * How many looks one connection makes before it ends: about twenty seconds.
     */
    private const int LOOKS = 20;

    /**
     * The pause before each look, in microseconds.
     */
    private const int PAUSE = 1_000_000;

    /**
     * The events found but not sent yet, in order.
     *
     * @var list<ServerEvent>
     */
    private array $due = [];

    /**
     * Whether the connection's first events have been found.
     */
    private bool $started = false;

    /**
     * The looks made so far.
     */
    private int $looks = 0;

    /**
     * The unread count last sent; -1 before the first.
     */
    private int $unread = -1;

    /**
     * @param Inbox         $inbox         The members' notifications.
     * @param BellParts     $bellParts     The badge and items the events carry.
     * @param string        $userId        The member's user id.
     * @param int           $after         The newest notification the page has; the stream sends only later ones.
     * @param TesseraEvents $tesseraEvents Makes the events.
     */
    public function __construct(
        private readonly Inbox $inbox,
        private readonly BellParts $bellParts,
        private readonly string $userId,
        private int $after,
        private readonly TesseraEvents $tesseraEvents = new TesseraEvents(),
    ) {
    }

    /**
     * @throws QueryException           When the notifications cannot be read.
     * @throws InvalidArgumentException When a patch's target is malformed.
     *
     * @return ServerEvent The next event: one found already, else what the next look finds, else a keep-alive; the
     *                     reconnect comment once the connection has made its looks; for no member, a last comment
     *                     that ends the stream.
     */
    #[Override]
    public function next(): ServerEvent
    {
        if ($this->userId === '') {
            return $this->tesseraEvents->keepAlive(true);
        }

        if (!$this->started) {
            $this->started = true;
            $this->look();
        }

        return $this->due === []
            ? $this->lookAgain()
            : $this->nextDue();
    }

    /**
     * Waits a second and looks again, once the connection has looks left.
     *
     * @throws QueryException           When the notifications cannot be read.
     * @throws InvalidArgumentException When a patch's target is malformed.
     *
     * @return ServerEvent What the look found first, else a keep-alive; the reconnect comment when no look is left.
     */
    private function lookAgain(): ServerEvent
    {
        if ($this->looks >= self::LOOKS) {
            return $this->tesseraEvents->reconnect();
        }

        usleep(self::PAUSE);
        $this->looks += 1;
        $this->look();

        return $this->nextDue();
    }

    /**
     * @return ServerEvent The first event found and not sent yet, which it takes off the list; a keep-alive when none is.
     */
    private function nextDue(): ServerEvent
    {
        $event = $this->due[0] ?? $this->tesseraEvents->keepAlive(false);
        $this->due = array_slice($this->due, 1);

        return $event;
    }

    /**
     * Finds what changed since the last look: a prepend for each notification after `after` (the newest five, oldest
     * first), then the count when it is not the one last sent.
     *
     * @throws QueryException           When the notifications cannot be read.
     * @throws InvalidArgumentException When a patch's target is malformed.
     */
    private function look(): void
    {
        $newer = $this->inbox->newerThan($this->userId, $this->after);
        $shown = count($newer) > BellParts::SHOWN
            ? array_slice($newer, count($newer) - BellParts::SHOWN)
            : $newer;

        foreach ($shown as $inboxItem) {
            $id = (string) $inboxItem->id;
            $event = $this->tesseraEvents->patchElements(BellParts::ITEMS, $this->bellParts->item($inboxItem), 'prepend', $id, false);
            $this->due[] = $event;
        }

        foreach ($newer as $inboxItem) {
            $this->after = $inboxItem->id;
        }

        $unread = $this->inbox->unread($this->userId);

        if ($unread === $this->unread && $newer === []) {
            return;
        }

        $this->unread = $unread;
        $this->due[] = $this->tesseraEvents->patchElements(BellParts::COUNT, $this->bellParts->badge($unread), 'inner', '', false);
    }
}

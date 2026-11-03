<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\QueryException;
use AIC\Tessera\Domain\TesseraEvents;
use App\Leads\Entity\BoardViewer;
use App\Leads\Entity\LeadStage;
use App\Leads\Entity\StageMove;
use App\Leads\Form\BoardCards;
use App\Leads\Repository\LeadMoveRepository;
use App\Leads\Repository\LeadRepository;
use InvalidArgumentException;
use Override;

use function array_slice;
use function count;
use function in_array;
use function usleep;

/**
 * One connection of the leads board's stream (#30), as Tessera events. A fresh connection starts with every column, so
 * a move made between the page's render and the connection still reaches it; a resumed one starts with the columns the
 * moves after its `Last-Event-ID` touched. It then looks once a second: the moves after the last one it sent give one
 * patch per column they touched, in board order, and nothing new a keep-alive comment, so a client that has gone frees
 * the worker at the next write. Each patch is `inner`, the column's anchor its target and the column's stack its
 * component, since AIC renders a stream's component with no marks; the last patch of a look carries the newest move's
 * id. After `LOOKS` looks it ends the connection with `reconnect()`, and Tessera comes back with that id (AIC #202).
 */
final class BoardSource implements EventSource
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
     * The most moves one look reads; the next look reads on from the last.
     */
    private const int BATCH = 50;

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
     * @param LeadRepository     $leadRepository     The leads the columns show.
     * @param LeadMoveRepository $leadMoveRepository The moves.
     * @param BoardViewer        $boardViewer        Who looks, which decides the cards' menus.
     * @param string             $token              Their form token, which the menus' forms carry.
     * @param int                $after              The last move the page has; the stream sends only what came after.
     * @param bool               $fresh              Whether the connection starts with every column, for a page that
     *                                               named no move.
     * @param BoardCards         $boardCards         Builds a column's stack as the page builds it.
     * @param TesseraEvents      $tesseraEvents      Makes the events.
     */
    public function __construct(
        private readonly LeadRepository $leadRepository,
        private readonly LeadMoveRepository $leadMoveRepository,
        private readonly BoardViewer $boardViewer,
        private readonly string $token,
        private int $after,
        private readonly bool $fresh,
        private readonly BoardCards $boardCards = new BoardCards(),
        private readonly TesseraEvents $tesseraEvents = new TesseraEvents(),
    ) {
    }

    /**
     * @throws QueryException           When the leads or the moves cannot be read.
     * @throws InvalidArgumentException When a type, a key or a patch's target is malformed.
     *
     * @return ServerEvent The next event: one found already, else what the next look finds, else a keep-alive; the
     *                     reconnect comment once the connection has made its looks; for no member, a last comment that
     *                     ends the stream.
     */
    #[Override]
    public function next(): ServerEvent
    {
        if ($this->boardViewer->email === '') {
            return $this->tesseraEvents->keepAlive(true);
        }

        if ($this->due === [] && $this->looks >= self::LOOKS) {
            return $this->tesseraEvents->reconnect();
        }

        if ($this->due === []) {
            $this->find();
        }

        return $this->take();
    }

    /**
     * Finds what to send: on the connection's first call every column for a fresh one, else what came after its id; on
     * a later one, after a second's pause, what came after the last move sent.
     *
     * @throws QueryException           When the leads or the moves cannot be read.
     * @throws InvalidArgumentException When a type, a key or a patch's target is malformed.
     */
    private function find(): void
    {
        if (!$this->started && $this->fresh) {
            $this->started = true;
            $this->patch(LeadStage::New->board(), (string) $this->after);

            return;
        }

        if ($this->started) {
            usleep(self::PAUSE);
            $this->looks += 1;
        }

        $this->started = true;
        $this->look();
    }

    /**
     * @return ServerEvent The first event found and not sent yet, which it takes off the list; a keep-alive when none is.
     */
    private function take(): ServerEvent
    {
        if ($this->due === []) {
            return $this->tesseraEvents->keepAlive(false);
        }

        $event = $this->due[0];
        $this->due = array_slice($this->due, 1);

        return $event;
    }

    /**
     * Finds the moves after the last one sent, and a patch for each column they touched, in board order.
     *
     * @throws QueryException           When the leads or the moves cannot be read.
     * @throws InvalidArgumentException When a type, a key or a patch's target is malformed.
     */
    private function look(): void
    {
        $moves = $this->leadMoveRepository->after($this->after, self::BATCH);

        if ($moves === []) {
            return;
        }

        foreach ($moves as $move) {
            $this->after = $move->id;
        }

        $this->patch($this->touched($moves), (string) $this->after);
    }

    /**
     * @param list<StageMove> $moves Some moves.
     *
     * @return list<LeadStage> The stages they left or went to, each once, in board order.
     */
    private function touched(array $moves): array
    {
        $stages = [];

        foreach ($moves as $move) {
            $stages[] = $move->from;
            $stages[] = $move->toStage;
        }

        $touched = [];

        foreach (LeadStage::New->board() as $leadStage) {
            if (!in_array($leadStage, $stages, true)) {
                continue;
            }

            $touched[] = $leadStage;
        }

        return $touched;
    }

    /**
     * Adds one `inner` patch per column, the last carrying the id.
     *
     * @param list<LeadStage> $stages The columns, in board order.
     * @param string          $id     The newest move's id, which the last patch carries.
     *
     * @throws QueryException           When the leads cannot be read.
     * @throws InvalidArgumentException When a type, a key or a patch's target is malformed.
     */
    private function patch(array $stages, string $id): void
    {
        $last = count($stages) - 1;

        foreach ($stages as $index => $leadStage) {
            $boardStack = $this->boardCards->stack(
                $leadStage,
                $this->leadRepository->inStage($leadStage, BoardColumnSource::SHOWN),
                $this->leadRepository->countStage($leadStage),
                $this->boardViewer,
                $this->token,
                0,
            );
            $eventId = $index === $last
                ? $id
                : '';
            $this->due[] = $this->tesseraEvents->patchElements('@' . $leadStage->anchor(), $boardStack, 'inner', $eventId, false);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Inbox\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function array_key_exists;
use function ctype_digit;
use function is_string;
use function strlen;

/**
 * Where a connection of the bell's stream starts (#19): after the `Last-Event-ID` Tessera sends when it reconnects,
 * else after the `after` the page named, else after the member's newest notification, so a connection never sends a
 * notification the page has.
 */
final readonly class BellStreams
{
    /**
     * The longest id read: more digits than an int holds are no id.
     */
    private const int DIGITS = 18;

    /**
     * @param InboxOwners $inboxOwners Whose stream it is.
     * @param Inbox       $inbox       The members' notifications.
     * @param BellParts   $bellParts   The parts the events carry.
     */
    public function __construct(
        private InboxOwners $inboxOwners,
        private Inbox $inbox,
        private BellParts $bellParts,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The stream's request, with its session, `Last-Event-ID` and `after`.
     *
     * @throws QueryException           When the member or their notifications cannot be read.
     * @throws LogicException           When the request carries no session, or the users' type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return EventSource One connection's events.
     */
    public function connection(ServerRequestInterface $serverRequest): EventSource
    {
        $userId = $this->inboxOwners->userId($serverRequest);
        $start = self::digits($serverRequest->getHeaderLine('Last-Event-ID'));

        if ($start < 0) {
            $start = self::digits(self::after($serverRequest));
        }

        if ($start < 0) {
            $start = $this->newest($userId);
        }

        return new BellSource($this->inbox, $this->bellParts, $userId, $start);
    }

    /**
     * @param string $userId The member's user id; empty for none.
     *
     * @throws QueryException When their notifications cannot be read.
     *
     * @return int The id of their newest notification; 0 when they have none.
     */
    private function newest(string $userId): int
    {
        if ($userId === '') {
            return 0;
        }

        foreach ($this->inbox->page($userId, 0, 1) as $inboxItem) {
            return $inboxItem->id;
        }

        return 0;
    }

    /**
     * @param ServerRequestInterface $serverRequest The stream's request.
     *
     * @return string Its `after` query value; empty when it is missing or a list.
     */
    private static function after(ServerRequestInterface $serverRequest): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists('after', $query) && is_string($query['after'])
            ? $query['after']
            : '';
    }

    /**
     * @param string $text A header or query value.
     *
     * @return int It as a notification id; -1 when it is not one.
     */
    private static function digits(string $text): int
    {
        return $text !== '' && strlen($text) <= self::DIGITS && ctype_digit($text)
            ? (int) $text
            : -1;
    }
}

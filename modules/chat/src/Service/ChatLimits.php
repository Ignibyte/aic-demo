<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Database\Contracts\QueryException;
use App\Chat\Repository\ChatRepository;
use InvalidArgumentException;

/**
 * The chat's own rate limits, by the visitor's session (#32), since AIC has none: at most three chats started by one
 * visitor key in an hour, and at most ten visitor messages in one chat in a minute. Both count stored rows against the
 * database's clock through `chat_recent`; nothing else is kept.
 */
final readonly class ChatLimits
{
    /**
     * The chats one visitor key may start in an hour.
     */
    public const int STARTS = 3;

    /**
     * The messages a visitor may send in one chat in a minute.
     */
    public const int SENDS = 10;

    /**
     * @param ChatRepository $chatRepository What the limits count.
     */
    public function __construct(
        private ChatRepository $chatRepository,
    ) {
    }

    /**
     * @param string $key The visitor's key; empty before their first start.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return bool Whether they may start another chat.
     */
    public function mayStart(string $key): bool
    {
        return $key === '' || $this->chatRepository->startsLately($key) < self::STARTS;
    }

    /**
     * @param int $chatId The visitor's open chat.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return bool Whether they may send another message now.
     */
    public function maySend(int $chatId): bool
    {
        return $this->chatRepository->recentSends($chatId) < self::SENDS;
    }
}

<?php

declare(strict_types=1);

namespace App\Chat\Entity;

/**
 * What the session's `chat.visitor` envelope holds (#32): the visitor's open chat and their key. The key is made on
 * the session's first start and kept across chats, so the start limit holds for the session; a visitor with no open
 * chat has chat 0.
 */
final readonly class ChatVisitor
{
    /**
     * @param int    $chatId Their open chat's id; 0 for none.
     * @param string $key    Their key, 32 hex digits; empty before their first start.
     */
    public function __construct(
        public int $chatId,
        public string $key,
    ) {
    }
}

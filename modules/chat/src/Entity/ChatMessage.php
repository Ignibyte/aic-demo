<?php

declare(strict_types=1);

namespace App\Chat\Entity;

/**
 * A chat's message as a thread shows it (#32): its seq within the chat, its side, who wrote it, its text and the time
 * it was sent, to the minute.
 */
final readonly class ChatMessage
{
    /**
     * @param int      $seq      Its number within the chat, from 1.
     * @param ChatSide $chatSide Who wrote it.
     * @param string   $author   Their name: the visitor's, or the member's ("Staff" once that user is removed).
     * @param string   $body     Its text, line breaks kept.
     * @param string   $time     When it was sent, to the minute: `YYYY-MM-DD HH:MM UTC`.
     */
    public function __construct(
        public int $seq,
        public ChatSide $chatSide,
        public string $author,
        public string $body,
        public string $time,
    ) {
    }
}

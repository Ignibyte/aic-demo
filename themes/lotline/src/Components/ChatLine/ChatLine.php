<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatLine;

use AIC\Component\Contracts\Component;

/**
 * One message of a chat's thread (#32), from chat-line.phtml: who wrote it, its text with its line breaks, and the
 * time it was sent. The side reading it sees its own messages at the end of the line and the other side's at the
 * start. A stream appends one after the thread as it arrives.
 */
final readonly class ChatLine implements Component
{
    /**
     * @param string $author Who wrote it.
     * @param string $body   Its text; its line breaks show.
     * @param string $time   When it was sent, `YYYY-MM-DD HH:MM UTC`.
     * @param bool   $mine   Whether the side reading it wrote it.
     */
    public function __construct(
        public string $author,
        public string $body,
        public string $time,
        public bool $mine,
    ) {
    }
}

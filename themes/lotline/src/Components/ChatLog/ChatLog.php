<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatLog;

use AIC\Component\Contracts\Component;
use App\Theme\Components\ChatThread\ChatThread;

/**
 * A chat's log (#32), from chat-log.phtml: the scrolling `role="log"` box holding the thread, keyed `messages` and
 * anchored `chat-messages`. A stream's fresh connection replaces what it holds with the whole thread, and each new
 * message lands after it as an `append`.
 */
final readonly class ChatLog implements Component
{
    /**
     * @param ChatThread $chatThread The messages, oldest first.
     * @param string     $label      The log's accessible name.
     */
    public function __construct(
        public ChatThread $chatThread,
        public string $label,
    ) {
    }
}

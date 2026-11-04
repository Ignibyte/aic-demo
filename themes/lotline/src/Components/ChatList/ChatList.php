<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatList;

use AIC\Component\Contracts\Component;

/**
 * The staff's chat inbox (#32), from chat-list.phtml: a table of the chats the member sees, open ones first, each
 * linking to its conversation with its unread count. Keyed `chats` and anchored `chat-list`, it refreshes itself every
 * five seconds; its sealed version lets the page answer 204 while nothing changed.
 */
final readonly class ChatList implements Component
{
    /**
     * @param string        $poll The route the list refreshes from, `/app/chats`.
     * @param list<ChatRow> $rows The chats, open first, then by their newest message.
     */
    public function __construct(
        public string $poll,
        public array $rows,
    ) {
    }
}

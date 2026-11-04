<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatDock;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;

/**
 * The visitor's open chat (#32), from chat-dock.phtml: a `<details id="chat-dock" data-permanent>` at the bottom left
 * of every storefront page, keyed and anchored `chat`, so a Tessera visit keeps it as the same node, its stream
 * connection, its open state and its box with it. It opens the visitor's stream and holds the `chatUnread` and
 * `chatTyping` signals the stream patches; its summary names the car and shows the unread count; opening it, and a
 * message landing while it is open, post the read. Inside: the log, the typing line, the send form and "End chat".
 */
final readonly class ChatDock implements Component
{
    /**
     * @param string         $carTitle    The car the chat is about.
     * @param string         $carPath     The car's page.
     * @param int            $unread      The staff's messages the visitor has not seen.
     * @param bool           $open        Whether it renders open (on `/chat`).
     * @param string         $typingName  Who the typing line names.
     * @param KeyedComponent $log         The `ChatLog`, keyed `messages` and anchored `chat-messages`.
     * @param KeyedComponent $send        The `ChatSendForm`, keyed `send` and anchored `chat-send`.
     * @param string         $back        The page "End chat" comes back to.
     * @param GuardFields    $guardFields The form token and the honeypot, for "End chat".
     */
    public function __construct(
        public string $carTitle,
        public string $carPath,
        public int $unread,
        public bool $open,
        public string $typingName,
        public KeyedComponent $log,
        public KeyedComponent $send,
        public string $back,
        public GuardFields $guardFields,
    ) {
    }
}

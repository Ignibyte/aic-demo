<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatConversation;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;

/**
 * A chat as the member sees it (#32), from chat-conversation.phtml, anchored `chat-conversation`: the car with a link
 * to its page, the visitor and their email, who it went to, then the log, the typing line and the send form, and
 * "Close chat" while it is open. It opens the member's stream and holds the `chatTyping` signal the stream patches.
 */
final readonly class ChatConversation implements Component
{
    /**
     * @param string         $stream       The member's stream for this chat, `/app/chats/<id>/events`.
     * @param string         $carTitle     The car it is about.
     * @param string         $carPath      The car's storefront page.
     * @param string         $visitorName  The visitor's name.
     * @param string         $visitorEmail Their email, or "not given".
     * @param string         $staffName    Who it went to, or "Unassigned".
     * @param bool           $open         Whether it is open.
     * @param KeyedComponent $log          The `ChatLog`, keyed `messages` and anchored `chat-messages`.
     * @param KeyedComponent $send         The `ChatSendForm`, keyed `send` and anchored `chat-send`.
     * @param string         $close        Where "Close chat" posts, `/app/chats/<id>/close`.
     * @param GuardFields    $guardFields  The form token and the honeypot, for "Close chat".
     */
    public function __construct(
        public string $stream,
        public string $carTitle,
        public string $carPath,
        public string $visitorName,
        public string $visitorEmail,
        public string $staffName,
        public bool $open,
        public KeyedComponent $log,
        public KeyedComponent $send,
        public string $close,
        public GuardFields $guardFields,
    ) {
    }
}

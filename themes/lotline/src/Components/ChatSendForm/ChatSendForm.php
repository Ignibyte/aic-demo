<?php

declare(strict_types=1);

namespace App\Theme\Components\ChatSendForm;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\GuardFields;

/**
 * A chat's send form (#32), from chat-send-form.phtml, for either side, keyed `send` and anchored `chat-send`. Its box
 * keeps what is typed through every morph and stream patch (`data-ignore-morph`); Enter sends, Shift+Enter breaks the
 * line; typing posts the side's typing route at most every two seconds; and the box clears when an answer patches the
 * `chatSent` signal, which the form declares in its region's scope, with the seq it took. With no script it is a plain
 * form that posts and reloads. A chat that has ended shows that it has in place of the form's fields. The staff side's
 * box takes focus (`autofocus`, #38): staff open a chat to answer it.
 */
final readonly class ChatSendForm implements Component
{
    /**
     * @param string      $id          The form's id, which its box's and reason's ids start with.
     * @param string      $action      Where a message posts.
     * @param string      $back        The page a plain post comes back to; empty for none.
     * @param string      $typing      The side's typing route.
     * @param string      $label       The box's accessible name.
     * @param string      $message     What the box holds: a refused message as sent; empty otherwise.
     * @param string      $reason      Why the last message was refused; empty for none.
     * @param bool        $ended       Whether the chat has ended, so nothing more is sent.
     * @param GuardFields $guardFields The form token and the honeypot, which the form renders first.
     * @param bool        $autofocus   Whether the box takes focus when the page loads or an answer redraws it.
     */
    public function __construct(
        public string $id,
        public string $action,
        public string $back,
        public string $typing,
        public string $label,
        public string $message,
        public string $reason,
        public bool $ended,
        public GuardFields $guardFields,
        public bool $autofocus = false,
    ) {
    }
}

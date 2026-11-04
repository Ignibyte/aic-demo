<?php

declare(strict_types=1);

namespace App\Chat\Form;

use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;
use AIC\Form\Application\FormTokens;
use App\Chat\Entity\ChatReply;
use App\Theme\Components\ChatSendForm\ChatSendForm;
use App\Theme\Components\ChatStart\ChatStart;
use App\Theme\Components\FormField\FormField;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The chat's forms (#32): the start form a car's page offers, and the send form either side writes in, each holding
 * AIC's guard fields (#246), so every send passes the form guard. A refused form comes back with its reason and, for
 * a page with no script, the text as sent.
 */
final readonly class ChatForms
{
    /**
     * @param FormTokens $formTokens Issues the session's form token.
     */
    public function __construct(
        private FormTokens $formTokens,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return GuardFields The form token and the honeypot.
     */
    public function guard(ServerRequestInterface $serverRequest): GuardFields
    {
        return $this->formTokens->guard($serverRequest);
    }

    /**
     * @param ServerRequestInterface $serverRequest The car page's request, with its session.
     * @param string                 $action        Where the start posts, `/cars/<slug>/chat`.
     * @param ChatReply|null         $chatReply     A refused start; null for a blank form.
     *
     * @throws LogicException When the request carries no session, or a key is malformed.
     *
     * @return KeyedComponent The start, keyed and anchored `chat`.
     */
    public function start(ServerRequestInterface $serverRequest, string $action, ?ChatReply $chatReply): KeyedComponent
    {
        $sent = $chatReply ?? new ChatReply(200, '', '', '', '', '', 0);
        $fields = [
            self::field('name', 'Your name (optional)', 'text', $sent->name, $sent, false),
            self::field('email', 'Your email (optional)', 'email', $sent->email, $sent, false),
            self::field('message', 'Your message', 'textarea', $sent->message, $sent, true),
        ];
        $chatStart = new ChatStart($action, $fields, $sent->reason, $this->guard($serverRequest));

        return new KeyedComponent('chat', $chatStart, '', 'chat');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     * @param string                 $action        Where a message posts.
     * @param string                 $back          The page a plain post comes back to; empty for none.
     * @param string                 $typing        The side's typing route.
     * @param string                 $label         The box's accessible name.
     * @param ChatReply|null         $chatReply     A refused message; null for an empty box.
     * @param bool                   $ended         Whether the chat has ended.
     *
     * @throws LogicException When the request carries no session, or a key is malformed.
     *
     * @return KeyedComponent The send form, keyed `send` and anchored `chat-send`.
     */
    public function send(
        ServerRequestInterface $serverRequest,
        string $action,
        string $back,
        string $typing,
        string $label,
        ?ChatReply $chatReply,
        bool $ended,
    ): KeyedComponent {
        $message = $chatReply instanceof ChatReply
            ? $chatReply->message
            : '';
        $reason = $chatReply instanceof ChatReply
            ? $chatReply->reason
            : '';
        $guardFields = $this->guard($serverRequest);
        $chatSendForm = new ChatSendForm('chat-send-form', $action, $back, $typing, $label, $message, $reason, $ended, $guardFields);

        return new KeyedComponent('send', $chatSendForm, '', 'chat-send');
    }

    /**
     * @param string    $name      The field's name, which its id ends with.
     * @param string    $label     Its label.
     * @param string    $type      Its input type, or `textarea`.
     * @param string    $value     What it holds.
     * @param ChatReply $chatReply The form's outcome, whose reason may be about this field.
     * @param bool      $required  Whether it must be filled.
     *
     * @throws LogicException When the key is malformed.
     *
     * @return KeyedComponent The field, keyed by its name.
     */
    private static function field(
        string $name,
        string $label,
        string $type,
        string $value,
        ChatReply $chatReply,
        bool $required,
    ): KeyedComponent {
        $error = $chatReply->field === $name
            ? $chatReply->reason
            : '';

        return new KeyedComponent($name, new FormField('chat-' . $name, $name, $label, $type, $value, $error, $required, false));
    }
}

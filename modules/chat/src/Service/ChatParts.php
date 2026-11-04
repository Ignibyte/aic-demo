<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatMessage;
use App\Chat\Entity\ChatReply;
use App\Chat\Entity\ChatSide;
use App\Chat\Form\ChatForms;
use App\Chat\Repository\ChatMessageRepository;
use App\Theme\Components\ChatDock\ChatDock;
use App\Theme\Components\ChatLine\ChatLine;
use App\Theme\Components\ChatLog\ChatLog;
use App\Theme\Components\ChatThread\ChatThread;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The chat as components (#32), the same for a page and a stream: a message's line, the whole thread in its log, and
 * the visitor's dock. Each side sees its own messages at the end of
 * the line; the visitor's own are signed "You".
 */
final readonly class ChatParts
{
    /**
     * Who the visitor's typing line names: the staff's side, whoever of them is writing.
     */
    private const string TEAM = 'Our team';

    /**
     * @param ChatMessageRepository $chatMessageRepository The messages.
     * @param ChatForms             $chatForms             The send form and the guard fields.
     */
    public function __construct(
        private ChatMessageRepository $chatMessageRepository,
        private ChatForms $chatForms,
    ) {
    }

    /**
     * @param ChatMessage $chatMessage A message.
     * @param ChatSide    $chatSide    The side reading it.
     *
     * @return ChatLine Its line.
     */
    public function line(ChatMessage $chatMessage, ChatSide $chatSide): ChatLine
    {
        $mine = $chatMessage->chatSide === $chatSide;
        $author = $mine && $chatSide === ChatSide::Visitor
            ? 'You'
            : $chatMessage->author;

        return new ChatLine($author, $chatMessage->body, $chatMessage->time, $mine);
    }

    /**
     * @param list<ChatMessage> $messages The messages, oldest first.
     * @param ChatSide          $chatSide The side reading them.
     *
     * @throws LogicException When a key is malformed.
     *
     * @return ChatThread The thread, each line keyed by its seq.
     */
    public function thread(array $messages, ChatSide $chatSide): ChatThread
    {
        $lines = [];

        foreach ($messages as $message) {
            $lines[] = new KeyedComponent('line-' . $message->seq, $this->line($message, $chatSide));
        }

        return new ChatThread($lines);
    }

    /**
     * @param Chat     $chat     A chat.
     * @param ChatSide $chatSide The side reading it.
     *
     * @throws QueryException           When the messages cannot be read.
     * @throws LogicException           When a key is malformed, or a type is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     *
     * @return KeyedComponent The log holding the whole thread, keyed `messages` and anchored `chat-messages`.
     */
    public function log(Chat $chat, ChatSide $chatSide): KeyedComponent
    {
        $thread = $this->thread($this->chatMessageRepository->thread($chat->id, $chat->visitorName), $chatSide);

        return new KeyedComponent('messages', new ChatLog($thread, 'Messages about the ' . $chat->carTitle), '', 'chat-messages');
    }

    /**
     * @param ServerRequestInterface $serverRequest The store page's request, with its session.
     * @param Chat                   $chat          The visitor's open chat.
     * @param int                    $unread        The staff's messages the visitor has not seen.
     * @param ChatReply|null         $chatReply     A refused message; null for none.
     * @param bool                   $open          Whether the dock renders open.
     * @param string                 $back          The page a plain post comes back to.
     *
     * @throws QueryException           When the messages cannot be read.
     * @throws LogicException           When the request carries no session, or a key is malformed, or a type is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     *
     * @return KeyedComponent The dock, keyed and anchored `chat`.
     */
    public function dock(
        ServerRequestInterface $serverRequest,
        Chat $chat,
        int $unread,
        ?ChatReply $chatReply,
        bool $open,
        string $back,
    ): KeyedComponent {
        $label = 'Message about the ' . $chat->carTitle;
        $send = $this->chatForms->send($serverRequest, '/chat/messages', $back, '/chat/typing', $label, $chatReply, false);
        $chatDock = new ChatDock(
            $chat->carTitle,
            $chat->carPath,
            $unread,
            $open || $chatReply instanceof ChatReply,
            self::TEAM,
            $this->log($chat, ChatSide::Visitor),
            $send,
            $back,
            $this->chatForms->guard($serverRequest),
        );

        return new KeyedComponent('chat', $chatDock, '', 'chat');
    }
}

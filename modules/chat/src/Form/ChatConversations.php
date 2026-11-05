<?php

declare(strict_types=1);

namespace App\Chat\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatReply;
use App\Chat\Entity\ChatSide;
use App\Chat\Service\ChatParts;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\ChatConversation\ChatConversation;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The member's chat pages (#32): a chat's conversation in its panel (the car, the visitor and their email, who it went
 * to, the log, the send form and "Close chat" while it is open), and the 404 for a chat that is not there and the 403
 * for one that went to another salesperson.
 */
final readonly class ChatConversations
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     * @param ChatParts  $chatParts  The log.
     * @param ChatForms  $chatForms  The send form and the guard fields.
     */
    public function __construct(
        private StaffPages $staffPages,
        private ChatParts $chatParts,
        private ChatForms $chatForms,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Chat                   $chat          The chat.
     * @param ChatReply|null         $chatReply     A refused reply; null for none.
     * @param int                    $status        200, 409 or 422.
     *
     * @throws QueryException           When the messages cannot be read.
     * @throws LogicException           When the request carries no session, or a type, a key or the status does not
     *                                  fit.
     * @throws UnexpectedValueException When a stored side is no side.
     *
     * @return PageDocument The conversation's page.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        Chat $chat,
        ?ChatReply $chatReply,
        int $status,
    ): PageDocument {
        $path = $chat->path();
        $label = 'Reply to ' . $chat->visitorName;
        $send = $this->chatForms->send($serverRequest, $path . '/messages', '', $path . '/typing', $label, $chatReply, !$chat->open, true);
        $chatConversation = new ChatConversation(
            $path . '/events',
            $chat->carTitle,
            $chat->carPath,
            $chat->visitorName,
            $chat->visitorEmail === '' ? 'not given' : $chat->visitorEmail,
            $chat->staffName === '' ? 'Unassigned' : $chat->staffName,
            $chat->open,
            $this->chatParts->log($chat, ChatSide::Staff),
            $send,
            $path . '/close',
            $this->chatForms->guard($serverRequest),
        );
        $keyedComponent = new KeyedComponent('conversation', $chatConversation, '', 'chat-conversation');
        $panel = new Panel('chat', 'Chat with ' . $chat->visitorName, 'About the ' . $chat->carTitle, true, [$keyedComponent]);

        return $this->staffPages->page('Chat with ' . $chat->visitorName, [new KeyedComponent('chat-panel', $panel)], $status);
    }

    /**
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageDocument The 404 for a chat that is not there.
     */
    public function missing(): PageDocument
    {
        $message = 'There is no chat at this address. It may have gone with its car.';

        return $this->panels()->warning('Chat not found', $message, 'chat-missing', 'Back to the chats', '/app/chats', 404);
    }

    /**
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageDocument The 403 for a chat that went to another salesperson.
     */
    public function refused(): PageDocument
    {
        $message = 'This chat went to another salesperson, so only they and the managers can read it.';

        return $this->panels()->warning('Not your chat', $message, 'chat-refused', 'Back to the chats', '/app/chats', 403);
    }

    /**
     * @return StaffPanels The staff's panel pages, for the 403 and the 404.
     */
    private function panels(): StaffPanels
    {
        return new StaffPanels($this->staffPages, 'chat');
    }
}

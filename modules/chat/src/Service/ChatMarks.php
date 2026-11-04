<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Database\Contracts\QueryException;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatSide;
use App\Chat\Repository\ChatRepository;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The chat's marks (#32): the member's typing, for a chat they may see that is open; and the visitor's typing, which
 * the member's stream shows for five seconds, and read, which takes the dock's unread count to 0, each for the open
 * chat the session's envelope names. A mark with no chat to go on writes nothing.
 */
final readonly class ChatMarks
{
    /**
     * @param ChatVisitors   $chatVisitors   The visitor's envelope.
     * @param ChatRepository $chatRepository The chats.
     * @param ChatAccess     $chatAccess     The member and the chat their route names.
     */
    public function __construct(
        private ChatVisitors $chatVisitors,
        private ChatRepository $chatRepository,
        private ChatAccess $chatAccess,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The member's typing post, routed with the chat's `id`.
     *
     * @throws QueryException           When the chat cannot be read or written.
     * @throws LogicException           When the request carries no session, or a type is malformed.
     * @throws UnexpectedValueException When the kept role is no role.
     */
    public function staffTyping(ServerRequestInterface $serverRequest): void
    {
        $chat = $this->chatAccess->routed($serverRequest);

        if (!$chat instanceof Chat || !$chat->open || !$this->chatAccess->viewer($serverRequest)->mayRead($chat)) {
            return;
        }

        $this->chatRepository->typing($chat->id, ChatSide::Staff);
    }

    /**
     * @param ServerRequestInterface $serverRequest The visitor's typing post, with its session.
     *
     * @throws QueryException When the chat cannot be read or written.
     * @throws LogicException When the request carries no session, or a type is malformed.
     */
    public function typing(ServerRequestInterface $serverRequest): void
    {
        $chat = $this->openChat($serverRequest);

        if (!$chat instanceof Chat) {
            return;
        }

        $this->chatRepository->typing($chat->id, ChatSide::Visitor);
    }

    /**
     * @param ServerRequestInterface $serverRequest The visitor's read post, with its session.
     *
     * @throws QueryException When the chat cannot be read or written.
     * @throws LogicException When the request carries no session, or a type is malformed.
     */
    public function read(ServerRequestInterface $serverRequest): void
    {
        $chat = $this->openChat($serverRequest);

        if (!$chat instanceof Chat) {
            return;
        }

        $this->chatRepository->see($chat->id, ChatSide::Visitor, $chat->lastSeq);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws QueryException When the chats cannot be read.
     * @throws LogicException When the request carries no session, or a type is malformed.
     *
     * @return Chat|null The open chat the session's envelope names, when its key is the visitor's; null otherwise.
     */
    private function openChat(ServerRequestInterface $serverRequest): ?Chat
    {
        $chatVisitor = $this->chatVisitors->visitor($serverRequest);

        return $this->chatRepository->visitorsOpen($chatVisitor->chatId, $chatVisitor->key);
    }
}

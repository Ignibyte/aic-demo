<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Database\Contracts\QueryException;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatSide;
use App\Chat\Form\ChatConversations;
use App\Chat\Form\ChatLists;
use App\Chat\Repository\ChatRepository;
use App\Chat\Repository\ChatStaffRepository;
use App\Staff\Form\StaffSeals;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The staff's side of the chat (#32): the list of the chats the member sees, which refreshes itself and answers 204
 * while its sealed version holds; a chat's conversation, which marks it seen; and the check every chat route makes
 * first: a chat the route does not name answers 404, and one that went to another salesperson 403.
 */
final readonly class ChatInbox
{
    /**
     * How many chats the list shows.
     */
    private const int SHOWN = 50;

    /**
     * @param ChatAccess          $chatAccess          The member and the chat their route names.
     * @param ChatRepository      $chatRepository      The seen mark.
     * @param ChatStaffRepository $chatStaffRepository The list and its version.
     * @param ChatLists           $chatLists           The list's page.
     * @param ChatConversations   $chatConversations   The conversation's page, the 404 and the 403.
     * @param StaffSeals          $staffSeals          Seals the list's version, and reads it back.
     */
    public function __construct(
        private ChatAccess $chatAccess,
        private ChatRepository $chatRepository,
        private ChatStaffRepository $chatStaffRepository,
        private ChatLists $chatLists,
        private ChatConversations $chatConversations,
        private StaffSeals $staffSeals,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, from a signed-in member, maybe its poll.
     *
     * @throws QueryException           When the chats cannot be read.
     * @throws LogicException           When the request carries no session, the version cannot be sealed, or a type, a
     *                                  key or the status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The list; 204 for its poll while nothing changed.
     */
    public function list(ServerRequestInterface $serverRequest): PageResponse
    {
        $summaries = $this->chatStaffRepository->listed($this->chatAccess->viewer($serverRequest), self::SHOWN);
        $version = $this->chatStaffRepository->version($summaries);

        if ($this->staffSeals->unchanged($serverRequest, 'chat-list', $version)) {
            return new PageUnchanged();
        }

        return $this->chatLists->page($serverRequest, $summaries, $this->staffSeals->version($version));
    }

    /**
     * @param ServerRequestInterface $serverRequest The conversation's request, routed with the chat's `id`.
     *
     * @throws QueryException           When the chat or its messages cannot be read, or its seen mark written.
     * @throws LogicException           When the request carries no session, or a type, a key or the status does not
     *                                  fit.
     * @throws UnexpectedValueException When the kept role is no role, or a stored side is no side.
     *
     * @return PageResponse The conversation; 404 or 403.
     */
    public function view(ServerRequestInterface $serverRequest): PageResponse
    {
        $chat = $this->readable($serverRequest);

        if ($chat instanceof PageDocument) {
            return $chat;
        }

        $this->chatRepository->see($chat->id, ChatSide::Staff, $chat->lastSeq);

        return $this->chatConversations->page($serverRequest, $chat, null, 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request routed with the chat's `id`.
     *
     * @throws QueryException           When the chat cannot be read.
     * @throws LogicException           When the request carries no session, or a type or the status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return Chat|PageDocument The chat the route names, as stored now, when the member may see it; else the 404 for
     *                           no chat, or the 403 for one that went to another salesperson.
     */
    public function readable(ServerRequestInterface $serverRequest): Chat|PageDocument
    {
        $chat = $this->chatAccess->routed($serverRequest);

        if (!$chat instanceof Chat) {
            return $this->chatConversations->missing();
        }

        return $this->chatAccess->viewer($serverRequest)->mayRead($chat)
            ? $chat
            : $this->chatConversations->refused();
    }
}

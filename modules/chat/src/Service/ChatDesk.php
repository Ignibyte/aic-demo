<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Chat\Entity\Chat;
use App\Chat\Entity\ChatReply;
use App\Chat\Form\ChatForms;
use App\Chat\Repository\ChatRepository;
use App\Inventory\Entity\ListedVehicle;
use App\Theme\Components\StoreHeading\StoreHeading;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use UnexpectedValueException;

/**
 * The visitor's side of the chat (#32), exported for the storefront's pages as leads' `LeadDesk` is: the dock every
 * store page places (the open chat, else the start form on a car's page, else nothing), and the start, the send and
 * the end, each for the chat the session's envelope names. A plain post comes back to the store
 * page it was sent from, when `back` names one.
 */
final readonly class ChatDesk
{
    /**
     * The page a plain post comes back to when `back` names no store page.
     */
    public const string CHAT_PAGE = '/chat';

    /**
     * @param ChatVisitors   $chatVisitors   The visitor's envelope.
     * @param ChatRepository $chatRepository The chats.
     * @param ChatParts      $chatParts      The dock.
     * @param ChatSends      $chatSends      The writes.
     * @param ChatForms      $chatForms      The start form.
     */
    public function __construct(
        private ChatVisitors $chatVisitors,
        private ChatRepository $chatRepository,
        private ChatParts $chatParts,
        private ChatSends $chatSends,
        private ChatForms $chatForms,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The store page's request, with its session.
     * @param string                 $back          The page a plain post from the dock comes back to.
     * @param string                 $startAction   Where a start posts on a car's page; empty on any other page.
     * @param ChatReply|null         $chatReply     A refused start or send to show; null for none.
     * @param bool                   $open          Whether an open chat's dock renders open.
     *
     * @throws QueryException           When the chat or its messages cannot be read.
     * @throws LogicException           When the request carries no session, or a key is malformed, or a type is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     *
     * @return KeyedComponent|null The dock, keyed and anchored `chat`; null for a page with none.
     */
    public function dock(
        ServerRequestInterface $serverRequest,
        string $back,
        string $startAction,
        ?ChatReply $chatReply,
        bool $open,
    ): ?KeyedComponent {
        $chat = $this->openChat($serverRequest);
        $refused = $chatReply instanceof ChatReply && !$chatReply->isTaken()
            ? $chatReply
            : null;

        if ($chat instanceof Chat) {
            return $this->chatParts->dock($serverRequest, $chat, $this->chatRepository->visitorUnread($chat), $refused, $open, $back);
        }

        return $startAction === ''
            ? null
            : $this->chatForms->start($serverRequest, $startAction, $refused);
    }

    /**
     * @param ServerRequestInterface $serverRequest A store page's request, with its session, which a plain post from
     *                                              the dock comes back to.
     *
     * @throws QueryException           When the chat or its messages cannot be read.
     * @throws LogicException           When the request carries no session, or a type or a key is malformed.
     * @throws UnexpectedValueException When a stored side is no side.
     *
     * @return KeyedComponent|null The dock for a store page that is not a car's: the visitor's open chat, else none.
     */
    public function pageDock(ServerRequestInterface $serverRequest): ?KeyedComponent
    {
        $uri = $serverRequest->getUri();
        $back = $uri->getPath() . ($uri->getQuery() === '' ? '' : '?' . $uri->getQuery());

        return $this->dock($serverRequest, $back, '', null, false);
    }

    /**
     * @param ServerRequestInterface $serverRequest The `/chat` page's request, with its session.
     *
     * @throws Throwable When the chat cannot be read or its seen mark written, the request carries no session, or the
     *                   key is malformed.
     *
     * @return KeyedComponent The page's heading: the open chat's car with a link to it, else that none is open; the
     *                        open chat is marked read.
     */
    public function heading(ServerRequestInterface $serverRequest): KeyedComponent
    {
        $chat = $this->openChat($serverRequest);

        if (!$chat instanceof Chat) {
            $lead = 'You have no chat open. Start one from the page of any car for sale.';
            $storeHeading = new StoreHeading('chat-heading', 'No chat open', $lead, '/cars', 'See every car for sale');

            return new KeyedComponent('heading', $storeHeading);
        }

        $this->chatSends->read($chat);
        $lead = 'Your messages with our team are below. Leave the page open, or come back to it while you look around.';
        $storeHeading = new StoreHeading('chat-heading', 'Your chat about the ' . $chat->carTitle, $lead, $chat->carPath, 'See the car');

        return new KeyedComponent('heading', $storeHeading);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted start form, with its session.
     * @param ListedVehicle          $listedVehicle The car for sale it was sent from.
     * @param string                 $carPath       The car's page.
     *
     * @throws Throwable When the chats cannot be read or written, the notification cannot be queued, or the request
     *                   has no session.
     *
     * @return ChatReply Taken (200) or refused (422).
     */
    public function start(
        ServerRequestInterface $serverRequest,
        ListedVehicle $listedVehicle,
        string $carPath,
    ): ChatReply {
        return $this->chatSends->start($serverRequest, $listedVehicle, $carPath);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted send form, with its session.
     *
     * @throws Throwable When the chat or its messages cannot be read or written, or the request has no session.
     *
     * @return ChatReply Taken (200, its seq), refused (422), or ended (409) when no chat is open.
     */
    public function send(ServerRequestInterface $serverRequest): ChatReply
    {
        $chat = $this->openChat($serverRequest);

        return $chat instanceof Chat
            ? $this->chatSends->fromVisitor($serverRequest, $chat)
            : new ChatReply(409, '', '', '', ChatReply::ENDED, '', 0);
    }

    /**
     * Ends the visitor's open chat, if any, and forgets it in the session, keeping the visitor's key.
     *
     * @param ServerRequestInterface $serverRequest The posted end form, with its session.
     *
     * @throws Throwable When the chat cannot be read or written, the job queued, or the request has no session.
     */
    public function end(ServerRequestInterface $serverRequest): void
    {
        $chatVisitor = $this->chatVisitors->visitor($serverRequest);
        $chat = $this->chatRepository->visitorsOpen($chatVisitor->chatId, $chatVisitor->key);

        if (!$chat instanceof Chat) {
            return;
        }

        $this->chatSends->end($chat);
        $this->chatVisitors->keep($serverRequest, 0, $chatVisitor->key);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted chat form.
     *
     * @return string Its `back` when it is a storefront page (`/cars`, below it, or `/chat`) on this site; else `/chat`.
     */
    public function back(ServerRequestInterface $serverRequest): string
    {
        return $this->chatSends->back($serverRequest, self::CHAT_PAGE);
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

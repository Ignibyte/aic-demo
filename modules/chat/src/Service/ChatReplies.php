<?php

declare(strict_types=1);

namespace App\Chat\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\SignalPatch;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageRedirect;
use AIC\Tessera\Domain\TesseraRequest;
use App\Chat\Entity\ChatReply;
use App\Chat\Entity\ChatSide;
use App\Chat\Form\ChatConversations;
use App\Staff\Service\StaffNotices;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The member's writes in a chat (#32): a reply, taken (303 to the chat, or the conversation's fragments with the
 * `chatSent` patch for Tessera, scoped to `@chat-send`, which AIC writes as the send form's path, AIC #260), refused
 * (422) or too late (409); and the close, which queues the transcript and answers 303 to the list with its notice.
 * Each answers 404 or 403 first, as `ChatInbox` checks.
 */
final readonly class ChatReplies
{
    /**
     * @param ChatInbox         $chatInbox         The chat the route names, when the member may see it.
     * @param ChatConversations $chatConversations The conversation's page.
     * @param ChatSends         $chatSends         The reply and the end.
     * @param ChatAccess        $chatAccess        The member who writes.
     * @param StaffNotices      $staffNotices      The close's notice.
     */
    public function __construct(
        private ChatInbox $chatInbox,
        private ChatConversations $chatConversations,
        private ChatSends $chatSends,
        private ChatAccess $chatAccess,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted reply, routed with the chat's `id`.
     *
     * @throws Throwable When the chat or its messages cannot be read or written, or a key or a status does not fit.
     *
     * @return PageResponse 303 to the chat, or its fragments for Tessera; 422, 409, 404 or 403.
     */
    public function reply(ServerRequestInterface $serverRequest): PageResponse
    {
        $chat = $this->chatInbox->readable($serverRequest);

        if ($chat instanceof PageDocument) {
            return $chat;
        }

        $chatReply = $chat->open
            ? $this->chatSends->send($serverRequest, $chat, ChatSide::Staff, $this->chatAccess->viewer($serverRequest)->userId)
            : new ChatReply(409, '', '', '', ChatReply::ENDED, '', 0);

        if ($chatReply->isTaken() && $serverRequest->getHeaderLine(TesseraRequest::REQUEST) === '') {
            return new PageRedirect($chat->path());
        }

        return $this->answer($serverRequest, $chatReply);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted close, routed with the chat's `id`.
     *
     * @throws Throwable When the chat cannot be read or written, the job queued, or a key or a status does not fit.
     *
     * @return PageResponse 303 to the list with its notice; 409, 404 or 403.
     */
    public function close(ServerRequestInterface $serverRequest): PageResponse
    {
        $chat = $this->chatInbox->readable($serverRequest);

        if ($chat instanceof PageDocument) {
            return $chat;
        }

        if (!$this->chatSends->end($chat)) {
            return $this->chatConversations->page($serverRequest, $chat, new ChatReply(409, '', '', '', ChatReply::ENDED, '', 0), 409);
        }

        $this->staffNotices->keep($serverRequest, 'Closed the chat with ' . $chat->visitorName . '.');

        return new PageRedirect('/app/chats');
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted reply, routed with the chat's `id`.
     * @param ChatReply              $chatReply     What became of it: refused, too late, or taken from Tessera.
     *
     * @throws Throwable When the chat or its messages cannot be read, or a key or a status does not fit.
     *
     * @return PageResponse The conversation as it is now: refused (422) or too late (409) with the reason, or taken
     *                      with the `chatSent` patch; 404 or 403 when the chat went meanwhile.
     */
    private function answer(ServerRequestInterface $serverRequest, ChatReply $chatReply): PageResponse
    {
        $chat = $this->chatInbox->readable($serverRequest);

        if ($chat instanceof PageDocument) {
            return $chat;
        }

        if (!$chatReply->isTaken()) {
            return $this->chatConversations->page($serverRequest, $chat, $chatReply, $chatReply->status);
        }

        $pageDocument = $this->chatConversations->page($serverRequest, $chat, null, 200);

        return $pageDocument->withSignalPatch(new SignalPatch('@chat-send', '{"chatSent": ' . $chatReply->sent . '}', false));
    }
}

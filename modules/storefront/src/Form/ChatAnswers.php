<?php

declare(strict_types=1);

namespace App\Storefront\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\SignalPatch;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageRedirect;
use AIC\Tessera\Domain\TesseraRequest;
use App\Chat\Entity\ChatReply;
use App\Chat\Service\ChatDesk;
use App\Storefront\Service\StorePages;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The visitor's chat answers in the store's frame (#32): the `/chat` page, the dock open on it; a message sent from
 * the dock, 303 back to the store page it came from for a plain post, the `/chat` page with the `chatSent` patch for
 * Tessera (scoped to `@chat-send`, which AIC writes as the send form's path, AIC #260), which takes the send form's
 * fragment from it, or that page refused (422) or with the chat ended (409); and "End chat", 303 back.
 * `storefront/src/Service/` is at its cap, so these sit here.
 */
final readonly class ChatAnswers
{
    /**
     * The `/chat` page's title.
     */
    private const string TITLE = 'Your chat';

    /**
     * @param StorePages $storePages The store's frame.
     * @param ChatDesk   $chatDesk   The visitor's chat.
     */
    public function __construct(
        private StorePages $storePages,
        private ChatDesk $chatDesk,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     *
     * @throws Throwable When the chat, its messages or the profile cannot be read, the request has no session, or a key
     *                   or a region does not fit the theme.
     *
     * @return PageDocument The `/chat` page: a heading naming the car, and the dock open; or that no chat is open.
     */
    public function page(ServerRequestInterface $serverRequest): PageDocument
    {
        return $this->chatPage($serverRequest, null, 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted send form, with its session.
     *
     * @throws Throwable When the chat or its messages cannot be read or written, the request has no session, or a key
     *                   or a region does not fit the theme.
     *
     * @return PageResponse 303 back, or the `/chat` page for Tessera with the `chatSent` patch; 422 or 409.
     */
    public function send(ServerRequestInterface $serverRequest): PageResponse
    {
        $chatReply = $this->chatDesk->send($serverRequest);

        if (!$chatReply->isTaken()) {
            return $this->chatPage($serverRequest, $chatReply, $chatReply->status);
        }

        if ($serverRequest->getHeaderLine(TesseraRequest::REQUEST) === '') {
            return new PageRedirect($this->chatDesk->back($serverRequest));
        }

        $pageDocument = $this->chatPage($serverRequest, null, 200);

        return $pageDocument->withSignalPatch(new SignalPatch('@chat-send', '{"chatSent": ' . $chatReply->sent . '}', false));
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted end form, with its session.
     *
     * @throws Throwable When the chat cannot be read or written, the job queued, or the request has no session.
     *
     * @return PageResponse 303 back to the store page it came from.
     */
    public function end(ServerRequestInterface $serverRequest): PageResponse
    {
        $this->chatDesk->end($serverRequest);

        return new PageRedirect($this->chatDesk->back($serverRequest));
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param ChatReply|null         $chatReply     A refused or late message; null for none.
     * @param int                    $status        200, 409 or 422.
     *
     * @throws Throwable When the chat, its messages or the profile cannot be read, the request has no session, or a key
     *                   or a region does not fit the theme.
     *
     * @return PageDocument The `/chat` page, with the reason first when the chat has ended.
     */
    private function chatPage(ServerRequestInterface $serverRequest, ?ChatReply $chatReply, int $status): PageDocument
    {
        $parts = [$this->chatDesk->heading($serverRequest)];

        if ($chatReply instanceof ChatReply && $chatReply->status === 409) {
            $parts = [new KeyedComponent('notice', new Notice($chatReply->reason, NoticeTone::Info, 'chat-ended')), ...$parts];
        }

        $dock = $this->chatDesk->dock($serverRequest, ChatDesk::CHAT_PAGE, '', $chatReply, true);

        return $this->storePages->framed($this->storePages->profile(), self::TITLE, $parts, $status, $dock);
    }
}

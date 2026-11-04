<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Storefront\Form\ChatAnswers;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /chat/messages (#32): the visitor's message to their open chat; 303 back to the store page it was sent from for
 * a plain form, the send form's fragment with the `chatSent` patch for Tessera; 422 refused or over the limit, 409
 * when no chat is open.
 */
final readonly class ChatMessagePost implements Page
{
    /**
     * @param ChatAnswers $chatAnswers The chat's store pages.
     */
    public function __construct(
        private ChatAnswers $chatAnswers,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted send form, with its session.
     *
     * @throws Throwable When the chat or its messages cannot be read or written, the request has no session, or a key
     *                   or a region does not fit the theme.
     *
     * @return PageResponse The answer.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->chatAnswers->send($serverRequest);
    }
}

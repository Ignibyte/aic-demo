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
 * POST /chat/end (#32): the visitor ends their open chat, which queues its transcript when they left an email; 303 back
 * to the store page it was sent from, whether or not a chat was open.
 */
final readonly class ChatEndPost implements Page
{
    /**
     * @param ChatAnswers $chatAnswers The chat's store pages.
     */
    public function __construct(
        private ChatAnswers $chatAnswers,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted end form, with its session.
     *
     * @throws Throwable When the chat cannot be read or written, the job queued, or the request has no session.
     *
     * @return PageResponse 303 back.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->chatAnswers->end($serverRequest);
    }
}

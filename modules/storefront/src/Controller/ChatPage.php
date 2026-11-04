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
 * GET /chat (#32): the visitor's chat on a page of its own, the dock open, which also marks it read; with no chat open,
 * a page that says so and links to the cars.
 */
final readonly class ChatPage implements Page
{
    /**
     * @param ChatAnswers $chatAnswers The chat's store pages.
     */
    public function __construct(
        private ChatAnswers $chatAnswers,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     *
     * @throws Throwable When the chat, its messages or the profile cannot be read, the request has no session, or a key
     *                   or a region does not fit the theme.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->chatAnswers->page($serverRequest);
    }
}

<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Storefront\Service\CarPages;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /cars/{slug}/chat (#32): a visitor starts a chat about a car for sale, which goes to the salesperson on its
 * leads or the sales manager; the car's page answers with the dock open on the chat (200), or the start form again
 * with its reason (422); 404 for a car that is not for sale.
 */
final readonly class CarChatPost implements Page
{
    /**
     * @param CarPages $carPages The car's page.
     */
    public function __construct(
        private CarPages $carPages,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted start form, routed with the slug, with its session.
     *
     * @throws Throwable When the car, its photos, the leads or the chats cannot be read or written, the request has no
     *                   session, or a key or a region does not fit the theme.
     *
     * @return PageResponse The car's page, the chat open or the start refused; or the 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->carPages->chat($serverRequest);
    }
}

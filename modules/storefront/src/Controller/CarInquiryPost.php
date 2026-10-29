<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Leads\Entity\LeadKind;
use App\Storefront\Service\CarPages;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /cars/{slug}/inquiry (#18): a visitor's question about a car in stock, recorded for a salesperson; the car's
 * page answers with a thank-you in the question's panel (200), or the form again with its errors (422); 404 for a car
 * that is not for sale.
 */
final readonly class CarInquiryPost implements Page
{
    /**
     * @param CarPages $carPages The car's page.
     */
    public function __construct(
        private CarPages $carPages,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, routed with the slug, with its session.
     *
     * @throws Throwable When the car, its photos or the leads cannot be read or written, the request has no session, or
     *                   a key or a region does not fit the theme.
     *
     * @return PageResponse The car's page, thanked or refused; or the 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->carPages->send($serverRequest, LeadKind::Inquiry);
    }
}

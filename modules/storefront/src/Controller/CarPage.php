<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Storefront\Service\CarPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * GET /cars/{slug}: one car for sale, its photos, details and price, and the forms to ask about it or book a test
 * drive (#18); 404 in the public look for a car that is sold, on hold or unknown, or for a slug that is not the car's
 * own.
 */
final readonly class CarPage implements Page
{
    /**
     * @param CarPages $carPages The car's page.
     */
    public function __construct(
        private CarPages $carPages,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the slug, with its session.
     *
     * @throws LogicException   When an entity type is malformed, the request has no session, or a key or a region does
     *                          not fit the theme.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return PageResponse The car's page, or the 404.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->carPages->show($serverRequest);
    }
}

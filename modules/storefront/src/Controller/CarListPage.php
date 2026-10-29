<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Storefront\Service\CarListing;
use App\Storefront\Service\StorePages;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /cars: the cars for sale, filtered, sorted and paged by plain GET parameters, for anyone, signed in or not.
 */
final readonly class CarListPage implements Page
{
    /**
     * @param StorePages $storePages The public pages' document.
     * @param CarListing $carListing The list's parts.
     */
    public function __construct(
        private StorePages $storePages,
        private CarListing $carListing,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws QueryException           When the makes or years cannot be read.
     * @throws InvalidArgumentException When a key or a region does not fit the theme.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->storePages->page('Cars for sale', $this->carListing->parts($serverRequest), 200);
    }
}

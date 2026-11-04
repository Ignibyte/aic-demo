<?php

declare(strict_types=1);

namespace App\Storefront\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Chat\Service\ChatDesk;
use App\Storefront\Service\CarListing;
use App\Storefront\Service\StorePages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /cars: the cars for sale, filtered, sorted and paged by plain GET parameters, for anyone, signed in or not.
 */
final readonly class CarListPage implements Page
{
    /**
     * @param StorePages $storePages The public pages' document.
     * @param CarListing $carListing The list's parts.
     * @param ChatDesk   $chatDesk   The chat's dock (#32).
     */
    public function __construct(
        private StorePages $storePages,
        private CarListing $carListing,
        private ChatDesk $chatDesk,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws QueryException           When the makes, the years or the chat cannot be read.
     * @throws LogicException           When the request carries no session, or a key or a region does not fit the theme.
     * @throws UnexpectedValueException When a stored chat side is no side.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $dock = $this->chatDesk->pageDock($serverRequest);

        return $this->storePages->page('Cars for sale', $this->carListing->parts($serverRequest), 200, $dock);
    }
}

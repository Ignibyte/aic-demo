<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Storefront\Form\CarQuery;
use LogicException;
use Override;
use RuntimeException;

use function intdiv;

/**
 * The public list's results as a source (AIC #192): it holds the repository and the query the list was asked for, and
 * reads only in `build()`, which the render pass calls when it renders the results. A Tessera visit that names the
 * results alone (`@car-results`) builds them and nothing else's data. A page past the last shows the first.
 */
final readonly class CarResults implements ComponentSource
{
    /**
     * The cars on a page.
     */
    public const int PAGE = 12;

    /**
     * @param ListedVehicleRepository $listedVehicleRepository The vehicles for sale.
     * @param CarTiles                $carTiles                The grid and the pages.
     * @param CarQuery                $carQuery                What the list was asked for.
     */
    public function __construct(
        private ListedVehicleRepository $listedVehicleRepository,
        private CarTiles $carTiles,
        private CarQuery $carQuery,
    ) {
    }

    /**
     * @throws LogicException   When an entity type is malformed, or a key is.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return Component The page's cars and, past one page, the pages.
     */
    #[Override]
    public function build(): Component
    {
        $listingQuery = $this->carQuery->listingQuery;
        $count = $this->listedVehicleRepository->count($listingQuery);
        $pages = intdiv($count + self::PAGE - 1, self::PAGE);
        $page = $this->carQuery->page <= $pages
            ? $this->carQuery->page
            : 1;
        $listedVehicles = $this->listedVehicleRepository->page($listingQuery, self::PAGE, ($page - 1) * self::PAGE);

        return $this->carTiles->results($this->carQuery, $listedVehicles, $count, $page, $pages);
    }
}

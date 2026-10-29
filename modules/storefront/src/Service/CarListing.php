<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Theme\Components\StoreHeading\StoreHeading;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The public list's parts: its heading, the filter bar and the results, anchored `car-results` for the controls that
 * refresh them. The results come from their source, `CarResults`, which reads the cars only when they are rendered, so
 * a refresh of them alone reads the makes and years the query is checked against and their own cars, and nothing more.
 */
final readonly class CarListing
{
    /**
     * @param ListedVehicleRepository $listedVehicleRepository The vehicles for sale.
     * @param CarTiles                $carTiles                The grid and the pages.
     * @param CarQueries              $carQueries              Reads what the list is asked for.
     * @param CarFilters              $carFilters              The filter bar.
     */
    public function __construct(
        private ListedVehicleRepository $listedVehicleRepository,
        private CarTiles $carTiles,
        private CarQueries $carQueries = new CarQueries(),
        private CarFilters $carFilters = new CarFilters(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its query parameters.
     *
     * @throws QueryException           When the makes or years cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return list<KeyedComponent> The heading, the filter bar and the results.
     */
    public function parts(ServerRequestInterface $serverRequest): array
    {
        $makes = $this->listedVehicleRepository->makes();
        $years = $this->listedVehicleRepository->years();
        $carQuery = $this->carQueries->read($serverRequest, $makes, $years);
        $lead = 'Every car on our lot today, with its price. Filter by make, year and price.';
        $carResults = new CarResults($this->listedVehicleRepository, $this->carTiles, $carQuery);

        return [
            new KeyedComponent('heading', new StoreHeading('cars-heading', 'Cars for sale', $lead, '', '')),
            new KeyedComponent('filters', $this->carFilters->filterBar($makes, $years, $carQuery)),
            new KeyedComponent('results', new SourcedComponent($carResults), '', CarFilters::ANCHOR),
        ];
    }
}

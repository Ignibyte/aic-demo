<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Inventory\Entity\ListedVehicle;
use App\Inventory\Entity\ListingQuery;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Service\VehicleRows;
use InvalidArgumentException;
use UnexpectedValueException;

use function hash;
use function substr;

/**
 * The vehicles for sale, for the public side (#17): only the rows in stock, never one sold or on hold. The makes and
 * years for sale, a page of what a `ListingQuery` keeps and its count, every one of a make for the feed, and the one a
 * year and a key name. Each row is read as a `ListedVehicle`, so the VIN and the cost stay here.
 */
final readonly class ListedVehicleRepository
{
    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param VehicleTable $vehicleTable The `vehicles` entity type.
     * @param VehicleRows  $vehicleRows  Reads a row into a vehicle.
     */
    public function __construct(
        private EntityStore $entityStore,
        private VehicleTable $vehicleTable = new VehicleTable(),
        private VehicleRows $vehicleRows = new VehicleRows(),
    ) {
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<string> Every make for sale, in alphabetical order.
     */
    public function makes(): array
    {
        return $this->inStock()->totals()->values('make');
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return list<string> Every model year for sale, the oldest first.
     */
    public function years(): array
    {
        return $this->inStock()->totals()->values('year');
    }

    /**
     * @param ListingQuery $listingQuery What the list was asked for.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return int How many vehicles for sale it keeps.
     */
    public function count(ListingQuery $listingQuery): int
    {
        return $this->matching($listingQuery)->totals()->count();
    }

    /**
     * @param ListingQuery $listingQuery What the list was asked for.
     * @param int          $limit        How many vehicles a page holds, at least 1.
     * @param int          $offset       How many come before the page.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the limit is below 1 or the offset negative.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return list<ListedVehicle> The page's vehicles, in the query's order and then by VIN.
     */
    public function page(ListingQuery $listingQuery, int $limit, int $offset): array
    {
        $entityQuery = match ($listingQuery->sort) {
            ListingQuery::PRICE => $this->matching($listingQuery)->orderBy('list_price'),
            ListingQuery::PRICE_DESCENDING => $this->matching($listingQuery)->orderByDescending('list_price'),
            default => $this->matching($listingQuery)->orderByDescending('added_on'),
        };

        return $this->listed($entityQuery->orderBy('vin')->limit($limit)->offset($offset));
    }

    /**
     * @param string $make A make; empty for every one.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return list<ListedVehicle> Every vehicle of it for sale, newest on the lot first and then by VIN, as the
     *                             public list's first page orders them.
     */
    public function all(string $make): array
    {
        $entityQuery = $this->inStock();

        if ($make !== '') {
            $entityQuery->where('make', $make);
        }

        return $this->listed($entityQuery->orderByDescending('added_on')->orderBy('vin'));
    }

    /**
     * @param int    $year A model year.
     * @param string $key  A key, the first 6 hex digits of the SHA-256 of a VIN.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return ListedVehicle|null The vehicle for sale of that year with that key; null when none is.
     */
    public function find(int $year, string $key): ?ListedVehicle
    {
        foreach ($this->listed($this->inStock()->where('year', $year)->orderBy('vin')) as $listedVehicle) {
            if ($listedVehicle->key === $key) {
                return $listedVehicle;
            }
        }

        return null;
    }

    /**
     * @param ListingQuery $listingQuery What the list was asked for.
     *
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return EntityQuery The vehicles for sale its filters keep; a list price is never below 0, so the lowest bound
     *                     is always set.
     */
    private function matching(ListingQuery $listingQuery): EntityQuery
    {
        $entityQuery = $this->inStock()->where('list_price', $listingQuery->minPrice ?? 0, Comparison::GreaterOrEqual);

        if ($listingQuery->maxPrice !== null) {
            $entityQuery->where('list_price', $listingQuery->maxPrice, Comparison::LessOrEqual);
        }

        if ($listingQuery->year !== null) {
            $entityQuery->where('year', $listingQuery->year);
        }

        return $listingQuery->make === ''
            ? $entityQuery
            : $entityQuery->where('make', $listingQuery->make);
    }

    /**
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return EntityQuery The vehicles in stock.
     */
    private function inStock(): EntityQuery
    {
        return $this->entityStore->query($this->vehicleTable->type())->where('status', VehicleStatus::InStock->value);
    }

    /**
     * @param EntityQuery $entityQuery Vehicles, ordered and bounded.
     *
     * @throws QueryException           When the query fails.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return list<ListedVehicle> Each, as the public side sees it.
     */
    private function listed(EntityQuery $entityQuery): array
    {
        $listedVehicles = [];

        foreach ($entityQuery->rows() as $row) {
            $listedVehicles[] = $this->vehicle($row);
        }

        return $listedVehicles;
    }

    /**
     * Reads the row through `VehicleRows`, as the staff list does, then keeps what the public may see.
     *
     * @param Row $row A row of the `vehicles` entity type.
     *
     * @throws QueryException           When a column is missing or of another type.
     * @throws UnexpectedValueException When its status is no status.
     *
     * @return ListedVehicle The vehicle, its key worked out from its VIN, which goes no further.
     */
    private function vehicle(Row $row): ListedVehicle
    {
        $vehicle = $this->vehicleRows->vehicle($row);

        return new ListedVehicle(
            $row->int('id'),
            substr(hash('sha256', $vehicle->vin), 0, 6),
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
            $vehicle->trim,
            $vehicle->bodyStyle,
            $vehicle->mileage,
            $vehicle->exteriorColour,
            $vehicle->price->list,
        );
    }
}

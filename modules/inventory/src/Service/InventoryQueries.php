<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use App\Inventory\Form\InventoryQuery;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function in_array;
use function is_string;
use function preg_match;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Reads the inventory list's query parameters (`q`, `make`, `status`, `min_price`, `max_price`, `sort`, `page`). Each
 * is checked on its own, and one that is missing, unknown or malformed falls back to its default without touching the
 * others.
 */
final readonly class InventoryQueries
{
    /**
     * The columns the list sorts by.
     */
    public const array SORTS = ['year', 'make', 'model', 'mileage', 'price', 'status', 'added'];

    /**
     * @param VehicleStatuses $vehicleStatuses The statuses a filter may ask for.
     */
    public function __construct(
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     * @param list<string>           $makes         The makes on the lot.
     *
     * @return InventoryQuery What it asks for: a make that is not on the lot, a status that is none, a price that is
     *                        not 1 to 7 digits, an unknown sort or a page that is not 1 to 9999 each read as its default.
     */
    public function read(ServerRequestInterface $serverRequest, array $makes): InventoryQuery
    {
        $make = $this->field($serverRequest, 'make');
        $sort = $this->field($serverRequest, 'sort');
        $descending = str_starts_with($sort, '-');
        $key = $descending
            ? substr($sort, 1)
            : $sort;
        $known = in_array($key, self::SORTS, true);

        return new InventoryQuery(
            trim($this->field($serverRequest, 'q')),
            in_array($make, $makes, true) ? $make : '',
            $this->status($this->field($serverRequest, 'status')),
            $this->price($this->field($serverRequest, 'min_price')),
            $this->price($this->field($serverRequest, 'max_price')),
            $known ? $key : InventoryQuery::DEFAULT_SORT,
            $known ? $descending : true,
            $this->page($this->field($serverRequest, 'page')),
        );
    }

    /**
     * @param string $value A `page` parameter.
     *
     * @return int The page when it is 1 to 9999; 1 otherwise.
     */
    private function page(string $value): int
    {
        if (preg_match('/^[1-9][0-9]{0,3}$/', $value) !== 1) {
            return 1;
        }

        return (int) $value;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          A query parameter's name.
     *
     * @return string Its text; empty when it is absent, or a list (`make[]=…`) rather than one value.
     */
    private function field(ServerRequestInterface $serverRequest, string $name): string
    {
        $query = $serverRequest->getQueryParams();

        if (!array_key_exists($name, $query) || !is_string($query[$name])) {
            return '';
        }

        return $query[$name];
    }

    /**
     * @param string $value A `status` parameter.
     *
     * @return string It, when it is a status's stored value; empty otherwise.
     */
    private function status(string $value): string
    {
        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            if ($vehicleStatus->value === $value) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param string $value A price parameter.
     *
     * @return int|null Its whole dollars when it is 1 to 7 digits; null otherwise.
     */
    private function price(string $value): ?int
    {
        return preg_match('/^[0-9]{1,7}$/', $value) === 1
            ? (int) $value
            : null;
    }
}

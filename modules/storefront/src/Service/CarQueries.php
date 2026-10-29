<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use App\Inventory\Entity\ListingQuery;
use App\Staff\Service\StaffRequests;
use App\Storefront\Form\CarQuery;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function preg_match;

/**
 * Reads the public list's query parameters (`make`, `year`, `min_price`, `max_price`, `sort`, `page`). Each is checked
 * on its own, and one that is missing, unknown or malformed falls back to its default without touching the others.
 */
final readonly class CarQueries
{
    /**
     * The sorts the list offers besides its default, newest first.
     */
    public const array SORTS = [ListingQuery::PRICE, ListingQuery::PRICE_DESCENDING];

    /**
     * @param StaffRequests $staffRequests Reads a query parameter.
     */
    public function __construct(
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     * @param list<string>           $makes         The makes for sale.
     * @param list<string>           $years         The model years for sale.
     *
     * @return CarQuery What it asks for: a make or a year not for sale, a price that is not 1 to 7 digits, an unknown
     *                  sort or a page that is not 1 to 9999 each read as its default.
     */
    public function read(ServerRequestInterface $serverRequest, array $makes, array $years): CarQuery
    {
        $make = $this->staffRequests->query($serverRequest, 'make');
        $year = $this->staffRequests->query($serverRequest, 'year');
        $sort = $this->staffRequests->query($serverRequest, 'sort');
        $listingQuery = new ListingQuery(
            in_array($make, $makes, true) ? $make : '',
            in_array($year, $years, true) ? (int) $year : null,
            $this->number($serverRequest, 'min_price', '/^[0-9]{1,7}$/'),
            $this->number($serverRequest, 'max_price', '/^[0-9]{1,7}$/'),
            in_array($sort, self::SORTS, true) ? $sort : ListingQuery::NEWEST,
        );

        return new CarQuery($listingQuery, $this->number($serverRequest, 'page', '/^[1-9][0-9]{0,3}$/') ?? 1);
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     * @param string                 $name          A query parameter's name.
     * @param non-empty-string       $pattern       The digits it may hold.
     *
     * @return int|null Its value when its digits match; null otherwise.
     */
    private function number(ServerRequestInterface $serverRequest, string $name, string $pattern): ?int
    {
        $value = $this->staffRequests->query($serverRequest, $name);

        return preg_match($pattern, $value) === 1
            ? (int) $value
            : null;
    }
}

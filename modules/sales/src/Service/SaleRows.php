<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use AIC\Entity\Domain\Comparison;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleTable;
use InvalidArgumentException;

use function substr;
use function trim;

/**
 * The sales joined to their vehicle, customer and salesperson through the `sales` entity type's references (#14, AIC
 * #190), and a row of that join read into a `Sale`, for the repositories that list sales and that read one at a time.
 * The joins are named as the old statement named them: `v`, `c` and `u`, so a condition on the salesperson's login is
 * `u.email` and the vehicle's cost `v.cost`, and their columns come back as `v_vin`, `c_name` and `u_name`.
 */
final readonly class SaleRows
{
    /**
     * @param SaleTable    $saleTable    The `sales` entity type.
     * @param MonthLengths $monthLengths How long a month is.
     */
    public function __construct(
        private SaleTable $saleTable = new SaleTable(),
        private MonthLengths $monthLengths = new MonthLengths(),
    ) {
    }

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @throws InvalidArgumentException When an entity type or a join is malformed.
     *
     * @return EntityQuery Every sale, joined to its vehicle as `v`, its customer as `c` and its salesperson as `u`.
     */
    public function joined(EntityStore $entityStore): EntityQuery
    {
        return $entityStore->query($this->saleTable->type())
            ->with('vehicle_id', 'v')
            ->with('customer_id', 'c')
            ->with('salesperson_id', 'u');
    }

    /**
     * @param EntityQuery $entityQuery A query of `joined()`.
     *
     * @throws InvalidArgumentException When the query is not over the sales.
     *
     * @return EntityQuery It in the order the sales are listed in: newest first, then the later recorded.
     */
    public function newest(EntityQuery $entityQuery): EntityQuery
    {
        return $entityQuery->orderByDescending('sold_on')->orderByDescending('id');
    }

    /**
     * @param EntityQuery $entityQuery A query of `joined()`.
     * @param string      $month       A month, `YYYY-MM`.
     *
     * @throws InvalidArgumentException When the query is not over the sales.
     *
     * @return EntityQuery It keeping the sales from the month's first day to its last, which `MonthLengths` works out
     *                     by arithmetic rather than a PHP date class, which elephc is not known to compile.
     */
    public function inMonth(EntityQuery $entityQuery, string $month): EntityQuery
    {
        $last = $this->monthLengths->days((int) substr($month, 0, 4), (int) substr($month, 5, 2));

        return $entityQuery->where('sold_on', $month . '-01', Comparison::GreaterOrEqual)
            ->where('sold_on', $month . '-' . $last, Comparison::LessOrEqual);
    }

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `rows()` list has
     * no type to it, as `CustomerRows` notes. No AIC ticket carries this yet. The vehicle's title is its year, make,
     * model and trim, trimmed, as the old statement built it.
     *
     * @param Row $row A row of `joined()`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Sale The sale.
     */
    public function sale(Row $row): Sale
    {
        $vehicle = trim($row->int('v_year') . ' ' . $row->string('v_make') . ' ' . $row->string('v_model') . ' ' . $row->string('v_trim'));

        return new Sale(
            $row->int('id'),
            $row->string('v_vin'),
            $vehicle,
            $row->int('customer_id'),
            $row->string('c_name'),
            $row->string('u_name'),
            $row->int('price'),
            $row->string('sold_on'),
        );
    }
}

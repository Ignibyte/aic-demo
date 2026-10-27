<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Sales\Entity\Sale;

/**
 * A row of `sales`, joined to its vehicle, customer and salesperson, read into a `Sale`, for the repositories that
 * list sales and that read one at a time.
 */
final readonly class SaleRows
{
    /**
     * The columns a sale is read from: the vehicle's title and the day built as text by Postgres.
     */
    public const string SELECTED = "s.id, v.vin, trim(v.year || ' ' || v.make || ' ' || v.model || ' ' || v.trim) AS vehicle,"
        . " c.id AS customer_id, c.name AS customer, u.name AS salesperson, s.price, to_char(s.sold_on, 'YYYY-MM-DD') AS sold_on";

    /**
     * The sales joined to what they name.
     */
    public const string FROM = ' FROM sales s JOIN vehicles v ON v.id = s.vehicle_id JOIN customers c ON c.id = s.customer_id'
        . ' JOIN users u ON u.id = s.salesperson_id';

    /**
     * The order the sales are listed in: newest first, then the later recorded.
     */
    public const string NEWEST = ' ORDER BY s.sold_on DESC, s.id DESC';

    /**
     * Reads the row through a native `Row` parameter: elephc reads no phpdoc, so a row straight off a `fetchAll()` list
     * has no type to it, as `CustomerRows` notes. No AIC ticket carries this yet.
     *
     * @param Row $row A row with every column of `SELECTED`.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Sale The sale.
     */
    public function sale(Row $row): Sale
    {
        return new Sale(
            $row->int('id'),
            $row->string('vin'),
            $row->string('vehicle'),
            $row->int('customer_id'),
            $row->string('customer'),
            $row->string('salesperson'),
            $row->int('price'),
            $row->string('sold_on'),
        );
    }
}

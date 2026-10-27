<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Sales\Entity\Choice;
use App\Sales\Entity\SaleVehicle;

/**
 * What a sale form chooses from and names: the vehicles not sold, the customers and the staff as options, each keyed
 * by what is unique in its table (a VIN, an email, a login); a vehicle, a customer and a member of staff found by that
 * key; and the database's today.
 */
final readonly class SaleChoiceRepository
{
    /**
     * The vehicles not sold, and the one a form chose, by make, model and VIN.
     */
    private const string VEHICLES = "SELECT vin AS key, trim(year || ' ' || make || ' ' || model || ' ' || trim) || ', ' || vin AS label"
        . " FROM vehicles WHERE status <> 'sold' OR vin = ? ORDER BY make, model, vin";

    /**
     * The customers by name, each with the email that tells two of a name apart.
     */
    private const string CUSTOMERS = "SELECT email AS key, name || ' (' || email || ')' AS label FROM customers ORDER BY name, id";

    /**
     * The staff by name.
     */
    private const string STAFF = 'SELECT email AS key, name AS label FROM users ORDER BY name, id';

    /**
     * A vehicle by VIN, as a sale needs it.
     */
    private const string VEHICLE = "SELECT id, status, list_price, to_char(added_on, 'YYYY-MM-DD') AS added_on FROM vehicles"
        . ' WHERE vin = ? LIMIT 1';

    /**
     * The database's today.
     */
    private const string DAY = 'SELECT current_date::text AS day';

    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $chosen The VIN a form chose, offered even when it is sold; empty for none.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Choice> The vehicles a sale may name, by VIN.
     */
    public function vehicles(string $chosen): array
    {
        return $this->choices(self::VEHICLES, [$chosen]);
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<Choice> The customers, by email.
     */
    public function customers(): array
    {
        return $this->choices(self::CUSTOMERS, []);
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return list<Choice> The staff, by login.
     */
    public function staff(): array
    {
        return $this->choices(self::STAFF, []);
    }

    /**
     * @param string $vin A VIN, in capitals.
     *
     * @throws QueryException When the query fails.
     *
     * @return SaleVehicle|null The vehicle with it; null when none has it.
     */
    public function vehicle(string $vin): ?SaleVehicle
    {
        foreach ($this->database->fetchAll(self::VEHICLE, [$vin]) as $row) {
            return $this->saleVehicle($row);
        }

        return null;
    }

    /**
     * @param string $email A customer's email.
     *
     * @throws QueryException When the query fails.
     *
     * @return int The customer's id; 0 when no customer has it.
     */
    public function customerId(string $email): int
    {
        return $this->idFor('SELECT id FROM customers WHERE email = ? LIMIT 1', $email);
    }

    /**
     * @param string $login A member of staff's login.
     *
     * @throws QueryException When the query fails.
     *
     * @return int Their id; 0 when no member of staff has it.
     */
    public function memberId(string $login): int
    {
        return $this->idFor('SELECT id FROM users WHERE email = ? LIMIT 1', $login);
    }

    /**
     * @throws QueryException When the query fails.
     *
     * @return string The database's today, `YYYY-MM-DD`.
     */
    public function currentDay(): string
    {
        return $this->database->fetchOne(self::DAY, [])->string('day');
    }

    /**
     * @param string       $sql    A query giving `key` and `label`.
     * @param list<string> $values Its values.
     *
     * @throws QueryException When the query fails.
     *
     * @return list<Choice> Its rows as choices, in order.
     */
    private function choices(string $sql, array $values): array
    {
        $choices = [];

        foreach ($this->database->fetchAll($sql, $values) as $row) {
            $choices[] = $this->choice($row);
        }

        return $choices;
    }

    /**
     * @param string $sql A query giving one `id` for one `?`.
     * @param string $key Its value.
     *
     * @throws QueryException When the query fails.
     *
     * @return int The id; 0 when no row has the key.
     */
    private function idFor(string $sql, string $key): int
    {
        foreach ($this->database->fetchAll($sql, [$key]) as $row) {
            return $this->rowId($row);
        }

        return 0;
    }

    /**
     * Reads the row through a native `Row` parameter, as `SaleRows::sale()` does; so do the two below.
     *
     * @param Row $row A row of `choices()`'s query.
     *
     * @throws QueryException When a column is missing.
     *
     * @return Choice The choice.
     */
    private function choice(Row $row): Choice
    {
        return new Choice($row->string('key'), $row->string('label'));
    }

    /**
     * @param Row $row A row of `idFor()`'s query.
     *
     * @throws QueryException When it has no id.
     *
     * @return int The id.
     */
    private function rowId(Row $row): int
    {
        return $row->int('id');
    }

    /**
     * @param Row $row A row of `VEHICLE`.
     *
     * @throws QueryException When a column is missing.
     *
     * @return SaleVehicle The vehicle.
     */
    private function saleVehicle(Row $row): SaleVehicle
    {
        return new SaleVehicle($row->int('id'), $row->string('status') === 'sold', $row->int('list_price'), $row->string('added_on'));
    }
}

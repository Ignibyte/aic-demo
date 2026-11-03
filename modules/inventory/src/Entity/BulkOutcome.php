<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * What a bulk edit's transaction found (#29): `EntityStore::transaction()` runs a closure that returns nothing, so the
 * work inside it holds here the vehicles it read for update and why any of them was refused, and the bulk edit reads
 * them once the transaction has committed. No refusal means the change was written.
 */
final class BulkOutcome
{
    /**
     * The vehicles the transaction read, by VIN.
     *
     * @var list<Vehicle>
     */
    private array $vehicles = [];

    /**
     * Why each refused vehicle was refused, by VIN.
     *
     * @var array<string, string>
     */
    private array $refusals = [];

    /**
     * @param list<Vehicle>         $vehicles The vehicles the transaction read, before any change.
     * @param array<string, string> $refusals Why each refused VIN was refused; empty when the change was written.
     */
    public function hold(array $vehicles, array $refusals): void
    {
        $this->vehicles = $vehicles;
        $this->refusals = $refusals;
    }

    /**
     * @return list<Vehicle> The vehicles the transaction read, before any change.
     */
    public function vehicles(): array
    {
        return $this->vehicles;
    }

    /**
     * @return array<string, string> Why each refused VIN was refused; empty when the change was written.
     */
    public function refusals(): array
    {
        return $this->refusals;
    }
}

<?php

declare(strict_types=1);

namespace App\Sales\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Repository\VehicleRepository;
use App\Sales\Entity\SeedSale;
use App\Sales\Repository\SaleRepository;
use App\Sales\Service\SeedSales;
use InvalidArgumentException;
use Override;

use function count;

/**
 * `bin/aic sales:seed`: saves the seed's 36 sold vehicles and its 44 invented sales, each of whose vehicles it saves
 * sold through inventory's `VehicleRepository`; run again, it updates the same rows. It takes no arguments: exit 2
 * with its usage when given any. It needs the staff, inventory and customers seeds first, and answers exit 1, naming
 * them, when what a sale names is missing.
 */
final readonly class SalesSeed implements Command
{
    /**
     * @param SaleRepository    $saleRepository    The sales, and the sales staff.
     * @param VehicleRepository $vehicleRepository Saves the seed's vehicles.
     * @param SeedSales         $seedSales         The invented vehicles and sales.
     */
    public function __construct(
        private SaleRepository $saleRepository,
        private VehicleRepository $vehicleRepository,
        private SeedSales $seedSales = new SeedSales(),
    ) {
    }

    /**
     * @param CommandInput $commandInput Its arguments, which must be none.
     *
     * @throws QueryException           When a vehicle or a sale cannot be stored.
     * @throws InvalidArgumentException When the result's exit code is invalid, or a seeded vehicle's day is no day.
     *
     * @return CommandResponse How many were saved, the seeds to run first, or the usage.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['usage: bin/aic sales:seed'], 2);
        }

        $sellers = $this->saleRepository->sellers();

        if ($sellers === []) {
            return new CommandResult(['No salesperson or sales manager: run bin/aic staff:seed with LOTLINE_STAFF_PASSWORD set first.'], 1);
        }

        $vehicles = $this->saveVehicles();
        $sales = $this->seedSales->sales($sellers);
        $saved = $this->saveSales($sales);

        if ($saved < count($sales)) {
            $missing = $saved . ' of ' . count($sales) . ' invented sales saved:'
                . ' run bin/aic inventory:seed and bin/aic customers:seed first.';

            return new CommandResult([$missing], 1);
        }

        return new CommandResult([$vehicles . ' invented sold vehicles and ' . $saved . ' invented sales saved.'], 0);
    }

    /**
     * Saves the seed's own vehicles and the inventory seed's sold ones, each sold, through inventory's repository, so
     * every vehicle the seed sells is marked sold.
     *
     * @throws QueryException When a vehicle cannot be stored.
     *
     * @return int How many vehicles of its own the seed saved.
     */
    private function saveVehicles(): int
    {
        $vehicles = $this->seedSales->vehicles();

        foreach ($vehicles as $vehicle) {
            $this->vehicleRepository->save($vehicle);
        }

        foreach ($this->seedSales->soldOnTheLot() as $vehicle) {
            $this->vehicleRepository->save($vehicle);
        }

        return count($vehicles);
    }

    /**
     * @param list<SeedSale> $sales The seed's sales.
     *
     * @throws QueryException When a sale cannot be stored.
     *
     * @return int How many were saved; one whose vehicle, customer or salesperson is missing is not.
     */
    private function saveSales(array $sales): int
    {
        $saved = 0;

        foreach ($sales as $sale) {
            if (!$this->saleRepository->save($sale)) {
                continue;
            }

            $saved += 1;
        }

        return $saved;
    }
}

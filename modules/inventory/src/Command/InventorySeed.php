<?php

declare(strict_types=1);

namespace App\Inventory\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Inventory\Repository\VehicleRepository;
use App\Inventory\Service\SeedVehicles;
use InvalidArgumentException;
use Override;

use function count;

/**
 * `bin/aic inventory:seed`: saves the 60 invented vehicles on Ridgeway Motors' lot; run again, it updates the same
 * rows by VIN. It takes no arguments: exit 2 with its usage when given any.
 */
final readonly class InventorySeed implements Command
{
    /**
     * @param VehicleRepository $vehicleRepository The vehicles.
     * @param SeedVehicles      $seedVehicles      The invented vehicles.
     */
    public function __construct(
        private VehicleRepository $vehicleRepository,
        private SeedVehicles $seedVehicles = new SeedVehicles(),
    ) {
    }

    /**
     * @param CommandInput $commandInput Its arguments, which must be none.
     *
     * @throws QueryException           When a vehicle cannot be stored.
     * @throws InvalidArgumentException When the result's exit code is invalid.
     *
     * @return CommandResponse How many were saved, or the usage.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['usage: bin/aic inventory:seed'], 2);
        }

        $vehicles = $this->seedVehicles->all();

        foreach ($vehicles as $vehicle) {
            $this->vehicleRepository->save($vehicle);
        }

        return new CommandResult([count($vehicles) . ' invented vehicles saved.'], 0);
    }
}

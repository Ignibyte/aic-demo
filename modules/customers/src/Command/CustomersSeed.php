<?php

declare(strict_types=1);

namespace App\Customers\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Customers\Repository\CustomerRepository;
use App\Customers\Service\SeedCustomers;
use InvalidArgumentException;
use Override;

use function count;

/**
 * `bin/aic customers:seed`: saves Ridgeway Motors' 40 invented customers; run again, it updates the same rows by
 * email. It takes no arguments: exit 2 with its usage when given any.
 */
final readonly class CustomersSeed implements Command
{
    /**
     * @param CustomerRepository $customerRepository The customers.
     * @param SeedCustomers      $seedCustomers      The invented customers.
     */
    public function __construct(
        private CustomerRepository $customerRepository,
        private SeedCustomers $seedCustomers = new SeedCustomers(),
    ) {
    }

    /**
     * @param CommandInput $commandInput Its arguments, which must be none.
     *
     * @throws QueryException           When a customer cannot be stored.
     * @throws InvalidArgumentException When the result's exit code is invalid.
     *
     * @return CommandResponse How many were saved, or the usage.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['usage: bin/aic customers:seed'], 2);
        }

        $customers = $this->seedCustomers->all();

        foreach ($customers as $customer) {
            $this->customerRepository->save($customer);
        }

        return new CommandResult([count($customers) . ' invented customers saved.'], 0);
    }
}

<?php

declare(strict_types=1);

namespace App\Finance\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Module\Attribute\Tagged;
use App\Theme\Components\FinanceCalculator\FinanceCalculator;
use InvalidArgumentException;

/**
 * The dealership's default APR, and a car's calculator at it, for a page that cannot read the profile itself (#26):
 * inventory's vehicle page asks the first `FinanceRates` plugin, which may keep what it read for the rest of the
 * request, so the APR and the footer's name come from one read. With no plugin it is the standard 6.90%.
 */
final readonly class DealershipRates
{
    /**
     * @param EntityStore        $entityStore        The entity store, handed to the plugin.
     * @param list<object>       $financeRates       The `FinanceRates` plugins, in tag order (`#[Tagged]`).
     * @param FinanceCalculators $financeCalculators Builds the calculator.
     */
    public function __construct(
        private EntityStore $entityStore,
        #[Tagged(FinanceRates::class)]
        private array $financeRates,
        private FinanceCalculators $financeCalculators = new FinanceCalculators(),
    ) {
    }

    /**
     * @param int $price A car's price, in whole dollars.
     *
     * @throws QueryException           When the plugin cannot read the APR.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return FinanceCalculator The car's calculator at the dealership's APR.
     */
    public function calculator(int $price): FinanceCalculator
    {
        return $this->financeCalculators->calculator($price, $this->apr());
    }

    /**
     * @throws QueryException           When the plugin cannot read the APR.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return int The dealership's default APR, in basis points.
     */
    public function apr(): int
    {
        foreach ($this->financeRates as $financeRate) {
            if ($financeRate instanceof FinanceRates) {
                return $this->pluginApr($financeRate);
            }
        }

        return Payments::STANDARD_APR;
    }

    /**
     * Asks one plugin through a parameter typed with the plugin type: natively an `instanceof` does not narrow an
     * `object` local, so a call on it dispatches dynamically (AIC #61, framework/docs/native.md, "Write code with
     * concrete types"), as staff's `StaffDealership` does.
     *
     * @param FinanceRates $financeRates A plugin.
     *
     * @throws QueryException           When the plugin cannot read the APR.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return int The plugin's APR, in basis points.
     */
    private function pluginApr(FinanceRates $financeRates): int
    {
        return $financeRates->apr($this->entityStore);
    }
}

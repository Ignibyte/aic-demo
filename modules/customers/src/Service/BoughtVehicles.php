<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Customers\Entity\Customer;
use App\Customers\Entity\Purchase;
use InvalidArgumentException;

/**
 * Asks the `CustomerPurchases` plugins what a customer bought, every plugin's purchases in tag order. With no plugin
 * a customer has bought nothing.
 */
final readonly class BoughtVehicles
{
    /**
     * @param EntityStore  $entityStore       The entity store, handed to each plugin.
     * @param list<object> $customerPurchases The `CustomerPurchases` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private EntityStore $entityStore,
        private array $customerPurchases,
    ) {
    }

    /**
     * @param Customer $customer A customer.
     *
     * @throws QueryException When a plugin cannot read the purchases.
     *
     * @return list<Purchase> What the customer bought.
     */
    public function bought(Customer $customer): array
    {
        $purchases = [];

        foreach ($this->customerPurchases as $customerPurchase) {
            if (!($customerPurchase instanceof CustomerPurchases)) {
                continue;
            }

            foreach ($this->pluginPurchases($customerPurchase, $customer->id) as $purchase) {
                $purchases[] = $purchase;
            }
        }

        return $purchases;
    }

    /**
     * Asks one plugin through a parameter typed with the plugin type: natively an `instanceof` does not narrow an
     * `object` local, so a call on it dispatches dynamically and what it returns is never released (AIC #61,
     * framework/docs/native.md, "Write code with concrete types").
     *
     * @param CustomerPurchases $customerPurchases A plugin.
     * @param int               $customerId        A customer's id.
     *
     * @throws QueryException           When the plugin cannot read the purchases.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return list<Purchase> What the plugin says the customer bought, newest first.
     */
    private function pluginPurchases(CustomerPurchases $customerPurchases, int $customerId): array
    {
        return $customerPurchases->purchases($this->entityStore, $customerId);
    }
}

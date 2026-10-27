<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Entity\Purchase;

/**
 * Asks the `CustomerPurchases` plugins what a customer bought, every plugin's purchases in tag order. With no plugin
 * a customer has bought nothing.
 */
final readonly class BoughtVehicles
{
    /**
     * @param Database     $database          The database, handed to each plugin.
     * @param list<object> $customerPurchases The `CustomerPurchases` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private Database $database,
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

            foreach ($customerPurchase->purchases($this->database, $customer->id) as $purchase) {
                $purchases[] = $purchase;
            }
        }

        return $purchases;
    }
}

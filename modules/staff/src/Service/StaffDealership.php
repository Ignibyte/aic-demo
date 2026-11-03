<?php

declare(strict_types=1);

namespace App\Staff\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Module\Attribute\Tagged;
use InvalidArgumentException;

/**
 * The dealership's name for the staff pages (#23): the sign-in panel, the staff footer, `/`, the dashboard and the
 * customers' and sales' pages name it from the profile an admin keeps, through the first `DealershipNames` plugin, which
 * may keep what it read for the rest of the request (#25), so a rename reaches the next page. With no plugin it says
 * "the dealership".
 */
final readonly class StaffDealership
{
    /**
     * What the pages say when no module names the dealership.
     */
    private const string NAMELESS = 'the dealership';

    /**
     * @param EntityStore  $entityStore     The entity store, handed to the plugin.
     * @param list<object> $dealershipNames The `DealershipNames` plugins, in tag order (`#[Tagged]`).
     */
    public function __construct(
        private EntityStore $entityStore,
        #[Tagged(DealershipNames::class)]
        private array $dealershipNames,
    ) {
    }

    /**
     * @throws QueryException           When the plugin cannot read the name.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return string The dealership's name.
     */
    public function name(): string
    {
        foreach ($this->dealershipNames as $dealershipName) {
            if ($dealershipName instanceof DealershipNames) {
                return $this->pluginName($dealershipName);
            }
        }

        return self::NAMELESS;
    }

    /**
     * Asks one plugin through a parameter typed with the plugin type: natively an `instanceof` does not narrow an
     * `object` local, so a call on it dispatches dynamically and what it returns is never released (AIC #61,
     * framework/docs/native.md, "Write code with concrete types").
     *
     * @param DealershipNames $dealershipNames A plugin.
     *
     * @throws QueryException           When the plugin cannot read the name.
     * @throws InvalidArgumentException When an entity type the plugin reads through is malformed.
     *
     * @return string The plugin's name for the dealership.
     */
    private function pluginName(DealershipNames $dealershipNames): string
    {
        return $dealershipNames->name($this->entityStore);
    }
}

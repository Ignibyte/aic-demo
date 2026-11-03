<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use AIC\Entity\Application\EntityStore;
use App\Finance\Service\FinanceRates;
use App\Settings\Entity\DealershipProfile;
use App\Staff\Service\DealershipNames;
use Override;

/**
 * Staff's `DealershipNames` plugin (#23) and finance's `FinanceRates` plugin (#26): the dealership's name and its
 * default APR from its profile's one row, or the defaults until a first save, for the staff pages and the vehicle
 * page's payment calculator. The generated provider builds it with no arguments, as one singleton for both plugin
 * types, so it reads through the entity store the first call hands it. It keeps the profile it read for the rest of
 * the request (#25): the request's plugins are built once for it, and a page that names the dealership in its content
 * and its footer, or shows the calculator and the footer, would read the profile twice. Every request starts afresh,
 * so a save still reaches the next page.
 */
final class DealershipNameRepository implements DealershipNames, FinanceRates
{
    /**
     * The profile read this request; null until the first call.
     */
    private ?DealershipProfile $dealershipProfile = null;

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @return string The dealership's name.
     */
    #[Override]
    public function name(EntityStore $entityStore): string
    {
        return $this->profile($entityStore)->name;
    }

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @return int The dealership's default APR, in basis points.
     */
    #[Override]
    public function apr(EntityStore $entityStore): int
    {
        return $this->profile($entityStore)->aprBasisPoints;
    }

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @return DealershipProfile The profile, read on the request's first call.
     */
    private function profile(EntityStore $entityStore): DealershipProfile
    {
        $this->dealershipProfile ??= (new ProfileRepository($entityStore))->profile();

        return $this->dealershipProfile;
    }
}

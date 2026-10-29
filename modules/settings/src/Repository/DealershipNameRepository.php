<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use AIC\Entity\Application\EntityStore;
use App\Staff\Service\DealershipNames;
use Override;

/**
 * Staff's `DealershipNames` plugin (#23): the dealership's name from its profile's one row, or the default until a
 * first save, for the staff pages. The generated provider builds it with no arguments, so it reads through the entity
 * store each call hands it.
 */
final readonly class DealershipNameRepository implements DealershipNames
{
    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @return string The dealership's name.
     */
    #[Override]
    public function name(EntityStore $entityStore): string
    {
        return (new ProfileRepository($entityStore))->profile()->name;
    }
}

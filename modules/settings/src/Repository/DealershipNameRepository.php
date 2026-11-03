<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use AIC\Entity\Application\EntityStore;
use App\Staff\Service\DealershipNames;
use Override;

/**
 * Staff's `DealershipNames` plugin (#23): the dealership's name from its profile's one row, or the default until a
 * first save, for the staff pages. The generated provider builds it with no arguments, so it reads through the entity
 * store the first call hands it. It keeps what it read for the rest of the request (#25): the request's plugins are
 * built once for it, and a page that names the dealership in its content and its footer read the profile twice. Every
 * request starts afresh, so a rename still reaches the next page.
 */
final class DealershipNameRepository implements DealershipNames
{
    /**
     * The name read this request; null until the first call.
     */
    private ?string $name = null;

    /**
     * @param EntityStore $entityStore The entity store.
     *
     * @return string The dealership's name.
     */
    #[Override]
    public function name(EntityStore $entityStore): string
    {
        $this->name ??= (new ProfileRepository($entityStore))->profile()->name;

        return $this->name;
    }
}

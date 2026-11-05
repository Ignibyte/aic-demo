<?php

declare(strict_types=1);

/*
 * The brochures module's manifest (#44): each car for sale's one-page
 * brochure, kept in AIC's private files. It holds the brochure's key, its
 * queue and its download, and requires no module, so both modules that need
 * them can require it: inventory, whose forms change a vehicle and queue the
 * `car-brochure` job, and storefront, which declares that job, renders the
 * brochure from its view of the car and serves it below the car's page.
 * Storefront requires inventory, so the shared piece cannot sit in either.
 * The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use App\Brochures\Service\Brochures;

return (new ModuleManifest('brochures'))
    ->export(Brochures::class);

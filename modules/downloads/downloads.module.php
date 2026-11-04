<?php

declare(strict_types=1);

/*
 * The downloads module's manifest (#36): the files staff download from a
 * vehicle's page that the app keeps in AIC's private files. First, a vehicle's
 * photos as one ZIP, which photos' `photo-bundle` job writes; the route sits
 * beside inventory's under the vehicle's page, as panels' do, since
 * inventory's controllers are at their folder's cap and photos knows a vehicle
 * by its id alone. The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Downloads\Controller\PhotoBundleStream;

return (new ModuleManifest('downloads'))
    ->requires('staff')
    ->requires('inventory')
    ->requires('photos')
    ->route(RouteMethod::Get, '/app/inventory/{vin}/photos.zip', PhotoBundleStream::class);

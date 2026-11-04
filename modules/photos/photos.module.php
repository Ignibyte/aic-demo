<?php

declare(strict_types=1);

/*
 * The photos module's manifest (#16): a vehicle's photos, their table and
 * their files in AIC's public files (#23), with the command that moves the
 * files kept under var/photos/ before. It knows a vehicle by its id alone, so it
 * requires only staff, whose signed-in member's role decides who may change
 * them; inventory requires it and owns the routes and the vehicle's page. It
 * exports the photos' service, the vehicle page's gallery and one photo, and
 * the photo bundles (#36): a vehicle's photos as one ZIP in the private files,
 * which its `photo-bundle` job writes when they change and downloads serves.
 * The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use App\Photos\Command\PhotosMoveFiles;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Job\WritePhotoBundle;
use App\Photos\Migration\CreateVehiclePhotosTable;
use App\Photos\Service\PhotoBundles;
use App\Photos\Service\PhotoGalleries;
use App\Photos\Service\VehiclePhotos;

return (new ModuleManifest('photos'))
    ->requires('staff')
    ->migration(CreateVehiclePhotosTable::class)
    ->command('photos:move-files', PhotosMoveFiles::class)
    ->job('photo-bundle', WritePhotoBundle::class)
    ->export(VehiclePhotos::class)
    ->export(PhotoGalleries::class)
    ->export(VehiclePhoto::class)
    ->export(PhotoBundles::class);

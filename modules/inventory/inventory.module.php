<?php

declare(strict_types=1);

/*
 * The inventory module's manifest (#4): the vehicles on Ridgeway Motors' lot.
 * The vehicles table, its seed, and the list under /app, which the staff
 * module's protected prefix keeps to signed-in staff. The build reads it;
 * nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Inventory\Command\InventorySeed;
use App\Inventory\Controller\InventoryListPage;
use App\Inventory\Migration\CreateVehiclesTable;

return (new ModuleManifest('inventory'))
    ->requires('staff')
    ->route(RouteMethod::Get, '/app/inventory', InventoryListPage::class)
    ->command('inventory:seed', InventorySeed::class)
    ->migration(CreateVehiclesTable::class);

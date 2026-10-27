<?php

declare(strict_types=1);

/*
 * The inventory module's manifest (#4, #5): the vehicles on Ridgeway Motors'
 * lot. The vehicles table, its seed, the list, and each vehicle's page, form
 * and delete, found by its VIN, all under /app, which the staff module's
 * protected prefix keeps to signed-in staff. The build reads it; nothing
 * reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Inventory\Command\InventorySeed;
use App\Inventory\Controller\InventoryListPage;
use App\Inventory\Controller\VehicleCreatePost;
use App\Inventory\Controller\VehicleDeletePage;
use App\Inventory\Controller\VehicleDeletePost;
use App\Inventory\Controller\VehicleEditPage;
use App\Inventory\Controller\VehicleNewPage;
use App\Inventory\Controller\VehiclePage;
use App\Inventory\Controller\VehicleUpdatePost;
use App\Inventory\Migration\CreateVehiclesTable;
use App\Inventory\Service\VehicleServices;

return (new ModuleManifest('inventory'))
    ->requires('staff')
    ->route(RouteMethod::Get, '/app/inventory', InventoryListPage::class)
    ->route(RouteMethod::Get, '/app/inventory/new', VehicleNewPage::class)
    ->route(RouteMethod::Post, '/app/inventory', VehicleCreatePost::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}', VehiclePage::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/edit', VehicleEditPage::class)
    ->route(RouteMethod::Post, '/app/inventory/{vin}', VehicleUpdatePost::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/delete', VehicleDeletePage::class)
    ->route(RouteMethod::Post, '/app/inventory/{vin}/delete', VehicleDeletePost::class)
    ->factory(VehicleServices::class)
    ->command('inventory:seed', InventorySeed::class)
    ->migration(CreateVehiclesTable::class);

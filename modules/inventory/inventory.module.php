<?php

declare(strict_types=1);

/*
 * The inventory module's manifest (#4, #5): the vehicles on Ridgeway Motors'
 * lot. The vehicles table, its seed, the list, and each vehicle's page, form
 * and delete, found by its VIN, all under /app, which the staff module's
 * protected prefix keeps to signed-in staff. It exports the plugin type a
 * vehicle's page asks about its sale (#7), and what the sales seed builds
 * vehicles with. The build reads it; nothing reads it at run time.
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
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Entity\VehicleSale;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Migration\CreateVehiclesTable;
use App\Inventory\Repository\VehicleRepository;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Inventory\Service\SeedVehicles;
use App\Inventory\Service\VehicleSales;
use App\Inventory\Service\VehicleServices;
use App\Inventory\Service\Vins;

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
    ->migration(CreateVehiclesTable::class)
    ->export(VehicleSales::class)
    ->export(VehicleSale::class)
    ->export(Vehicle::class)
    ->export(VehiclePrice::class)
    ->export(VehicleStatus::class)
    ->export(Vins::class)
    ->export(VehicleRepository::class)
    ->export(VehicleStatusRepository::class)
    ->export(SeedVehicles::class);

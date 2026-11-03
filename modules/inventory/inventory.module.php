<?php

declare(strict_types=1);

/*
 * The inventory module's manifest (#4, #5): the vehicles on Ridgeway Motors'
 * lot. The vehicles table, its seed, the list, and each vehicle's page, form
 * and delete, found by its VIN, all under /app, which the staff module's
 * protected prefix keeps to signed-in staff; and a vehicle's photos, added,
 * deleted and served there through the photos module, which it requires (#16). It exports the plugin type a
 * vehicle's page asks about its sale (#7), and what the sales seed builds
 * vehicles with; and the vehicles' entity type and the database's today, a
 * view, which sales and the dashboard read (#14); and the vehicles for sale, without
 * their VINs or costs, which the storefront shows the public (#17). The list's route comes last,
 * so the generated handler parts do not each open with the record services
 * (gate:14). A vehicle's page shows finance's payment calculator (#26), at the
 * APR finance's `FinanceRates` plugin gives, so it requires finance; the
 * delete's page and post come after the photo routes, so the second handler
 * part opens with the photos' forms rather than the record services the first
 * one opens with (gate:14). The build reads it; nothing reads it at run time.
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
use App\Inventory\Controller\VehiclePhotoDeletePost;
use App\Inventory\Controller\VehiclePhotoPost;
use App\Inventory\Controller\VehiclePhotoStream;
use App\Inventory\Controller\VehicleUpdatePost;
use App\Inventory\Entity\ListedVehicle;
use App\Inventory\Entity\ListingQuery;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Entity\VehicleSale;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Migration\CreateTodayView;
use App\Inventory\Migration\CreateVehiclesTable;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Inventory\Repository\TodayRepository;
use App\Inventory\Repository\VehicleRepository;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Inventory\Service\SeedVehicles;
use App\Inventory\Service\VehicleSales;
use App\Inventory\Service\VehicleServices;
use App\Inventory\Service\Vins;

return (new ModuleManifest('inventory'))
    ->requires('staff')
    ->requires('photos')
    ->requires('finance')
    ->route(RouteMethod::Get, '/app/inventory/new', VehicleNewPage::class)
    ->route(RouteMethod::Post, '/app/inventory', VehicleCreatePost::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}', VehiclePage::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/edit', VehicleEditPage::class)
    ->route(RouteMethod::Post, '/app/inventory/{vin}', VehicleUpdatePost::class)
    ->route(RouteMethod::Post, '/app/inventory/{vin}/photos', VehiclePhotoPost::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/photos/{position:int}', VehiclePhotoStream::class)
    ->route(RouteMethod::Post, '/app/inventory/{vin}/photos/{position:int}/delete', VehiclePhotoDeletePost::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/delete', VehicleDeletePage::class)
    ->route(RouteMethod::Post, '/app/inventory/{vin}/delete', VehicleDeletePost::class)
    ->route(RouteMethod::Get, '/app/inventory', InventoryListPage::class)
    ->factory(VehicleServices::class)
    ->command('inventory:seed', InventorySeed::class)
    ->migration(CreateVehiclesTable::class)
    ->migration(CreateTodayView::class)
    ->export(VehicleSales::class)
    ->export(VehicleSale::class)
    ->export(Vehicle::class)
    ->export(VehiclePrice::class)
    ->export(VehicleStatus::class)
    ->export(Vins::class)
    ->export(VehicleRepository::class)
    ->export(VehicleStatusRepository::class)
    ->export(SeedVehicles::class)
    ->export(VehicleTable::class)
    ->export(TodayRepository::class)
    ->export(ListedVehicleRepository::class)
    ->export(ListedVehicle::class)
    ->export(ListingQuery::class);

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
 * one opens with (gate:14). A list row is edited in place (#28): its row shown, its row as a form, and its save, a
 * PUT (AIC #242), after the list's route, so their generated part builds the rows' services alone and does not
 * open as the record's forms' part does (gate:14). The list's ticked rows are edited at once (#29): a POST, last, that
 * sets their status, moves them to a lot location (a column its own migration adds) or deletes them. A vehicle's page
 * places the panels the `VehiclePanel` plugins give (#31), which the panels module registers, and its exported price
 * changes (`vehicle_prices`, their own migration) are what one of them draws. The cars in stock with their listing
 * key (`vehicle_listings`, a view, #33) are what the storefront's comparison reads by key. A save from the vehicle's
 * form or its row, and a photo added or deleted, queue the car's brochure (#44) through the brochures module, which
 * it requires; storefront's `car-brochure` job writes it. The XML feed for listing sites (#37) reads the cars for sale
 * as `FeedListing`s, each a `ListedVehicle` and its VIN with no cost, the one export a VIN leaves through for the
 * public side. The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Inventory\Command\InventorySeed;
use App\Inventory\Controller\InventoryListPage;
use App\Inventory\Controller\VehicleBulkPost;
use App\Inventory\Controller\VehicleCreatePost;
use App\Inventory\Controller\VehicleDeletePage;
use App\Inventory\Controller\VehicleDeletePost;
use App\Inventory\Controller\VehicleEditPage;
use App\Inventory\Controller\VehicleNewPage;
use App\Inventory\Controller\VehiclePage;
use App\Inventory\Controller\VehiclePhotoDeletePost;
use App\Inventory\Controller\VehiclePhotoPost;
use App\Inventory\Controller\VehiclePhotoStream;
use App\Inventory\Controller\VehicleRowEditPage;
use App\Inventory\Controller\VehicleRowPage;
use App\Inventory\Controller\VehicleRowPut;
use App\Inventory\Controller\VehicleUpdatePost;
use App\Inventory\Entity\Feed\FeedListing;
use App\Inventory\Entity\ListedVehicle;
use App\Inventory\Entity\ListingQuery;
use App\Inventory\Entity\PricePoint;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleLot;
use App\Inventory\Entity\VehiclePanel;
use App\Inventory\Entity\VehiclePrice;
use App\Inventory\Entity\VehicleSale;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Entity\VehicleTable;
use App\Inventory\Migration\AddVehicleLocation;
use App\Inventory\Migration\CreateTodayView;
use App\Inventory\Migration\CreateVehicleListingsView;
use App\Inventory\Migration\CreateVehiclePricesTable;
use App\Inventory\Migration\CreateVehiclesTable;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Inventory\Repository\TodayRepository;
use App\Inventory\Repository\VehiclePriceRepository;
use App\Inventory\Repository\VehicleRepository;
use App\Inventory\Repository\VehicleStatusRepository;
use App\Inventory\Service\SeedVehicles;
use App\Inventory\Service\VehicleRecords;
use App\Inventory\Service\VehicleSales;
use App\Inventory\Service\VehicleServices;
use App\Inventory\Service\Vins;

return (new ModuleManifest('inventory'))
    ->requires('staff')
    ->requires('photos')
    ->requires('finance')
    ->requires('brochures')
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
    ->route(RouteMethod::Get, '/app/inventory/{vin}/row', VehicleRowPage::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/row/edit', VehicleRowEditPage::class)
    ->route(RouteMethod::Put, '/app/inventory/{vin}/row', VehicleRowPut::class)
    ->route(RouteMethod::Post, '/app/inventory/bulk', VehicleBulkPost::class)
    ->factory(VehicleServices::class)
    ->command('inventory:seed', InventorySeed::class)
    ->migration(CreateVehiclesTable::class)
    ->migration(CreateTodayView::class)
    ->migration(AddVehicleLocation::class)
    ->migration(CreateVehiclePricesTable::class)
    ->migration(CreateVehicleListingsView::class)
    ->export(VehicleSales::class)
    ->export(VehicleSale::class)
    ->export(Vehicle::class)
    ->export(VehiclePrice::class)
    ->export(VehicleStatus::class)
    ->export(VehicleLot::class)
    ->export(Vins::class)
    ->export(VehicleRepository::class)
    ->export(VehicleStatusRepository::class)
    ->export(SeedVehicles::class)
    ->export(VehicleTable::class)
    ->export(TodayRepository::class)
    ->export(ListedVehicleRepository::class)
    ->export(ListedVehicle::class)
    ->export(ListingQuery::class)
    ->export(VehiclePanel::class)
    ->export(PricePoint::class)
    ->export(VehiclePriceRepository::class)
    ->export(VehicleRecords::class)
    ->export(FeedListing::class);

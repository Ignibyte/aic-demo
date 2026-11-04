<?php

declare(strict_types=1);

/*
 * The panels module's manifest (#31): the vehicle page's heavy parts, which
 * load after it. Each is a `VehiclePanel` plugin inventory's page places (its
 * price history, the vehicles like it, its leads and holds), and each has its
 * own route under the vehicle's, which its skeleton loads and the arrived
 * panel polls, inside staff's protected /app prefix. It requires inventory for
 * the vehicles and their price changes, leads and holds for theirs, each
 * through its exported repository, and staff for the vehicle pages' frame. The
 * build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Inventory\Entity\VehiclePanel;
use App\Panels\Controller\PriceHistoryPage;
use App\Panels\Controller\SimilarVehiclesPage;
use App\Panels\Controller\VehicleActivityPage;
use App\Panels\Service\PriceHistoryPanel;
use App\Panels\Service\SimilarVehiclesPanel;
use App\Panels\Service\VehicleActivityPanel;

return (new ModuleManifest('panels'))
    ->requires('staff')
    ->requires('inventory')
    ->requires('leads')
    ->requires('holds')
    ->route(RouteMethod::Get, '/app/inventory/{vin}/price-history', PriceHistoryPage::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/similar', SimilarVehiclesPage::class)
    ->route(RouteMethod::Get, '/app/inventory/{vin}/activity', VehicleActivityPage::class)
    ->plugin(VehiclePanel::class, PriceHistoryPanel::class)
    ->plugin(VehiclePanel::class, SimilarVehiclesPanel::class)
    ->plugin(VehiclePanel::class, VehicleActivityPanel::class);

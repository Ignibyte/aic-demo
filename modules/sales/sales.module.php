<?php

declare(strict_types=1);

/*
 * The sales module's manifest (#7): the vehicles Ridgeway Motors sells. The
 * sales table, its seed, the list with its filters and totals, the form that
 * records a sale and marks the vehicle sold, and each sale's page and void,
 * all under /app, which the staff module's protected prefix keeps to
 * signed-in staff. It plugs into the vehicle's and the customer's pages, whose
 * modules it requires. The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Customers\Service\CustomerPurchases;
use App\Inventory\Service\VehicleSales;
use App\Sales\Command\SalesSeed;
use App\Sales\Controller\SaleCreatePost;
use App\Sales\Controller\SaleListPage;
use App\Sales\Controller\SaleNewPage;
use App\Sales\Controller\SalePage;
use App\Sales\Controller\SaleVoidPage;
use App\Sales\Controller\SaleVoidPost;
use App\Sales\Migration\CreateSalesTable;
use App\Sales\Service\CustomerPurchaseLookup;
use App\Sales\Service\SaleRecordServices;
use App\Sales\Service\SaleServices;
use App\Sales\Service\VehicleSaleLookup;

return (new ModuleManifest('sales'))
    ->requires('inventory')
    ->requires('customers')
    ->requires('staff')
    ->route(RouteMethod::Get, '/app/sales', SaleListPage::class)
    ->route(RouteMethod::Get, '/app/sales/new', SaleNewPage::class)
    ->route(RouteMethod::Post, '/app/sales', SaleCreatePost::class)
    ->route(RouteMethod::Get, '/app/sales/{id:int}', SalePage::class)
    ->route(RouteMethod::Get, '/app/sales/{id:int}/void', SaleVoidPage::class)
    ->route(RouteMethod::Post, '/app/sales/{id:int}/void', SaleVoidPost::class)
    ->factory(SaleServices::class)
    ->factory(SaleRecordServices::class)
    ->plugin(VehicleSales::class, VehicleSaleLookup::class)
    ->plugin(CustomerPurchases::class, CustomerPurchaseLookup::class)
    ->command('sales:seed', SalesSeed::class)
    ->migration(CreateSalesTable::class);

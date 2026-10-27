<?php

declare(strict_types=1);

/*
 * The dashboard module's manifest (#8): /app, the first page signed-in staff
 * see. It greets the member in the header, then gives this month's figures as
 * stat tiles, the sales recorded last, and three charts drawn on the server:
 * sales by month, inventory by make and revenue by salesperson. /app/events
 * (#10) is its Tessera stream, which keeps the tiles and the latest sales up
 * to date. It reads the vehicles, the sales, the customers and the staff,
 * whose modules it requires, and writes nothing; staff's protected prefix keeps both routes to
 * signed-in staff. The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Dashboard\Controller\DashboardEvents;
use App\Dashboard\Controller\DashboardPage;

return (new ModuleManifest('dashboard'))
    ->requires('customers')
    ->requires('inventory')
    ->requires('sales')
    ->requires('staff')
    ->route(RouteMethod::Get, '/app', DashboardPage::class)
    ->route(RouteMethod::Get, '/app/events', DashboardEvents::class);

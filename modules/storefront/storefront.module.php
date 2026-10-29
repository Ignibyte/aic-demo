<?php

declare(strict_types=1);

/*
 * The storefront module's manifest (#17): Ridgeway Motors' public side, for
 * customers, with no sign-in. The cars for sale at /cars, each one's page and
 * photos below it, and the same cars as JSON for listing partners at
 * /api/vehicles. It reads the vehicles for sale through inventory's exported
 * repository, which never gives a VIN or a cost, and their photos through
 * photos'; it requires staff only for its request helper. A car's page
 * carries two lead forms, a question and a test drive (#18), which post below
 * it and which leads' exported LeadDesk builds and records; the module's
 * factory builds the car's page for all three. Its pages use the
 * theme's store layout, whose regions no staff block fills; its header and
 * footer show the dealership's profile, which it requires settings for (#22).
 * The list's route
 * comes last, after the routes below it. The build reads it; nothing reads it
 * at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Storefront\Controller\CarInquiryPost;
use App\Storefront\Controller\CarListPage;
use App\Storefront\Controller\CarPage;
use App\Storefront\Controller\CarPhotoStream;
use App\Storefront\Controller\CarTestDrivePost;
use App\Storefront\Controller\VehicleFeedApi;
use App\Storefront\Controller\VehicleFeedItemApi;
use App\Storefront\Service\StoreServices;

return (new ModuleManifest('storefront'))
    ->requires('inventory')
    ->requires('photos')
    ->requires('staff')
    ->requires('leads')
    ->requires('settings')
    ->route(RouteMethod::Get, '/cars/{slug}', CarPage::class)
    ->route(RouteMethod::Post, '/cars/{slug}/inquiry', CarInquiryPost::class)
    ->route(RouteMethod::Post, '/cars/{slug}/test-drive', CarTestDrivePost::class)
    ->route(RouteMethod::Get, '/cars/{slug}/photos/{position:int}', CarPhotoStream::class)
    ->route(RouteMethod::Get, '/api/vehicles/{slug}', VehicleFeedItemApi::class)
    ->route(RouteMethod::Get, '/api/vehicles', VehicleFeedApi::class)
    ->route(RouteMethod::Get, '/cars', CarListPage::class)
    ->factory(StoreServices::class);

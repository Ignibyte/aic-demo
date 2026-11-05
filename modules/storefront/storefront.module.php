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
 * footer show the dealership's profile, which it requires settings for (#22);
 * a car's page shows finance's payment calculator at the profile's APR (#26).
 * Every store page carries the chat's dock, which chat's exported ChatDesk
 * builds (#32), and the visitor's framed chat pages sit here: a start below
 * a car, /chat, a message and the end. Every store page also carries the
 * comparison's dock (#33); the compare module builds its /compare page in the
 * same frame, with the slugs and the figures as written, so the module
 * exports StorePages, CarSlugs and CarFigures. Each car for sale has a
 * one-page brochure (#44): its `car-brochure` job, which inventory queues
 * when it changes the vehicle, renders it in the theme's brochure layout and
 * keeps it in AIC's private files under the brochures module's key, and two
 * Streams below the car serve it, inline and as an attachment; it requires
 * brochures for the key, the queue and the download. The list's route
 * comes last, after the routes below it. The build reads it; nothing reads it
 * at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Storefront\Controller\CarBrochureDownloadStream;
use App\Storefront\Controller\CarBrochureStream;
use App\Storefront\Controller\CarChatPost;
use App\Storefront\Controller\CarInquiryPost;
use App\Storefront\Controller\CarListPage;
use App\Storefront\Controller\CarPage;
use App\Storefront\Controller\CarPhotoStream;
use App\Storefront\Controller\CarTestDrivePost;
use App\Storefront\Controller\ChatEndPost;
use App\Storefront\Controller\ChatMessagePost;
use App\Storefront\Controller\ChatPage;
use App\Storefront\Controller\VehicleFeedApi;
use App\Storefront\Controller\VehicleFeedItemApi;
use App\Storefront\Job\WriteBrochure;
use App\Storefront\Service\CarFigures;
use App\Storefront\Service\CarSlugs;
use App\Storefront\Service\StorePages;
use App\Storefront\Service\StoreServices;

return (new ModuleManifest('storefront'))
    ->requires('inventory')
    ->requires('photos')
    ->requires('staff')
    ->requires('leads')
    ->requires('settings')
    ->requires('finance')
    ->requires('chat')
    ->requires('brochures')
    ->route(RouteMethod::Get, '/cars/{slug}', CarPage::class)
    ->route(RouteMethod::Post, '/cars/{slug}/inquiry', CarInquiryPost::class)
    ->route(RouteMethod::Post, '/cars/{slug}/test-drive', CarTestDrivePost::class)
    ->route(RouteMethod::Post, '/cars/{slug}/chat', CarChatPost::class)
    ->route(RouteMethod::Get, '/cars/{slug}/brochure', CarBrochureStream::class)
    ->route(RouteMethod::Get, '/cars/{slug}/brochure/download', CarBrochureDownloadStream::class)
    ->route(RouteMethod::Get, '/cars/{slug}/photos/{position:int}', CarPhotoStream::class)
    ->route(RouteMethod::Get, '/api/vehicles/{slug}', VehicleFeedItemApi::class)
    ->route(RouteMethod::Get, '/api/vehicles', VehicleFeedApi::class)
    ->route(RouteMethod::Get, '/cars', CarListPage::class)
    ->route(RouteMethod::Get, '/chat', ChatPage::class)
    ->route(RouteMethod::Post, '/chat/messages', ChatMessagePost::class)
    ->route(RouteMethod::Post, '/chat/end', ChatEndPost::class)
    ->job('car-brochure', WriteBrochure::class)
    ->factory(StoreServices::class)
    ->export(StorePages::class)
    ->export(CarSlugs::class)
    ->export(CarFigures::class);

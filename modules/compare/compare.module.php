<?php

declare(strict_types=1);

/*
 * The compare module's manifest (#33): up to three cars for sale side by
 * side. The storefront's bar ticks them in the browser; /compare shows them,
 * in the store's frame, as the drawer's fragment for Tessera and as a page of
 * its own with JavaScript off. It requires storefront for that frame, the
 * slugs and the figures as written; inventory for the cars, which it reads by
 * their slugs' keys; photos for their main photos; finance for the payment;
 * chat for the dock every store page carries; and settings, whose profile
 * service the store frame is built with. Its factory builds the comparison.
 * The route is /compare, not
 * under /cars, so storefront's /cars/{slug} never catches it. The build reads
 * it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Compare\Controller\ComparePage;
use App\Compare\Service\CompareServices;

return (new ModuleManifest('compare'))
    ->requires('storefront')
    ->requires('inventory')
    ->requires('photos')
    ->requires('finance')
    ->requires('chat')
    ->requires('settings')
    ->route(RouteMethod::Get, '/compare', ComparePage::class)
    ->factory(CompareServices::class);

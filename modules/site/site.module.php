<?php

declare(strict_types=1);

use AIC\Module\Contracts\MiddlewarePosition;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Block\NavigationBlock;
use App\Site\Block\SiteFooterBlock;
use App\Site\Command\DatabasePing;
use App\Site\Controller\BootstrapPage;
use App\Site\Controller\CoveragePage;
use App\Site\Controller\HealthApi;
use App\Site\Controller\ResilienceExpiredPage;
use App\Site\Controller\ResilienceExpiredPost;
use App\Site\Controller\ResilienceFeedPage;
use App\Site\Controller\ResilienceFeedPost;
use App\Site\Controller\ResilienceFullVisitPage;
use App\Site\Controller\ResilienceFullVisitPost;
use App\Site\Controller\ResilienceGatewayPage;
use App\Site\Controller\ResilienceProtocolPage;
use App\Site\Controller\ResilienceRefusedPage;
use App\Site\Controller\ResilienceRefusedPost;
use App\Site\Controller\ThemeGuidePage;
use App\Site\Controller\ThemeGuidePost;
use App\Site\Middleware\ResilienceFaults;

return (new ModuleManifest('site'))
    ->requires('staff')
    ->route(RouteMethod::Get, '/health', HealthApi::class)
    ->command('db:ping', DatabasePing::class)
    ->route(RouteMethod::Get, '/', BootstrapPage::class)
    ->route(RouteMethod::Get, '/theme', ThemeGuidePage::class)
    ->route(RouteMethod::Post, '/theme', ThemeGuidePost::class)
    ->route(RouteMethod::Get, '/theme/coverage', CoveragePage::class)
    ->route(RouteMethod::Get, '/theme/resilience/expired', ResilienceExpiredPage::class)
    ->route(RouteMethod::Post, '/theme/resilience/expired', ResilienceExpiredPost::class)
    ->route(RouteMethod::Get, '/theme/resilience/gateway', ResilienceGatewayPage::class)
    ->route(RouteMethod::Get, '/theme/resilience/refused', ResilienceRefusedPage::class)
    ->route(RouteMethod::Post, '/theme/resilience/refused', ResilienceRefusedPost::class)
    ->route(RouteMethod::Get, '/theme/resilience/feed', ResilienceFeedPage::class)
    ->route(RouteMethod::Post, '/theme/resilience/feed', ResilienceFeedPost::class)
    ->route(RouteMethod::Get, '/theme/resilience/protocol', ResilienceProtocolPage::class)
    ->route(RouteMethod::Get, '/theme/resilience/full-visit', ResilienceFullVisitPage::class)
    ->route(RouteMethod::Post, '/theme/resilience/full-visit', ResilienceFullVisitPost::class)
    ->middleware(ResilienceFaults::class, MiddlewarePosition::AfterSession)
    ->block(NavigationBlock::class)
    ->block(SiteFooterBlock::class);

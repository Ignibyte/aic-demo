<?php

declare(strict_types=1);

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Block\NavigationBlock;
use App\Site\Block\SiteFooterBlock;
use App\Site\Command\DatabasePing;
use App\Site\Controller\BootstrapPage;
use App\Site\Controller\HealthApi;
use App\Site\Controller\ThemeGuidePage;
use App\Site\Controller\ThemeGuidePost;

return (new ModuleManifest('site'))
    ->route(RouteMethod::Get, '/health', HealthApi::class)
    ->command('db:ping', DatabasePing::class)
    ->route(RouteMethod::Get, '/', BootstrapPage::class)
    ->route(RouteMethod::Get, '/theme', ThemeGuidePage::class)
    ->route(RouteMethod::Post, '/theme', ThemeGuidePost::class)
    ->block(NavigationBlock::class)
    ->block(SiteFooterBlock::class);

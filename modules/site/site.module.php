<?php

declare(strict_types=1);

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Command\DatabasePing;
use App\Site\Controller\AssetFile;
use App\Site\Controller\BootstrapPage;
use App\Site\Controller\HealthApi;

return (new ModuleManifest('site'))
    ->route(RouteMethod::Get, '/assets/{file}', AssetFile::class)
    ->route(RouteMethod::Get, '/assets/{dir}/{file}', AssetFile::class)
    ->route(RouteMethod::Get, '/health', HealthApi::class)
    ->command('db:ping', DatabasePing::class)
    ->route(RouteMethod::Get, '/', BootstrapPage::class);

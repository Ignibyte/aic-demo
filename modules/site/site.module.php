<?php

declare(strict_types=1);

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Command\DatabasePing;
use App\Site\Controller\HealthApi;

return (new ModuleManifest('site'))
    ->route(RouteMethod::Get, '/health', HealthApi::class)
    ->command('db:ping', DatabasePing::class);

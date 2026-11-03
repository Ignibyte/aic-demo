<?php

declare(strict_types=1);

/*
 * The finance module's manifest (#26): a car's payment calculator, which
 * storefront's car page and inventory's vehicle page both show. It builds the
 * theme's FinanceCalculator from a price and an APR, and holds the plugin type
 * through which settings gives the dealership's default APR, so a page that
 * cannot read the profile itself asks the plugin. It requires nothing: the
 * pages that show the calculator require it. The build reads it; nothing reads
 * it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use App\Finance\Service\DealershipRates;
use App\Finance\Service\FinanceCalculators;
use App\Finance\Service\FinanceRates;
use App\Finance\Service\Payments;

return (new ModuleManifest('finance'))
    ->export(FinanceCalculators::class)
    ->export(Payments::class)
    ->export(FinanceRates::class)
    ->export(DealershipRates::class);

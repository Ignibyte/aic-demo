<?php

declare(strict_types=1);

/*
 * The reports module's manifest (#20): two reports nobody asks for, each a
 * job its schedule queues on a UTC cadence. Each morning at 07:00 the
 * aged-stock job lists the vehicles on the lot longer than config/reports.php's
 * threshold to each sales manager; each night at 21:00 the daily-digest job
 * mails the managers the day's sales, leads and new stock. It owns the two
 * views they read, which read the tables of inventory, sales and leads, and it
 * requires staff for the users by role. The build reads it; nothing reads it
 * at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use App\Reports\Job\NotifyAgedStock;
use App\Reports\Job\SendDailyDigest;
use App\Reports\Migration\CreateReportViews;
use App\Reports\Service\ReportSettings;

return (new ModuleManifest('reports'))
    ->requires('staff')
    ->requires('inventory')
    ->requires('sales')
    ->requires('leads')
    ->factory(ReportSettings::class)
    ->job('aged-stock', NotifyAgedStock::class)
    ->job('daily-digest', SendDailyDigest::class)
    ->schedule('aged-stock', 'aged-stock', [], '0 7 * * *')
    ->schedule('daily-digest', 'daily-digest', [], '0 21 * * *')
    ->migration(CreateReportViews::class);

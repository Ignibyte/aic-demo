<?php

declare(strict_types=1);

/*
 * The reports module's manifest. Two reports nobody asks for (#20), each a job
 * its schedule queues on a UTC cadence: each morning at 07:00 the aged-stock
 * job lists the vehicles on the lot longer than the dealership's threshold to
 * each sales manager; each night at 21:00 the daily-digest job mails the
 * managers the day's sales, leads and new stock. Both read the dealership's
 * profile (#22), the threshold and the name, which it requires settings for. And the managers' reports
 * page (#21), /app/reports: sales by month, salespeople, inventory by make and
 * leads by kind over a range, each exported as CSV by the report-export job to
 * a download only its member gets, kept in AIC's private files (#23) with the
 * command that moves those kept under var/exports/ before, which the
 * prune-exports schedule removes after seven days; and the sales report as JSON at /api/reports/sales. It
 * owns the views they read, which read the tables of inventory, sales, leads
 * and customers, and the exports' table; it requires staff for the users by
 * role. The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Reports\Command\ReportsMoveExports;
use App\Reports\Controller\ReportExportPost;
use App\Reports\Controller\ReportExportStream;
use App\Reports\Controller\ReportsPage;
use App\Reports\Controller\SalesReportApi;
use App\Reports\Job\NotifyAgedStock;
use App\Reports\Job\PruneReportExports;
use App\Reports\Job\SendDailyDigest;
use App\Reports\Job\WriteReportExport;
use App\Reports\Migration\CreateReportExportsTable;
use App\Reports\Migration\CreateReportExportsView;
use App\Reports\Migration\CreateReportRangeViews;
use App\Reports\Migration\CreateReportViews;
use App\Reports\Service\ExportServices;
use App\Reports\Service\ReportServices;

return (new ModuleManifest('reports'))
    ->requires('staff')
    ->requires('inventory')
    ->requires('sales')
    ->requires('leads')
    ->requires('customers')
    ->requires('settings')
    ->route(RouteMethod::Get, '/app/reports', ReportsPage::class)
    ->route(RouteMethod::Post, '/app/reports/exports', ReportExportPost::class)
    ->route(RouteMethod::Get, '/app/reports/exports/{id:int}', ReportExportStream::class)
    ->route(RouteMethod::Get, '/api/reports/sales', SalesReportApi::class)
    ->factory(ReportServices::class)
    ->factory(ExportServices::class)
    ->job('aged-stock', NotifyAgedStock::class)
    ->job('daily-digest', SendDailyDigest::class)
    ->job('report-export', WriteReportExport::class)
    ->job('prune-exports', PruneReportExports::class)
    ->command('reports:move-exports', ReportsMoveExports::class)
    ->schedule('aged-stock', 'aged-stock', [], '0 7 * * *')
    ->schedule('daily-digest', 'daily-digest', [], '0 21 * * *')
    ->schedule('prune-exports', 'prune-exports', [], '15 4 * * *')
    ->migration(CreateReportViews::class)
    ->migration(CreateReportRangeViews::class)
    ->migration(CreateReportExportsTable::class)
    ->migration(CreateReportExportsView::class);

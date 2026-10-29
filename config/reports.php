<?php

declare(strict_types=1);

/*
 * The reports' settings (#20): a vehicle not sold that has been on the lot
 * more than 60 days is aged stock, which the aged-stock notification lists
 * each morning. `ReportSettings`, the reports module's factory, requires this
 * file.
 */

use App\Reports\Entity\ReportConfig;

return new ReportConfig(
    agedStockDays: 60,
);

<?php

declare(strict_types=1);

namespace App\Reports\Service;

use App\Reports\Entity\ReportConfig;
use RuntimeException;

/**
 * The reports' settings, a factory the manifest lists (#20): config/reports.php gives them, and this hands them to the
 * aged-stock job, as staff's `StaffSettings` hands out the seed's.
 */
final readonly class ReportSettings
{
    /**
     * @throws RuntimeException When config/reports.php does not return a ReportConfig.
     *
     * @return ReportConfig The aged-stock threshold.
     */
    public function reportConfig(): ReportConfig
    {
        $reportConfig = require __DIR__ . '/../../../../config/reports.php';

        if (!$reportConfig instanceof ReportConfig) {
            throw new RuntimeException('config/reports.php must return an ' . ReportConfig::class . '.');
        }

        return $reportConfig;
    }
}

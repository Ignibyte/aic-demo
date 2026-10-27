<?php

declare(strict_types=1);

namespace App\Staff\Service;

use App\Staff\Entity\StaffSeedConfig;
use RuntimeException;

/**
 * The seed settings, a factory the manifest lists: config/staff.php reads them from the environment, the one place
 * that may, and this hands them to `staff:seed`.
 */
final readonly class StaffSettings
{
    /**
     * @throws RuntimeException When config/staff.php does not return a StaffSeedConfig.
     *
     * @return StaffSeedConfig Who `staff:seed` saves.
     */
    public function staffSeedConfig(): StaffSeedConfig
    {
        $staffSeedConfig = require __DIR__ . '/../../../../config/staff.php';

        if (!$staffSeedConfig instanceof StaffSeedConfig) {
            throw new RuntimeException('config/staff.php must return an ' . StaffSeedConfig::class . '.');
        }

        return $staffSeedConfig;
    }
}

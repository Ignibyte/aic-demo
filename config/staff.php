<?php

declare(strict_types=1);

/*
 * Who `bin/aic staff:seed` saves (#3), from the environment only:
 * LOTLINE_ADMIN_EMAIL, LOTLINE_ADMIN_NAME and LOTLINE_ADMIN_PASSWORD for the
 * admin, and LOTLINE_STAFF_PASSWORD for the invented staff, who are left out
 * without it. No password lives in a file in the repository; on a dev box
 * DDEV's ignored .ddev/config.local.yaml sets them.
 */

use App\Staff\Entity\StaffSeedConfig;

$setting = static function (string $name): string {
    $value = getenv($name);

    return is_string($value)
        ? $value
        : '';
};

return new StaffSeedConfig(
    adminEmail: $setting('LOTLINE_ADMIN_EMAIL'),
    adminName: $setting('LOTLINE_ADMIN_NAME'),
    adminPassword: $setting('LOTLINE_ADMIN_PASSWORD'),
    staffPassword: $setting('LOTLINE_STAFF_PASSWORD'),
);

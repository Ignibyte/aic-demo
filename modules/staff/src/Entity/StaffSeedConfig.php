<?php

declare(strict_types=1);

namespace App\Staff\Entity;

/**
 * Who `bin/aic staff:seed` saves, from the environment through config/staff.php: the admin, and the password the
 * invented staff share. Never in a file in the repository.
 */
final readonly class StaffSeedConfig
{
    /**
     * @param string $adminEmail    LOTLINE_ADMIN_EMAIL; empty when unset.
     * @param string $adminName     LOTLINE_ADMIN_NAME; empty when unset.
     * @param string $adminPassword LOTLINE_ADMIN_PASSWORD; empty when unset.
     * @param string $staffPassword LOTLINE_STAFF_PASSWORD; empty to leave the invented staff out.
     */
    public function __construct(
        public string $adminEmail,
        public string $adminName,
        public string $adminPassword,
        public string $staffPassword,
    ) {
    }
}

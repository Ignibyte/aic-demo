<?php

declare(strict_types=1);

namespace App\Staff\Entity;

/**
 * What a member of staff is at Ridgeway Motors, stored as its value in `users.role`.
 */
enum Role: string
{
    /** Runs the app and its users. */
    case Admin = 'admin';

    /** Leads the sales floor. */
    case SalesManager = 'sales_manager';

    /** Sells. */
    case Salesperson = 'salesperson';

    /**
     * @return string The role as staff read it.
     */
    public function label(): string
    {
        // if/return rather than match: elephc cannot lower a match whose arms are enum cases yet (AIC #164).
        if ($this === self::Admin) {
            return 'Admin';
        }

        if ($this === self::SalesManager) {
            return 'Sales manager';
        }

        return 'Salesperson';
    }
}

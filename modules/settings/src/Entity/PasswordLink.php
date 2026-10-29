<?php

declare(strict_types=1);

namespace App\Settings\Entity;

/**
 * A password link as the set-password page reads it (#22): whose it is, and whether it can still set a password.
 */
final readonly class PasswordLink
{
    /**
     * @param int  $id      The link's id.
     * @param int  $userId  The member whose password it sets.
     * @param bool $used    Whether it set one already, or a newer link voided it.
     * @param bool $expired Whether its 24 hours have passed, by the database's clock.
     */
    public function __construct(
        public int $id,
        public int $userId,
        public bool $used,
        public bool $expired,
    ) {
    }

    /**
     * @return bool Whether it can still set a password: neither used nor expired.
     */
    public function open(): bool
    {
        return !$this->used && !$this->expired;
    }
}

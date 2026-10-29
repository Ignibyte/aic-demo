<?php

declare(strict_types=1);

namespace App\Reports\Entity;

use function str_contains;

/**
 * A member of staff a report goes to (#20).
 */
final readonly class Recipient
{
    /**
     * @param int    $id    Their user id.
     * @param string $login Their login, an email for everyone but the gate's own user.
     */
    public function __construct(
        public int $id,
        public string $login,
    ) {
    }

    /**
     * @return bool Whether their login is an address mail can go to.
     */
    public function mailable(): bool
    {
        return str_contains($this->login, '@');
    }
}

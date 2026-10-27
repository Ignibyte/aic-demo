<?php

declare(strict_types=1);

namespace App\Sales\Entity;

/**
 * One option a sale form or a list filter offers: the key it posts (a VIN, an email, a login or a month) and the text
 * staff read.
 */
final readonly class Choice
{
    /**
     * @param string $key   What it posts.
     * @param string $label What it shows.
     */
    public function __construct(
        public string $key,
        public string $label,
    ) {
    }
}

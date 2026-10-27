<?php

declare(strict_types=1);

namespace App\Sales\Form;

use App\Sales\Entity\SaleDetails;

/**
 * What the sale form's checks found: each refused field's message, or, when nothing was refused, the sale to record.
 */
final readonly class SaleCheck
{
    /**
     * @param array<string, string> $errors      Why each refused field is refused, by name; empty when the entry is valid.
     * @param SaleDetails|null      $saleDetails The sale the valid entry describes; null when something was refused.
     */
    public function __construct(
        public array $errors,
        public ?SaleDetails $saleDetails,
    ) {
    }
}

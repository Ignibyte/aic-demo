<?php

declare(strict_types=1);

namespace App\Leads\Entity;

use AIC\Component\Domain\KeyedComponent;

/**
 * What a car's page shows after a visitor sent one of its lead forms (#18): both lead panels, the sent one thanked or
 * refused, and the status the page answers with, 200 or 422.
 */
final readonly class LeadReply
{
    /**
     * @param list<KeyedComponent> $panels The lead panels, keyed and anchored.
     * @param int                  $status 200 when the lead was recorded, 422 when a field was refused.
     */
    public function __construct(
        public array $panels,
        public int $status,
    ) {
    }
}

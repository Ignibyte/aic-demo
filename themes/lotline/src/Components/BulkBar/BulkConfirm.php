<?php

declare(strict_types=1);

namespace App\Theme\Components\BulkBar;

/**
 * The action bar's confirm card (#29): the choice that deletes, what it asks and warns, and whether it arrives open. A
 * delete posted without script is answered with the card open, the delete chosen and its rows ticked.
 */
final readonly class BulkConfirm
{
    /**
     * @param string $remove     The choice that deletes, which asks first.
     * @param string $question   What the card asks (`Delete the ticked vehicles?`).
     * @param string $warning    What it says a delete does.
     * @param bool   $confirming Whether it arrives open, asking to confirm a delete.
     * @param int    $ticked     How many rows are ticked as it arrives, which it counts without script.
     */
    public function __construct(
        public string $remove,
        public string $question,
        public string $warning,
        public bool $confirming,
        public int $ticked,
    ) {
    }
}

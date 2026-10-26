<?php

declare(strict_types=1);

namespace App\Theme\Components\TableRow;

use App\Theme\Components\StatusBadge\BadgeTone;

/**
 * One cell: its text, and a link, a monospace face, a right-set number or a badge.
 */
final readonly class TableCell
{
    /**
     * @param string     $text      Its text.
     * @param string     $href      Where it links; empty for none.
     * @param bool       $mono      Whether it is set monospace (an identifier).
     * @param bool       $numeric   Whether it is a number, set right.
     * @param ?BadgeTone $badgeTone Its badge's colour; null for plain text.
     */
    public function __construct(
        public string $text,
        public string $href,
        public bool $mono,
        public bool $numeric,
        public ?BadgeTone $badgeTone,
    ) {
    }
}

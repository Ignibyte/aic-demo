<?php

declare(strict_types=1);

namespace App\Theme\Components\Panel;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A titled card on a dashboard, from panel.phtml: its heading and a line under it, then what it shows; half the width from
 * `xl` unless wide.
 */
final readonly class Panel implements Component
{
    /**
     * @param string               $id          The panel's id stem (`sales` gives `sales-heading`).
     * @param string               $title       Its heading.
     * @param string               $description The line under it.
     * @param bool                 $wide        Whether it spans the grid at every width.
     * @param list<KeyedComponent> $body        What it shows, in order, each keyed.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public bool $wide,
        public array $body,
    ) {
    }
}

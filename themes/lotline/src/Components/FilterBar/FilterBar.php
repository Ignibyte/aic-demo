<?php

declare(strict_types=1);

namespace App\Theme\Components\FilterBar;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A list's filters, from filter-bar.phtml: a GET search form of compact fields that Tessera answers in place, then
 * Filter and Clear.
 */
final readonly class FilterBar implements Component
{
    /**
     * @param string               $id        The form's id.
     * @param string               $action    The list's path.
     * @param string               $target    The regions Tessera updates (`content`).
     * @param list<KeyedComponent> $fields    The compact fields, each keyed by its name.
     * @param string               $clearHref The list with no filter.
     */
    public function __construct(
        public string $id,
        public string $action,
        public string $target,
        public array $fields,
        public string $clearHref,
    ) {
    }
}

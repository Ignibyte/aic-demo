<?php

declare(strict_types=1);

namespace App\Theme\Components\FilterBar;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;

/**
 * A list's filters, from filter-bar.phtml: a GET search form of compact fields that Tessera answers in place, then
 * Filter and Clear. With a history mode the form carries it as `data-push` and Filter carries `data-push="push"`, so
 * a submit without a submitter (a debounced search) takes the form's mode and a pressed Filter still adds an entry.
 */
final readonly class FilterBar implements Component
{
    /**
     * @param string               $id        The form's id.
     * @param string               $action    The list's path.
     * @param string               $target    What Tessera updates: a region (`content`) or an anchor (`@NAME`).
     * @param list<KeyedComponent> $fields    The compact fields, each keyed by its name.
     * @param string               $clearHref The list with no filter.
     * @param string               $history   Tessera's history mode for the form's own submits (`replace`); empty to push.
     */
    public function __construct(
        public string $id,
        public string $action,
        public string $target,
        public array $fields,
        public string $clearHref,
        public string $history = '',
    ) {
    }
}

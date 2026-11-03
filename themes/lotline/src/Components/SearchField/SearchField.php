<?php

declare(strict_types=1);

namespace App\Theme\Components\SearchField;

use AIC\Component\Contracts\Component;

/**
 * A list's search box, from search-field.phtml: a compact labelled search input in a filter bar's GET form. Each
 * pause of 300 ms in typing submits that form, which Tessera sends as a visit of the form's targets; a spinner in the
 * box shows while the form's request is out. With JavaScript off it is a plain field of the form.
 */
final readonly class SearchField implements Component
{
    /**
     * @param string $id       The input's id.
     * @param string $name     The field's name.
     * @param string $label    The label.
     * @param string $value    The search shown.
     * @param string $focusKey The name Tessera restores focus to on Back and Forward (`data-focus-key`).
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $label,
        public string $value,
        public string $focusKey,
    ) {
    }
}

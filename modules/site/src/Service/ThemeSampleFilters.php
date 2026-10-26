<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\KeyedComponent;
use App\Site\Form\ThemeQuery;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;

/**
 * The style guide's filter bar over the sample list: a search field and a status select, as asked, with the sort
 * kept in a hidden field; Tessera answers it in `content`.
 */
final readonly class ThemeSampleFilters
{
    /**
     * @param ThemeQuery $themeQuery What the list was asked for.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FilterBar The search field and the status select, as asked, keeping the sort.
     */
    public function filterBar(ThemeQuery $themeQuery): FilterBar
    {
        $options = [];

        foreach (ThemeSampleStock::STATUSES as $value => $label) {
            $options[] = new SelectOption($value, $label, $value === $themeQuery->status);
        }

        $formField = new FormField('filter-q', 'q', 'Search', 'search', $themeQuery->search, '', false, true);
        $selectField = new SelectField('filter-status', 'status', 'Status', 'Every status', $options, '', true);

        return new FilterBar('inventory-filters', '/theme', 'content', [
            new KeyedComponent('q', $formField),
            new KeyedComponent('status', $selectField),
            new KeyedComponent('sort', new HiddenInput('sort', $themeQuery->sort)),
        ], '/theme');
    }
}

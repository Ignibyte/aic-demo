<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Customers\Entity\Customer;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\SearchField\SearchField;
use LogicException;

/**
 * The customers list's filter bar: the search, showing what was asked. It is a plain GET form, which Tessera answers
 * with the results alone (`@customer-results`). The search submits the form on each pause in typing (#27): a Tessera
 * visit of the results, the query in the URL, replacing the list's history entry rather than adding one. The box sits
 * outside the results, so their answer leaves its focus and caret alone.
 */
final readonly class CustomerFilters
{
    /**
     * The search form's id.
     */
    public const string FORM = 'customers-filters';

    /**
     * The search box's focus key, which Tessera restores focus to on Back and Forward.
     */
    public const string SEARCH = 'customers-search';

    /**
     * @param string $search The search asked for.
     *
     * @throws LogicException When a key is malformed.
     *
     * @return FilterBar The search form, its box holding the search.
     */
    public function filterBar(string $search): FilterBar
    {
        $searchField = new SearchField('filter-q', 'q', 'Search name, email or phone', $search, self::SEARCH);
        $fields = [new KeyedComponent('q', $searchField)];

        return new FilterBar(self::FORM, Customer::PAGES, '@' . CustomerTable::ANCHOR, $fields, Customer::PAGES, 'replace');
    }
}

<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Inventory\Entity\ListingQuery;
use App\Storefront\Form\CarQuery;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\SelectField\SelectField;
use App\Theme\Components\SelectField\SelectOption;
use InvalidArgumentException;

/**
 * The public list's filter bar: the make and year selects, the price range and the sort, each showing what was asked.
 * It is a plain GET form, which Tessera answers with the results alone (`@car-results`); with JavaScript off it loads
 * the whole page.
 */
final readonly class CarFilters
{
    /**
     * The results' anchor: the list carries it on its results, and the filter form and the page links target it.
     */
    public const string ANCHOR = 'car-results';

    /**
     * @param list<string> $makes    The makes for sale.
     * @param list<string> $years    The model years for sale, the oldest first.
     * @param CarQuery     $carQuery What the list was asked for.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return FilterBar The filter bar.
     */
    public function filterBar(array $makes, array $years, CarQuery $carQuery): FilterBar
    {
        $listingQuery = $carQuery->listingQuery;
        $year = $listingQuery->year === null
            ? ''
            : (string) $listingQuery->year;
        $sort = $listingQuery->sort;
        $sorts = [
            new SelectOption(ListingQuery::PRICE, 'Price, low to high', $sort === ListingQuery::PRICE),
            new SelectOption(ListingQuery::PRICE_DESCENDING, 'Price, high to low', $sort === ListingQuery::PRICE_DESCENDING),
        ];
        $selectField = new SelectField('cars-make', 'make', 'Make', 'Every make', $this->options($makes, $listingQuery->make), '', true);

        return new FilterBar('car-filters', CarSlugs::PAGES, '@' . self::ANCHOR, [
            new KeyedComponent('make', $selectField),
            new KeyedComponent('year', new SelectField('cars-year', 'year', 'Year', 'Every year', $this->options($years, $year), '', true)),
            new KeyedComponent('min_price', $this->price('min', 'Price from', $listingQuery->minPrice)),
            new KeyedComponent('max_price', $this->price('max', 'Price to', $listingQuery->maxPrice)),
            new KeyedComponent('sort', new SelectField('cars-sort', 'sort', 'Sort', 'Newest first', $sorts, '', true)),
        ], CarSlugs::PAGES);
    }

    /**
     * @param list<string> $values The values to offer, each its own label.
     * @param string       $chosen The value asked for; empty for none.
     *
     * @return list<SelectOption> An option each, the asked one chosen.
     */
    private function options(array $values, string $chosen): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[] = new SelectOption($value, $value, $value === $chosen);
        }

        return $options;
    }

    /**
     * @param string   $bound `min` or `max`.
     * @param string   $label The label.
     * @param int|null $price The bound asked for; null for none.
     *
     * @return FormField The compact number field, `cars-{bound}-price` named `{bound}_price`.
     */
    private function price(string $bound, string $label, ?int $price): FormField
    {
        $value = $price === null
            ? ''
            : (string) $price;

        return new FormField('cars-' . $bound . '-price', $bound . '_price', $label, 'number', $value, '', false, true);
    }
}

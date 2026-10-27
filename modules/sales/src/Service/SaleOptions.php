<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Sales\Entity\Choice;
use App\Theme\Components\SelectField\SelectOption;

use function substr;

/**
 * Choices as the theme's select options, the chosen one marked; and the months that have sales as choices, each
 * named as staff say it (`October 2026`).
 */
final readonly class SaleOptions
{
    /**
     * The months' names, January first.
     */
    private const array MONTH_NAMES = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    /**
     * @param list<Choice> $choices The choices, in order.
     * @param string       $chosen  The chosen key; empty for none.
     *
     * @return list<SelectOption> An option for each.
     */
    public function options(array $choices, string $chosen): array
    {
        $options = [];

        foreach ($choices as $choice) {
            $options[] = new SelectOption($choice->key, $choice->label, $choice->key === $chosen);
        }

        return $options;
    }

    /**
     * @param list<string> $months Months, `YYYY-MM`.
     *
     * @return list<Choice> Each keyed by itself and named by its month and year.
     */
    public function months(array $months): array
    {
        $choices = [];

        foreach ($months as $month) {
            $name = self::MONTH_NAMES[(int) substr($month, 5, 2) - 1] ?? $month;
            $choices[] = new Choice($month, $name . ' ' . substr($month, 0, 4));
        }

        return $choices;
    }
}

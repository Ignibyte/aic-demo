<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * The reports the reports page shows and exports (#21), stored as its value in `report_exports.report` and posted as
 * the export form's `report`. Each is a list of figures: a label, a count and an amount, which its header names.
 */
enum ReportKind: string
{
    /** Each month the range touches: its sales and their revenue. */
    case Sales = 'sales';

    /** Each salesperson who sold in the range: their units and revenue. */
    case Salespeople = 'salespeople';

    /** Each make on the lot in the range: its vehicles and their average days on the lot. */
    case Inventory = 'inventory';

    /** Each kind of lead that came in: how many, and how many became a sale. */
    case Leads = 'leads';

    /**
     * @return string The report's title.
     */
    public function title(): string
    {
        // if/return rather than match: elephc cannot lower a match whose arms are enum cases yet (AIC #164).
        if ($this === self::Sales) {
            return 'Sales by month';
        }

        if ($this === self::Salespeople) {
            return 'Revenue and units by salesperson';
        }

        if ($this === self::Inventory) {
            return 'Inventory by make';
        }

        return 'Leads by kind';
    }

    /**
     * @return list<string> Its columns: what labels a figure, its count and its amount.
     */
    public function header(): array
    {
        if ($this === self::Sales) {
            return ['Month', 'Sales', 'Revenue'];
        }

        if ($this === self::Salespeople) {
            return ['Salesperson', 'Units', 'Revenue'];
        }

        if ($this === self::Inventory) {
            return ['Make', 'Vehicles', 'Average days on the lot'];
        }

        return ['Kind', 'Leads', 'Became a sale'];
    }

    /**
     * @return bool Whether its amount is money, in whole dollars.
     */
    public function money(): bool
    {
        return $this === self::Sales || $this === self::Salespeople;
    }
}

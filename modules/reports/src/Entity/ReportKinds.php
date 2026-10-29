<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * The reports in the order the page shows them (#21), and the one a stored or posted value names, found by a loop over
 * the cases rather than `ReportKind::tryFrom()`, as staff's `Roles` reads a role: elephc is not known to compile an
 * enum's `tryFrom()`, and no AIC ticket carries it yet.
 */
final readonly class ReportKinds
{
    /**
     * @return list<ReportKind> Every report, in the page's order.
     */
    public function all(): array
    {
        return [ReportKind::Sales, ReportKind::Salespeople, ReportKind::Inventory, ReportKind::Leads];
    }

    /**
     * @param string $value A report's stored value, such as `sales`.
     *
     * @return ReportKind|null The report it names; null for none.
     */
    public function named(string $value): ?ReportKind
    {
        foreach ($this->all() as $reportKind) {
            if ($reportKind->value === $value) {
                return $reportKind;
            }
        }

        return null;
    }
}

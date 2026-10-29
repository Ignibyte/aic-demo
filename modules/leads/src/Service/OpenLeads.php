<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Leads\Entity\Lead;
use App\Leads\Entity\Seller;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function count;
use function number_format;

/**
 * The open leads' table (#18): the day each came in (without its time, so both of gate:23's passes answer alike), its
 * kind linked to its page, the visitor, the car and the salesperson; and a summary of how many the filter keeps.
 */
final readonly class OpenLeads
{
    /**
     * @param list<Lead> $leads The leads shown.
     * @param int        $count How many the filter keeps in all.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable The leads, each linked to its page.
     */
    public function table(array $leads, int $count): DataTable
    {
        $rows = [];

        // Rows are keyed by their place, not the lead's id: a key prints in the markup, and gate:23's two passes give
        // their leads different ids.
        foreach ($leads as $index => $lead) {
            $rows[] = new KeyedComponent('lead-' . $index, $this->row($lead));
        }

        $empty = 'No open leads match. Clear the filter to see every one.';

        return new DataTable('leads-table', $this->summary(count($leads), $count), $empty, 'content', [
            new TableColumn('Received', '', 'none', '', false),
            new TableColumn('Kind', '', 'none', '', false),
            new TableColumn('Name', '', 'none', '', false),
            new TableColumn('Car', '', 'none', '', false),
            new TableColumn('Salesperson', '', 'none', '', false),
        ], $rows);
    }

    /**
     * @param Lead $lead A lead.
     *
     * @return TableRow Its row.
     */
    private function row(Lead $lead): TableRow
    {
        $seller = $lead->seller;

        return new TableRow([
            new TableCell($lead->receivedOn, '', false, false, null),
            new TableCell($lead->leadDetails->leadKind->label(), $lead->path(), false, false, null),
            new TableCell($lead->leadDetails->name, '', false, false, null),
            new TableCell($lead->leadCar->title, '', false, false, null),
            new TableCell($seller instanceof Seller ? $seller->name : 'Unassigned', '', false, false, null),
        ]);
    }

    /**
     * @param int $shown How many the table shows.
     * @param int $count How many the filter keeps.
     *
     * @return string `12 open leads`, or `73 open leads, the newest 50 shown`.
     */
    private function summary(int $shown, int $count): string
    {
        $summary = number_format($count) . ($count === 1 ? ' open lead' : ' open leads');

        return $count > $shown
            ? $summary . ', the newest ' . $shown . ' shown'
            : $summary;
    }
}

<?php

declare(strict_types=1);

namespace App\Holds\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Database\Contracts\QueryException;
use App\Holds\Entity\Hold;
use App\Holds\Repository\HoldRepository;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Panel\Panel;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function count;

/**
 * The active holds, newest first (#20): the notice a placement left, a link to the form, and the table: the car linked
 * to its inventory page, the customer, who placed it, the day it was placed, when it expires (UTC, to the minute) and
 * the note.
 */
final readonly class HoldList
{
    /**
     * @param HoldRepository $holdRepository The holds.
     * @param StaffNotices   $staffNotices   A placement's notice.
     */
    public function __construct(
        private HoldRepository $holdRepository,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its session.
     *
     * @throws QueryException When the holds cannot be read.
     * @throws LogicException When the request carries no session, or a key or a type is malformed.
     *
     * @return Panel The list's panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'holds-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('add', new Link('Put a vehicle on hold', Hold::PAGES . '/new'));
        $body[] = new KeyedComponent('table', $this->table($this->holdRepository->active()));
        $description = 'Vehicles kept for a customer, each for 48 hours from when it was placed; times are UTC';

        return new Panel('holds', 'Vehicles on hold', $description, true, $body);
    }

    /**
     * @param list<Hold> $holds The active holds.
     *
     * @throws LogicException When a key is malformed.
     *
     * @return DataTable The holds.
     */
    private function table(array $holds): DataTable
    {
        $rows = [];

        // Rows are keyed by their place, not the hold's id: a key prints in the markup, and gate:23's two passes give
        // their holds different ids.
        foreach ($holds as $index => $hold) {
            $rows[] = new KeyedComponent('hold-' . $index, $this->row($hold));
        }

        $count = count($holds);
        $summary = $count === 1
            ? '1 vehicle on hold'
            : $count . ' vehicles on hold';

        return new DataTable('holds-table', $summary, 'No vehicle is on hold.', 'content', [
            new TableColumn('Car', '', 'none', '', false),
            new TableColumn('Customer', '', 'none', '', false),
            new TableColumn('Placed by', '', 'none', '', false),
            new TableColumn('Placed', '', 'none', '', false),
            new TableColumn('Expires', '', 'none', '', false),
            new TableColumn('Note', '', 'none', '', false),
        ], $rows);
    }

    /**
     * @param Hold $hold A hold.
     *
     * @return TableRow Its row.
     */
    private function row(Hold $hold): TableRow
    {
        return new TableRow([
            new TableCell($hold->title, $hold->carPath(), false, false, null),
            new TableCell($hold->customerName === '' ? 'No longer on file' : $hold->customerName, '', false, false, null),
            new TableCell($hold->placerName === '' ? 'No longer on staff' : $hold->placerName, '', false, false, null),
            new TableCell($hold->placedOn, '', false, false, null),
            new TableCell($hold->expires, '', false, false, null),
            new TableCell($hold->note, '', false, false, null),
        ]);
    }
}

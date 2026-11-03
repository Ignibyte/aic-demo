<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\BulkEdit;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Repository\VehicleRepository;
use App\Inventory\Service\InventoryQueries;
use App\Inventory\Service\InventoryResults;
use App\Inventory\Service\InventoryTable;
use App\Staff\Service\StaffNotices;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function count;
use function implode;
use function in_array;
use function intdiv;

/**
 * A bulk edit's answers (#29). Each but a plain success is the inventory page, its list's panel holding the action bar,
 * the totals line and the ticked rows alone, each where the list puts it, so a Tessera action naming those rows and the
 * totals is answered with them and nothing else, and a plain post gets the page whole. The totals line counts the list
 * the edit came from, as it is now. A plain success goes back to that list with a notice.
 */
final readonly class VehicleBulkPages
{
    /**
     * The list's vehicles, counted and counted by status.
     */
    private VehicleRepository $vehicleRepository;

    /**
     * @param VehicleRowPages  $vehicleRowPages  The inventory page holding the list's results where the list puts them.
     * @param EntityStore      $entityStore      The entity store, which the list's vehicles are read through; built on
     *                                           here, so the generated handler builds no more classes than PHPMD allows.
     * @param VehicleRowForm   $vehicleRowForm   Each vehicle's row: ticked, refused or gone.
     * @param InventoryTable   $inventoryTable   The bar, the totals line and the results of the ticked rows.
     * @param InventoryQueries $inventoryQueries Reads the list's query the edit came from.
     * @param StaffNotices     $staffNotices     Leaves a plain success's notice for the list.
     */
    public function __construct(
        private VehicleRowPages $vehicleRowPages,
        EntityStore $entityStore,
        private VehicleRowForm $vehicleRowForm,
        private InventoryTable $inventoryTable = new InventoryTable(),
        private InventoryQueries $inventoryQueries = new InventoryQueries(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
        $this->vehicleRepository = new VehicleRepository($entityStore);
    }

    /**
     * @param ServerRequestInterface $serverRequest A delete posted without its confirmation, with its session.
     * @param BulkEdit               $bulkEdit      What it asks.
     * @param list<Vehicle>          $vehicles      The vehicles that have its VINs.
     *
     * @throws RuntimeException When the list cannot be read (`QueryException`), or a stored status is no status.
     * @throws LogicException   When the request has no session, a key does not fit, or a region does not fit the theme.
     *
     * @return PageResponse 200: the ticked rows, still ticked, and the bar asking first, the delete chosen.
     */
    public function confirm(ServerRequestInterface $serverRequest, BulkEdit $bulkEdit, array $vehicles): PageResponse
    {
        return $this->page($serverRequest, $bulkEdit, $this->rows($bulkEdit, $vehicles, [], true), $bulkEdit->page, '', 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A bulk edit refused, with its session.
     * @param BulkEdit               $bulkEdit      What it asked.
     * @param list<Vehicle>          $vehicles      The vehicles that have its VINs, as they stand.
     * @param array<string, string>  $refusals      Why each refused VIN was refused; empty when the post itself is.
     *
     * @throws RuntimeException When the list cannot be read (`QueryException`), or a stored status is no status.
     * @throws LogicException   When the request has no session, a key does not fit, or a region does not fit the theme.
     *
     * @return PageResponse 422: the ticked rows, still ticked, each refused one set apart with why, and the totals line
     *                      saying nothing was changed and naming each refused row by VIN.
     */
    public function refused(
        ServerRequestInterface $serverRequest,
        BulkEdit $bulkEdit,
        array $vehicles,
        array $refusals,
    ): PageResponse {
        $outcome = $bulkEdit->problem();

        if ($outcome === '') {
            $reasons = [];

            foreach ($refusals as $vin => $refusal) {
                $reasons[] = 'VIN ' . $vin . ': ' . $refusal;
            }

            $outcome = 'Nothing was changed. ' . implode(' ', $reasons);
        }

        return $this->page($serverRequest, $bulkEdit, $this->rows($bulkEdit, $vehicles, $refusals, true), $bulkEdit->page, $outcome, 422);
    }

    /**
     * @param ServerRequestInterface $serverRequest A bulk edit written, with its session.
     * @param BulkEdit               $bulkEdit      What it asked.
     * @param list<Vehicle>          $before        Its vehicles before the change.
     * @param list<Vehicle>          $after         Its vehicles after it; none after a delete.
     *
     * @throws RuntimeException When the list cannot be read (`QueryException`), or a stored status is no status.
     * @throws LogicException   When the request has no session, a key does not fit, or a region does not fit the theme.
     *
     * @return PageResponse For Tessera, 200: the rows as they are now (a deleted one not at all, so it leaves the page)
     *                      and the totals line saying what was done; for a plain post, 303 back to the list with that as
     *                      a notice.
     */
    public function applied(
        ServerRequestInterface $serverRequest,
        BulkEdit $bulkEdit,
        array $before,
        array $after,
    ): PageResponse {
        $outcome = $bulkEdit->done(count($before));

        if (!$bulkEdit->enhanced) {
            $this->staffNotices->keep($serverRequest, $outcome);

            return new PageRedirect($bulkEdit->back);
        }

        return $bulkEdit->deletes()
            ? $this->deleted($serverRequest, $bulkEdit, $before, $outcome)
            : $this->page($serverRequest, $bulkEdit, $this->rows($bulkEdit, $after, [], false), $bulkEdit->page, $outcome, 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A bulk delete written, from Tessera, with its session.
     * @param BulkEdit               $bulkEdit      What it asked.
     * @param list<Vehicle>          $before        The vehicles it deleted, as they were.
     * @param string                 $outcome       What the totals line says was done.
     *
     * @throws RuntimeException When the list cannot be read (`QueryException`), or a stored status is no status.
     * @throws LogicException   When the request has no session, a key does not fit, or a region does not fit the theme.
     *
     * @return PageResponse 200: the bar and the totals line, the page's VINs without theirs, and no row for a deleted
     *                      vehicle, so AIC answers each deleted row's target removed and it leaves the page (AIC #247).
     */
    private function deleted(
        ServerRequestInterface $serverRequest,
        BulkEdit $bulkEdit,
        array $before,
        string $outcome,
    ): PageResponse {
        $deleted = [];

        foreach ($before as $vehicle) {
            $deleted[] = $vehicle->vin;
        }

        $page = [];

        foreach ($bulkEdit->page as $vin) {
            if (in_array($vin, $deleted, true)) {
                continue;
            }

            $page[] = $vin;
        }

        return $this->page($serverRequest, $bulkEdit, [], $page, $outcome, 200);
    }

    /**
     * @param BulkEdit              $bulkEdit A bulk edit.
     * @param list<Vehicle>         $vehicles The vehicles that have its VINs.
     * @param array<string, string> $refusals Why each refused VIN was refused.
     * @param bool                  $checked  Whether the rows' boxes arrive ticked.
     *
     * @throws LogicException When a key does not fit, or a vehicle's path cannot be sealed.
     *
     * @return list<KeyedComponent> A row per ticked VIN: its vehicle's, or gone when no vehicle has it; none for a post
     *                              of more VINs than a page holds.
     */
    private function rows(BulkEdit $bulkEdit, array $vehicles, array $refusals, bool $checked): array
    {
        $rows = [];
        $found = [];

        // A post of more VINs than a page holds is read no further, so it shows no rows rather than rows gone.
        if (count($bulkEdit->vins) > BulkEdit::MOST) {
            return $rows;
        }

        foreach ($vehicles as $vehicle) {
            $rows[] = $this->vehicleRowForm->ticked($vehicle, $checked, $refusals[$vehicle->vin] ?? '');
            $found[] = $vehicle->vin;
        }

        foreach ($bulkEdit->vins as $vin) {
            if (in_array($vin, $found, true)) {
                continue;
            }

            $rows[] = $this->vehicleRowForm->gone($vin);
        }

        return $rows;
    }

    /**
     * @param ServerRequestInterface $serverRequest The bulk edit's request, with its session.
     * @param BulkEdit               $bulkEdit      What it asked.
     * @param list<KeyedComponent>   $rows          The rows the answer holds.
     * @param list<string>           $page          The VINs of the rows the page still shows.
     * @param string                 $outcome       What the totals line says was done or refused; empty for nothing.
     * @param int                    $status        200, or 422 for a refusal.
     *
     * @throws RuntimeException When the list cannot be read (`QueryException`), or a stored status is no status.
     * @throws LogicException   When a key does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The inventory page, its list's panel holding the bar (asking first for a delete posted
     *                      without its confirmation), the totals line of the list the edit came from, and the rows.
     */
    private function page(
        ServerRequestInterface $serverRequest,
        BulkEdit $bulkEdit,
        array $rows,
        array $page,
        string $outcome,
        int $status,
    ): PageResponse {
        $listed = $serverRequest->withQueryParams($bulkEdit->query);
        $inventoryQuery = $this->inventoryQueries->read($listed, $this->vehicleRepository->makes());
        $count = $this->vehicleRepository->count($inventoryQuery);
        $pages = intdiv($count + InventoryResults::PAGE - 1, InventoryResults::PAGE);
        $statuses = $count === 0
            ? []
            : $this->vehicleRepository->statuses($inventoryQuery);
        $confirming = $bulkEdit->deletes() && !$bulkEdit->confirmed && $status === 200;
        $bulkBar = $this->inventoryTable->bar($bulkEdit->guardFields, $bulkEdit->back, $confirming, count($bulkEdit->vins));
        $number = $inventoryQuery->page <= $pages
            ? $inventoryQuery->page
            : 1;
        $tableTotals = $this->inventoryTable->totals($count, $number, $pages, $statuses, $outcome, $status === 422, $page);
        $touched = $this->inventoryTable->touched($rows, $bulkBar, $tableTotals);

        return $this->vehicleRowPages->frame($touched, $status);
    }
}

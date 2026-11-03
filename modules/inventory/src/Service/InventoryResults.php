<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use App\Inventory\Form\InventoryQuery;
use App\Inventory\Form\VehicleRowForm;
use App\Inventory\Repository\VehicleRepository;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function intdiv;

/**
 * The inventory list's results as a source (#14, AIC #192): it holds the repository and the query the list was asked
 * for, and reads only in `build()`, which the render pass calls when it renders the results. A Tessera visit that
 * names the results alone (`@inventory-results`) builds them and nothing else's data. A page past the last shows the
 * first. Each row is `VehicleRowForm`'s, the shape a row's own answer has (#28). The action bar goes back to the list as
 * it is shown, and the totals line counts the matching vehicles by status and holds the page's VINs (#29).
 */
final readonly class InventoryResults implements ComponentSource
{
    /**
     * The vehicles on a page.
     */
    public const int PAGE = 15;

    /**
     * @param VehicleRepository      $vehicleRepository The vehicles.
     * @param InventoryTable         $inventoryTable    The table and the pages.
     * @param InventoryQuery         $inventoryQuery    What the list was asked for.
     * @param VehicleRowForm         $vehicleRowForm    Each vehicle's row.
     * @param ServerRequestInterface $serverRequest     The list's request, with its session, for the guard fields the
     *                                                  action bar posts (#29, AIC #246).
     * @param InventoryLinks         $inventoryLinks    Writes the list's link the bar goes back to.
     * @param FormTokens             $formTokens        Gives the visitor's guard fields: their form token and the honeypot.
     */
    public function __construct(
        private VehicleRepository $vehicleRepository,
        private InventoryTable $inventoryTable,
        private InventoryQuery $inventoryQuery,
        private VehicleRowForm $vehicleRowForm,
        private ServerRequestInterface $serverRequest,
        private InventoryLinks $inventoryLinks = new InventoryLinks(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @throws QueryException           When the vehicles cannot be read.
     * @throws UnexpectedValueException When a stored status is no status.
     * @throws LogicException           When a key is malformed; or a vehicle's path cannot be sealed as its row's props.
     *
     * @return Component The sort the filter form sends, the action bar, the totals line with the counts by status, the
     *                   page's table and, past one page, the pages.
     */
    #[Override]
    public function build(): Component
    {
        $count = $this->vehicleRepository->count($this->inventoryQuery);
        $pages = intdiv($count + self::PAGE - 1, self::PAGE);
        $page = $this->inventoryQuery->page <= $pages
            ? $this->inventoryQuery->page
            : 1;
        $rows = [];
        $vins = [];

        foreach ($this->vehicleRepository->page($this->inventoryQuery, self::PAGE, ($page - 1) * self::PAGE) as $vehicle) {
            $rows[] = $this->vehicleRowForm->shown($vehicle);
            $vins[] = $vehicle->vin;
        }

        $inventoryQuery = $this->inventoryQuery;
        $back = $this->inventoryLinks->href($inventoryQuery, $inventoryQuery->sort, $inventoryQuery->descending, $page);
        $statuses = $count === 0
            ? []
            : $this->vehicleRepository->statuses($inventoryQuery);
        $bulkBar = $this->inventoryTable->bar($this->formTokens->guard($this->serverRequest), $back);
        $tableTotals = $this->inventoryTable->totals($count, $page, $pages, $statuses, '', false, $vins);

        return $this->inventoryTable->results($inventoryQuery, $rows, $page, $pages, $bulkBar, $tableTotals);
    }
}

<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Service\InventoryList;
use App\Inventory\Service\InventoryTable;
use App\Inventory\Service\SoldVehicles;
use App\Inventory\Service\VehicleRecords;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\Panel\Panel;
use LogicException;

/**
 * The page a vehicle's row answers with (#28): the inventory page, its list's panel holding that row alone under the
 * keys the list gives it, so the row sits at the path it has in the list. A Tessera request from the row finds its
 * target there and AIC answers with the row's fragment alone; a plain request gets the page whole. It also frames the
 * vehicle pages' 404 and 400 pages, and builds the vehicle form's checks, which a row's save runs.
 */
final readonly class VehicleRowPages
{
    /**
     * The staff pages' document.
     */
    private StaffPages $staffPages;

    /**
     * @param PageDocuments  $pageDocuments  The theme's documents, which the staff pages' document is built on.
     * @param InventoryTable $inventoryTable The list's results, of one row.
     */
    public function __construct(
        PageDocuments $pageDocuments,
        private InventoryTable $inventoryTable = new InventoryTable(),
    ) {
        $this->staffPages = new StaffPages($pageDocuments);
    }

    /**
     * @param KeyedComponent $keyedComponent The vehicle's row, shown or as a form.
     * @param int            $status         200, or 422 for a refused save.
     *
     * @throws LogicException When a key does not fit; or a region does not fit the theme.
     *
     * @return PageDocument The inventory page, its list's panel holding that row alone where the list puts it.
     */
    public function page(KeyedComponent $keyedComponent, int $status): PageDocument
    {
        $results = new KeyedComponent(InventoryList::RESULTS, $this->inventoryTable->alone($keyedComponent), '', InventoryTable::ANCHOR);
        $panel = new Panel(InventoryList::KEY, InventoryList::TITLE, InventoryList::LINE, true, [$results]);

        return $this->staffPages->page('Inventory', [new KeyedComponent(InventoryList::KEY, $panel)], $status);
    }

    /**
     * @param EntityStore $entityStore The entity store, through which the vehicle is read.
     *
     * @return VehicleRecords The route's vehicle, its repository, and the vehicle pages' 404 and 400 pages in the staff
     *                        document.
     */
    public function records(EntityStore $entityStore): VehicleRecords
    {
        return new VehicleRecords($entityStore, new StaffPanels($this->staffPages, 'vehicle'));
    }

    /**
     * @param VehicleRecords $vehicleRecords The route's vehicle and its repository.
     * @param EntityStore    $entityStore    The entity store, through which a sale is asked for.
     * @param list<object>   $vehicleSales   The `VehicleSales` plugins, which know a vehicle's recorded sale.
     *
     * @return VehicleChecks The vehicle form's checks.
     */
    public function checks(VehicleRecords $vehicleRecords, EntityStore $entityStore, array $vehicleSales): VehicleChecks
    {
        return new VehicleChecks($vehicleRecords->repository(), new SoldVehicles($entityStore, $vehicleSales));
    }
}

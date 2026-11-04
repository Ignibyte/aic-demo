<?php

declare(strict_types=1);

namespace App\Panels\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageUnchanged;
use AIC\Entity\Application\EntityStore;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehiclePanel;
use App\Inventory\Service\VehicleRecords;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * The one flow of a vehicle panel's route (#31), `GET /app/inventory/{vin}/<key>`: the route's vehicle through
 * inventory's `VehicleRecords`, which checks the VIN as a VIN before any query, else the vehicle pages' 404; the
 * panel's props by its anchor, refused with 400 when they were altered; then its version, and 204 for a refresh of the
 * panel alone whose props carry it, before the panel is built (`DashboardPoll`'s order); otherwise the vehicle pages'
 * frame holding the panel alone, at the path the vehicle's page gives it, which AIC answers with the panel's fragment
 * for Tessera and whole for a plain request.
 */
final readonly class PanelPages
{
    /**
     * The route's vehicle, the vehicle pages' 404 and their frame.
     */
    private VehicleRecords $vehicleRecords;

    /**
     * @param EntityStore $entityStore The entity store.
     * @param StaffSeals  $staffSeals  Opens a refresh's props and seals the panel's version.
     * @param StaffPages  $staffPages  The staff pages' document.
     * @param PanelParts  $panelParts  Builds the arrived panel.
     */
    public function __construct(
        private EntityStore $entityStore,
        private StaffSeals $staffSeals,
        StaffPages $staffPages,
        private PanelParts $panelParts = new PanelParts(),
    ) {
        $this->vehicleRecords = new VehicleRecords($entityStore, new StaffPanels($staffPages, 'vehicle'));
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for one panel's route, from a signed-in member of staff.
     * @param VehiclePanel           $vehiclePanel  The panel.
     *
     * @throws RuntimeException When the vehicle or the panel's data cannot be read.
     * @throws LogicException   When a key, an anchor, an entity type or a status does not fit, or the props cannot be
     *                          sealed.
     *
     * @return PageResponse The frame holding the panel; 204 when nothing changed; 400 or 404.
     */
    public function answer(ServerRequestInterface $serverRequest, VehiclePanel $vehiclePanel): PageResponse
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        $anchor = $vehiclePanel->anchor();

        if (!$this->staffSeals->propsHold($serverRequest, $anchor)) {
            return $this->refused($vehicle);
        }

        $version = $vehiclePanel->version($this->entityStore, $vehicle);

        if ($this->staffSeals->unchanged($serverRequest, $anchor, $version)) {
            return new PageUnchanged();
        }

        $keyedComponent = $this->panelParts->arrived($vehiclePanel, $this->entityStore, $this->staffSeals, $vehicle, $version);
        $staffPanels = $this->vehicleRecords->panels();

        return $staffPanels->page($vehicle->title(), 'Vehicle record', 'What the lot keeps on this vehicle.', [], 200, [$keyedComponent]);
    }

    /**
     * @param Vehicle $vehicle The route's vehicle.
     *
     * @throws LogicException When the status does not fit the theme.
     *
     * @return PageResponse The page for a refresh whose props were altered on the way, 400, nothing built.
     */
    private function refused(Vehicle $vehicle): PageResponse
    {
        $message = 'This panel could not be refreshed, so nothing was shown. Open the vehicle again.';
        $staffPanels = $this->vehicleRecords->panels();

        return $staffPanels->warning('Panel refused', $message, 'vehicle-panel-refused', 'Back to the vehicle', $vehicle->path(), 400);
    }
}

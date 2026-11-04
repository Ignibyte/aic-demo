<?php

declare(strict_types=1);

namespace App\Downloads\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Entity\Application\EntityStore;
use AIC\Routing\Domain\RouteNotFoundException;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Service\VehicleRecords;
use App\Photos\Service\PhotoBundles;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use App\Staff\Service\StaffSession;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * The photo bundle's download (#36): the route's VIN read through inventory's `VehicleRecords`, which checks it as a
 * VIN before any query, the signed-in member checked, and photos' `PhotoBundles` asked for the vehicle's ZIP. Any
 * member of staff may have it, whatever their role: it holds the photos the vehicle's page shows them.
 */
final readonly class PhotoBundleDownloads
{
    /**
     * The route's vehicle and its id.
     */
    private VehicleRecords $vehicleRecords;

    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param StaffPages   $staffPages   The staff pages' document, which inventory's records take.
     * @param PhotoBundles $photoBundles The vehicles' photo bundles.
     * @param StaffSession $staffSession Who is signed in.
     */
    public function __construct(
        EntityStore $entityStore,
        StaffPages $staffPages,
        private PhotoBundles $photoBundles,
        private StaffSession $staffSession = new StaffSession(),
    ) {
        $this->vehicleRecords = new VehicleRecords($entityStore, new StaffPanels($staffPages, 'vehicle'));
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's ZIP, with its session and the route's VIN.
     *
     * @throws LogicException   When the request carries no session, or an entity type is malformed.
     * @throws RuntimeException When the vehicle cannot be read or its stored status is no status, or there is no member,
     *                          no such vehicle or no bundle (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return FileDownload The ZIP, as AIC's validated, ranged download.
     */
    public function download(ServerRequestInterface $serverRequest): FileDownload
    {
        $vehicle = $this->staffSession->member($serverRequest) === null
            ? null
            : $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return $this->photoBundles->download($serverRequest, $this->vehicleRecords->vehicleId($serverRequest), $vehicle->vin);
    }
}

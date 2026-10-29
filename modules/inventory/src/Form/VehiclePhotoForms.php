<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Service\VehicleCards;
use App\Inventory\Service\VehicleRecords;
use App\Inventory\Service\VehicleServices;
use App\Photos\Service\VehiclePhotos;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffRequests;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function preg_match;

/**
 * A vehicle's photos (#16) through its routes: adding one from the page's upload form, deleting one by its position,
 * and serving one. Only a sales manager or an admin adds or deletes (403 otherwise); a refused upload answers the
 * vehicle's page as 422 with the reason, and a done change goes back to it (303) with a notice. It takes the vehicle's
 * records and cards from `VehicleServices`, so the photo routes' generated part stays one short method.
 */
final readonly class VehiclePhotoForms
{
    /**
     * What a salesperson's post is told.
     */
    private const string FORBIDDEN = "Only a sales manager or an admin can change a vehicle's photos.";

    /**
     * What a delete form for a position with no photo is told.
     */
    private const string MISSING = 'This vehicle has no photo there. It may have been deleted.';

    /**
     * What a delete form is told when its envelope does not seal the photo now at its position.
     */
    private const string STALE = 'This form was issued for a photo that has moved or gone, or it changed on the way, so nothing was'
        . ' deleted. Open the vehicle again.';

    /**
     * The route's vehicle, its id, and the 404 and warning pages.
     */
    private VehicleRecords $vehicleRecords;

    /**
     * The vehicle's page, with a refusal.
     */
    private VehicleCards $vehicleCards;

    /**
     * @param StaffSeals      $staffSeals      Checks a delete form's envelope.
     * @param VehiclePhotos   $vehiclePhotos   The vehicle's photos.
     * @param VehicleServices $vehicleServices The vehicle's records and cards.
     * @param StaffNotices    $staffNotices    Leaves a change's notice for the vehicle's page.
     * @param StaffRequests   $staffRequests   Reads the route's position.
     */
    public function __construct(
        private StaffSeals $staffSeals,
        private VehiclePhotos $vehiclePhotos,
        VehicleServices $vehicleServices,
        private StaffNotices $staffNotices = new StaffNotices(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
        $this->vehicleRecords = $vehicleServices->vehicleRecords();
        $this->vehicleCards = $vehicleServices->vehicleCards();
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted upload form, with its session.
     *
     * @throws LogicException   When the request has no session, or a key does not fit the theme.
     * @throws RuntimeException When the vehicle or its photos cannot be read or written, a stored status or the
     *                          member's role is not one, or the upload cannot be read or moved.
     *
     * @return PageResponse 303 to the vehicle's page; the page as 422 with the reason; 403; or 404.
     */
    public function add(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->changeable($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->refusal($serverRequest);
        }

        $refusal = $this->vehiclePhotos->add($serverRequest, $this->vehicleRecords->vehicleId($serverRequest));

        if ($refusal !== '') {
            return $this->vehicleCards->page($serverRequest, $refusal, 422);
        }

        $this->staffNotices->keep($serverRequest, 'Added a photo of the ' . $vehicle->title() . '.');

        return new PageRedirect($vehicle->path());
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted delete form, with its session.
     *
     * @throws LogicException   When the request has no session, or a status does not fit the theme.
     * @throws RuntimeException When the vehicle or its photos cannot be read or deleted, or a stored status or the
     *                          member's role is not one.
     *
     * @return PageResponse 303 to the vehicle's page; 400 for an envelope that does not seal the photo now at that
     *                      position; 403; or 404 for no vehicle or no photo there.
     */
    public function remove(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->changeable($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->refusal($serverRequest);
        }

        $vehicleId = $this->vehicleRecords->vehicleId($serverRequest);
        $vehiclePhoto = $this->vehiclePhotos->photo($vehicleId, $this->position($serverRequest));

        if ($vehiclePhoto === null) {
            return $this->vehicleRecords->photoWarning($vehicle, 'Photo not found', self::MISSING, 404);
        }

        if (!$this->staffSeals->holds($serverRequest, $vehiclePhoto->sealed($vehicle->path()))) {
            return $this->vehicleRecords->photoWarning($vehicle, 'Form refused', self::STALE, 400);
        }

        if ($this->vehiclePhotos->remove($vehicleId, $vehiclePhoto)) {
            $this->staffNotices->keep($serverRequest, 'Deleted photo ' . $vehiclePhoto->position . ' of the ' . $vehicle->title() . '.');
        }

        return new PageRedirect($vehicle->path());
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a photo, with its range and conditions.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicle or its photos cannot be read, or there is no such photo, which AIC answers
     *                          404.
     *
     * @return StreamResponse The photo, inline.
     */
    public function photo(ServerRequestInterface $serverRequest): StreamResponse
    {
        $vehicleId = $this->vehicleRecords->vehicleId($serverRequest);

        return $this->vehiclePhotos->download($serverRequest, $vehicleId, $this->position($serverRequest));
    }

    /**
     * @param ServerRequestInterface $serverRequest A post to one of a vehicle's photo routes, with its session.
     *
     * @throws LogicException   When the request has no session, or an entity type is malformed.
     * @throws RuntimeException When the vehicle cannot be read, or a stored status or the member's role is not one.
     *
     * @return Vehicle|null The route's vehicle when the signed-in member may change its photos; null otherwise.
     */
    private function changeable(ServerRequestInterface $serverRequest): ?Vehicle
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        return $vehicle instanceof Vehicle && $this->vehiclePhotos->mayChange($serverRequest)
            ? $vehicle
            : null;
    }

    /**
     * @param ServerRequestInterface $serverRequest A post `changeable()` refused.
     *
     * @throws LogicException   When a status does not fit the theme, or an entity type is malformed.
     * @throws RuntimeException When the vehicle cannot be read, or its stored status is not one.
     *
     * @return PageResponse 403 when the route's vehicle is there, for a member who may not change its photos; else 404.
     */
    private function refusal(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        return $vehicle instanceof Vehicle
            ? $this->vehicleRecords->photoWarning($vehicle, 'Not allowed', self::FORBIDDEN, 403)
            : $this->vehicleRecords->missing();
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a photo's route.
     *
     * @return int Its `position`, from 1; 0 when it is not a whole number from 1 to 999.
     */
    private function position(ServerRequestInterface $serverRequest): int
    {
        $position = $this->staffRequests->attribute($serverRequest, 'position');

        return preg_match('/^[1-9][0-9]{0,2}$/', $position) === 1
            ? (int) $position
            : 0;
    }
}

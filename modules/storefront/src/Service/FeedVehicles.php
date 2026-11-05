<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Database\Contracts\QueryException;
use App\Inventory\Entity\ListedVehicle;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Photos\Entity\VehiclePhoto;
use App\Staff\Service\StaffRequests;
use App\Storefront\Entity\FeedVehicle;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function strtolower;

/**
 * The feed for listing partners: the vehicles for sale, in the public list's first order, or those of the make
 * `?make=` names, matched without regard to case. A make not for sale, or a list of makes, gives no vehicle rather
 * than every one. The list's photos are read at once, not once a vehicle (#25).
 */
final readonly class FeedVehicles
{
    /**
     * @param ListedVehicleRepository $listedVehicleRepository The vehicles for sale.
     * @param CarFinder               $carFinder               Their photos.
     * @param CarSlugs                $carSlugs                Each one's slug and page.
     * @param StaffRequests           $staffRequests           Reads the `make` parameter.
     * @param CarFigures              $carFigures              Writes the price as a decimal string.
     */
    public function __construct(
        private ListedVehicleRepository $listedVehicleRepository,
        private CarFinder $carFinder,
        private CarSlugs $carSlugs = new CarSlugs(),
        private StaffRequests $staffRequests = new StaffRequests(),
        private CarFigures $carFigures = new CarFigures(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The feed's request.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return FeedBody The vehicles it asks for.
     */
    public function list(ServerRequestInterface $serverRequest): FeedBody
    {
        $asked = $this->asked($serverRequest);
        $make = $asked === null
            ? null
            : $this->make($asked);

        if ($make === null) {
            return new FeedBody([]);
        }

        $listedVehicles = $this->listedVehicleRepository->all($make);
        $photos = $this->carFinder->photosOf($listedVehicles);
        $vehicles = [];

        foreach ($listedVehicles as $listedVehicle) {
            $vehicles[] = $this->entry($listedVehicle, $photos[$listedVehicle->id] ?? []);
        }

        return new FeedBody($vehicles);
    }

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @throws LogicException   When the photos' entity type is malformed.
     * @throws RuntimeException When its photos cannot be read.
     *
     * @return FeedVehicle It, as the feed gives it.
     */
    public function vehicle(ListedVehicle $listedVehicle): FeedVehicle
    {
        return $this->entry($listedVehicle, $this->carFinder->photosOf([$listedVehicle])[$listedVehicle->id] ?? []);
    }

    /**
     * @return FeedBody The answer for a vehicle not for sale.
     */
    public function missing(): FeedBody
    {
        return new FeedBody([], 'No vehicle for sale has that slug.');
    }

    /**
     * @param ListedVehicle      $listedVehicle A car for sale.
     * @param list<VehiclePhoto> $vehiclePhotos Its photos, the first added first.
     *
     * @return FeedVehicle It, as the feed gives it.
     */
    private function entry(ListedVehicle $listedVehicle, array $vehiclePhotos): FeedVehicle
    {
        $path = $this->carSlugs->path($listedVehicle);
        $photos = [];

        foreach ($vehiclePhotos as $vehiclePhoto) {
            $photos[] = $vehiclePhoto->source();
        }

        return new FeedVehicle(
            $this->carSlugs->slug($listedVehicle),
            $path,
            $listedVehicle->year,
            $listedVehicle->make,
            $listedVehicle->model,
            $listedVehicle->trim,
            $listedVehicle->bodyStyle,
            $listedVehicle->mileage,
            $listedVehicle->exteriorColour,
            $this->carFigures->decimal($listedVehicle->price),
            $photos,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest The feed's request.
     *
     * @return string|null Its `make` parameter, empty when it is absent; null when it is a list (`make[]=…`).
     */
    private function asked(ServerRequestInterface $serverRequest): ?string
    {
        $asked = $this->staffRequests->query($serverRequest, 'make');

        return $asked === '' && ($serverRequest->getQueryParams()['make'] ?? '') !== ''
            ? null
            : $asked;
    }

    /**
     * @param string $asked A make asked for; empty for none.
     *
     * @throws QueryException           When the makes cannot be read.
     * @throws InvalidArgumentException When their entity type is malformed.
     *
     * @return string|null The make for sale it names, ignoring case; empty for every make when it is empty; null for
     *                     a make not for sale.
     */
    private function make(string $asked): ?string
    {
        if ($asked === '') {
            return '';
        }

        foreach ($this->listedVehicleRepository->makes() as $make) {
            if (strtolower($make) === strtolower($asked)) {
                return $make;
            }
        }

        return null;
    }
}

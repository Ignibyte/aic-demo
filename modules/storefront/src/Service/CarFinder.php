<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Database\Contracts\QueryException;
use App\Brochures\Service\Brochures;
use App\Inventory\Entity\ListedVehicle;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Photos\Entity\VehiclePhoto;
use App\Photos\Service\VehiclePhotos;
use App\Staff\Service\StaffRequests;
use App\Theme\Components\BrochureLinks\BrochureLinks;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use UnexpectedValueException;

use function preg_match;

/**
 * What a public request names by its route: the car for sale its `slug` names, that car's photo at its `position`, and
 * its brochure (#44), inline or as `{slug}-brochure.html`, with the two links its page shows once the brochure is kept.
 * A car is found by the year and key its slug holds, and only when its own slug is the one asked for, so a slug with
 * the right key and another make answers nothing. It also reads the photos of the cars a list shows, at once (#25).
 */
final readonly class CarFinder
{
    /**
     * @param ListedVehicleRepository $listedVehicleRepository The vehicles for sale.
     * @param VehiclePhotos           $vehiclePhotos           Their photos.
     * @param Brochures               $brochures               Their kept brochures (#44).
     * @param CarSlugs                $carSlugs                Writes and reads slugs.
     * @param StaffRequests           $staffRequests           Reads a route attribute.
     */
    public function __construct(
        private ListedVehicleRepository $listedVehicleRepository,
        private VehiclePhotos $vehiclePhotos,
        private Brochures $brochures,
        private CarSlugs $carSlugs = new CarSlugs(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request routed with a `slug`.
     *
     * @throws QueryException           When the vehicles cannot be read.
     * @throws InvalidArgumentException When their entity type is malformed.
     * @throws UnexpectedValueException When a stored status is no status.
     *
     * @return ListedVehicle|null The car for sale with that exact slug; null when none has it, or it is sold or held.
     */
    public function car(ServerRequestInterface $serverRequest): ?ListedVehicle
    {
        $slug = $this->staffRequests->attribute($serverRequest, 'slug');

        if (!$this->carSlugs->holds($slug)) {
            return null;
        }

        $listedVehicle = $this->listedVehicleRepository->find($this->carSlugs->year($slug), $this->carSlugs->key($slug));

        return $listedVehicle instanceof ListedVehicle && $this->carSlugs->slug($listedVehicle) === $slug
            ? $listedVehicle
            : null;
    }

    /**
     * The photos of the cars a list shows, in one read (#25): the profiler showed `/cars` and the feed reading them once
     * a car.
     *
     * @param list<ListedVehicle> $listedVehicles Cars for sale.
     *
     * @throws LogicException   When the photos' entity type is malformed.
     * @throws RuntimeException When the photos cannot be read.
     *
     * @return array<int, list<VehiclePhoto>> Each car's photos by its id, the first added first; a car with none has
     *                                        no entry.
     */
    public function photosOf(array $listedVehicles): array
    {
        $vehicleIds = [];

        foreach ($listedVehicles as $listedVehicle) {
            $vehicleIds[] = $listedVehicle->id;
        }

        return $this->vehiclePhotos->photosOf($vehicleIds);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request routed with a `slug` and a `position`.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles or their photos cannot be read, or there is no car for sale or no photo there
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return FileDownload The photo; AIC sends its ETag and honours the request's conditions and range.
     */
    public function photo(ServerRequestInterface $serverRequest): FileDownload
    {
        $listedVehicle = $this->car($serverRequest);
        $position = $this->staffRequests->attribute($serverRequest, 'position');
        $vehicleId = $listedVehicle instanceof ListedVehicle
            ? $listedVehicle->id
            : 0;

        return $this->vehiclePhotos->download(
            $serverRequest,
            $vehicleId,
            preg_match('/^[1-9][0-9]{0,2}$/', $position) === 1 ? (int) $position : 0,
        );
    }

    /**
     * @param ListedVehicle $listedVehicle A car for sale.
     *
     * @throws InvalidArgumentException When the brochure's key is not a storage key.
     *
     * @return BrochureLinks|null The links its page shows to its brochure, `{car path}/brochure` and its `/download`;
     *                            null while none is kept.
     */
    public function brochureLinks(ListedVehicle $listedVehicle): ?BrochureLinks
    {
        if (!$this->brochures->exists($listedVehicle->id)) {
            return null;
        }

        $path = $this->carSlugs->path($listedVehicle) . '/brochure';

        return new BrochureLinks('car-brochure', $path, $path . '/download');
    }

    /**
     * @param ServerRequestInterface $serverRequest A request routed with a `slug`.
     * @param bool                   $attachment    Whether to send it to be saved, as `{slug}-brochure.html`, rather
     *                                              than inline.
     *
     * @throws LogicException   When an entity type is malformed.
     * @throws RuntimeException When the vehicles cannot be read, or there is no car for sale or no brochure written
     *                          (`RouteNotFoundException`, which AIC answers 404).
     *
     * @return FileDownload The car's kept brochure; AIC sends its ETag and honours the request's conditions and range.
     *                      Its download name is the car's own slug, which `car()` found equal to the route's.
     */
    public function brochure(ServerRequestInterface $serverRequest, bool $attachment): FileDownload
    {
        $listedVehicle = $this->car($serverRequest);

        if (!$listedVehicle instanceof ListedVehicle) {
            return $this->brochures->download($serverRequest, 0, '');
        }

        $downloadName = $attachment
            ? $this->carSlugs->slug($listedVehicle) . '-brochure.html'
            : '';

        return $this->brochures->download($serverRequest, $listedVehicle->id, $downloadName);
    }
}

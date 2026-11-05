<?php

declare(strict_types=1);

namespace App\Storefront\Job;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Storage\Application\PageFiles;
use App\Brochures\Service\Brochures;
use App\Generated\ThemeLayoutName;
use App\Inventory\Repository\ListedVehicleRepository;
use App\Storefront\Service\CarProfiles;
use App\Storefront\Service\StorePages;
use Override;
use Throwable;

use function ctype_digit;
use function strlen;

/**
 * The `car-brochure` job (#44), which inventory queues with the vehicle's id whenever it changes the vehicle: renders
 * the car's one-page brochure in the theme's `brochure` layout, from storefront's view of it (no VIN, no cost) and the
 * dealership's profile, and keeps it through AIC's `PageFiles` at `Brochures::key()`. A car that is not for sale has
 * its brochure deleted, so none shows what is no longer so.
 */
final readonly class WriteBrochure implements Job
{
    /**
     * @param ListedVehicleRepository $listedVehicleRepository The cars for sale, as the public pages read them.
     * @param CarProfiles             $carProfiles             The brochure's sheet, from the car page's facts and photos.
     * @param PageDocuments           $pageDocuments           The theme's documents.
     * @param PageFiles               $pageFiles               Keeps a rendered page in the private files.
     * @param StorePages              $storePages              The dealership's profile.
     * @param Brochures               $brochures               The brochures' key, and the delete of one.
     */
    public function __construct(
        private ListedVehicleRepository $listedVehicleRepository,
        private CarProfiles $carProfiles,
        private PageDocuments $pageDocuments,
        private PageFiles $pageFiles,
        private StorePages $storePages,
        private Brochures $brochures,
    ) {
    }

    /**
     * Keeps the brochure of the car the payload names, or deletes it when that car is not for sale.
     *
     * @param JobInput $jobInput The payload: `vehicle`, the changed vehicle's id.
     *
     * @throws Throwable When the car, its photos or the dealership's profile cannot be read, or the brochure cannot be
     *                   kept or deleted; the queue retries.
     *
     * @return JobResponse Done, the brochure kept or deleted; failed for a payload that is not a vehicle's id.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $vehicle = $jobInput->value('vehicle', '');
        $vehicleId = $this->vehicleId($vehicle);

        if ($vehicleId === 0) {
            return new JobFailed('The car-brochure payload names no vehicle: "' . $vehicle . '".');
        }

        $listedVehicle = $this->listedVehicleRepository->listing($vehicleId);

        if ($listedVehicle === null) {
            $this->brochures->forget($vehicleId);

            return new JobDone();
        }

        $parts = [new KeyedComponent('brochure', $this->carProfiles->brochure($listedVehicle, $this->storePages->profile()))];
        $title = $listedVehicle->title() . ' brochure';
        $pageDocument = $this->pageDocuments->content($parts, 200, $title, ThemeLayoutName::Brochure);
        $this->pageFiles->put($this->brochures->key($vehicleId), $pageDocument);

        return new JobDone();
    }

    /**
     * @param string $vehicle The payload's `vehicle`.
     *
     * @return int It as a vehicle's id, a whole number of 1 to 18 digits; 0 for anything else.
     */
    private function vehicleId(string $vehicle): int
    {
        return $vehicle !== '' && strlen($vehicle) <= 18 && ctype_digit($vehicle)
            ? (int) $vehicle
            : 0;
    }
}

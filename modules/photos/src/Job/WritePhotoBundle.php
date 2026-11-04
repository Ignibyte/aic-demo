<?php

declare(strict_types=1);

namespace App\Photos\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use App\Photos\Service\PhotoBundles;
use Override;
use Throwable;

use function ctype_digit;
use function strlen;

/**
 * The `photo-bundle` job (#36), which a photo's add or delete queues with the vehicle's id: writes the vehicle's
 * photos as one ZIP in the private files, or deletes it when none are left, through `PhotoBundles`.
 */
final readonly class WritePhotoBundle implements Job
{
    /**
     * @param PhotoBundles $photoBundles Writes the bundle.
     */
    public function __construct(
        private PhotoBundles $photoBundles,
    ) {
    }

    /**
     * @param JobInput $jobInput The payload: `vehicle`, the vehicle's id.
     *
     * @throws Throwable When the photos cannot be read, a photo's file is gone or the bundle cannot be written; the
     *                   queue retries.
     *
     * @return JobResponse Done; failed for a payload that names no vehicle.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $id = $jobInput->value('vehicle', '');

        if ($id === '' || strlen($id) > 18 || !ctype_digit($id)) {
            return new JobFailed('The photo-bundle payload names no vehicle: "' . $id . '".');
        }

        $this->photoBundles->write((int) $id);

        return new JobDone();
    }
}

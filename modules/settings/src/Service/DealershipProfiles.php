<?php

declare(strict_types=1);

namespace App\Settings\Service;

use AIC\Database\Contracts\QueryException;
use App\Settings\Entity\DealershipProfile;
use App\Settings\Repository\ProfileRepository;
use InvalidArgumentException;

/**
 * The dealership's profile for every module that shows or applies it (#22), exported: storefront's public header and
 * footer, leads' test-drive hours and visitor mails, reports' aged-stock threshold and digest. It reads the one row on
 * each call, so a save reaches the next page and the next job.
 */
final readonly class DealershipProfiles
{
    /**
     * @param ProfileRepository $profileRepository The profile's row.
     */
    public function __construct(
        private ProfileRepository $profileRepository,
    ) {
    }

    /**
     * @throws QueryException           When the profile cannot be read.
     * @throws InvalidArgumentException When its entity type is malformed.
     *
     * @return DealershipProfile The profile as saved; the defaults until a first save.
     */
    public function profile(): DealershipProfile
    {
        return $this->profileRepository->profile();
    }
}

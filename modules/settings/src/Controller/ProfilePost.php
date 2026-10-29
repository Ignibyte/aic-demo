<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Settings\Service\ProfileSaves;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/settings/profile (#22): an admin saves the dealership's profile, upserted when the stored one is still the one
 * the form showed; 303 to the settings, 422 for a refused field, 409 for a stale copy, 400 for an altered envelope and
 * 403 for anyone but an admin.
 */
final readonly class ProfilePost implements Page
{
    /**
     * @param ProfileSaves $profileSaves The profile's save.
     */
    public function __construct(
        private ProfileSaves $profileSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted profile form, with its session.
     *
     * @throws Throwable When the records cannot be read or written, or the page cannot be built.
     *
     * @return PageResponse 303 to the settings; the page again as 422 or 409; 400; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->profileSaves->save($serverRequest);
    }
}

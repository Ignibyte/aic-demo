<?php

declare(strict_types=1);

namespace App\Holds\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Holds\Service\HoldSaves;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/holds (#20): places a checked hold and goes to the list, or shows the form again as 422. AIC's form guard
 * answers 403 without the token.
 */
final readonly class HoldCreatePost implements Page
{
    /**
     * @param HoldSaves $holdSaves The form's flows.
     */
    public function __construct(
        private HoldSaves $holdSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws Throwable When the hold cannot be checked or placed, the request has no session, or a key does not fit
     *                   the theme.
     *
     * @return PageResponse 303 to the holds list, or 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->holdSaves->create($serverRequest);
    }
}

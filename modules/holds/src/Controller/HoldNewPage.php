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
 * GET /app/holds/new (#20): the hold form, with the vehicle `?vehicle=` names chosen when it is in stock. The prefix is
 * protected, so only signed-in staff reach it.
 */
final readonly class HoldNewPage implements Page
{
    /**
     * @param HoldSaves $holdSaves The form's flows.
     */
    public function __construct(
        private HoldSaves $holdSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws Throwable When the vehicle or the options cannot be read, the request has no session, or a key does not
     *                   fit the theme.
     *
     * @return PageResponse The form.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->holdSaves->start($serverRequest);
    }
}

<?php

declare(strict_types=1);

namespace App\Holds\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Holds\Service\HoldList;
use App\Staff\Service\StaffPages;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /app/holds (#20): the active holds, newest first. The prefix is protected, so only signed-in staff reach it.
 */
final readonly class HoldListPage implements Page
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     * @param HoldList   $holdList   The list.
     */
    public function __construct(
        private StaffPages $staffPages,
        private HoldList $holdList,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException When the holds cannot be read.
     * @throws LogicException When the request has no session, or a key, a type or a status does not fit.
     *
     * @return PageResponse The list.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->staffPages->page('Holds', [new KeyedComponent('holds', $this->holdList->panel($serverRequest))], 200);
    }
}

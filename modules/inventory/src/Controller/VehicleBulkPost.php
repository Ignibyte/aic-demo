<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Inventory\Form\VehicleBulkEdits;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /app/inventory/bulk (#29): a bulk edit of the inventory list, for every ticked vehicle at once: a status, a lot
 * location, or a delete once confirmed. All are written in one transaction or none is: 200 with the touched rows and
 * the totals line for Tessera, 303 back to the list for a plain post; the rows and why as 422 when any is refused; the
 * page that asks first for a delete posted without its confirmation. AIC's form guard has checked the CSRF token, and
 * its write ledger answers a repeated Tessera write, before it is routed.
 */
final readonly class VehicleBulkPost implements Page
{
    /**
     * @param VehicleBulkEdits $vehicleBulkEdits The bulk edit's flow.
     */
    public function __construct(
        private VehicleBulkEdits $vehicleBulkEdits,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted bulk edit, with its session.
     *
     * @throws Throwable When a read or a write fails (the transaction rolled back first), the request has no session, or
     *                   an answer does not fit the theme.
     *
     * @return PageResponse 200, 303 or 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->vehicleBulkEdits->post($serverRequest);
    }
}

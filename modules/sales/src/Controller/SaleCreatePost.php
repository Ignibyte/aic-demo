<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Sales\Form\SaleSaves;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /app/sales: records the sale and marks its vehicle sold when every field passes and goes to the sale (303);
 * else the form again (422), each error beside its field, a vehicle already sold among them.
 */
final readonly class SaleCreatePost implements Page
{
    /**
     * @param SaleSaves $saleSaves The form's flows.
     */
    public function __construct(
        private SaleSaves $saleSaves,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException When the sale cannot be checked or recorded.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the new sale, or 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->saleSaves->create($serverRequest);
    }
}

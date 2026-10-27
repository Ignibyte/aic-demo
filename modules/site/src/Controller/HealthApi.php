<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Application\DatabaseStatus;
use AIC\Database\Contracts\QueryException;
use App\Site\Service\HealthBody;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/** Report whether Lotline can reach its database, asked through AIC's `DatabaseStatus` (#14). */
final readonly class HealthApi implements Api
{
    /** @param DatabaseStatus $databaseStatus What the database says about itself. */
    public function __construct(private DatabaseStatus $databaseStatus)
    {
    }

    /**
     * @param ServerRequestInterface $serverRequest The incoming request.
     *
     * @return ApiResponse The database state and request protocol.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        $protocol = $serverRequest->getProtocolVersion();

        try {
            $this->databaseStatus->ping();
        } catch (QueryException) {
            return new JsonResponse(new HealthBody(false, $protocol), 503);
        }

        return new JsonResponse(new HealthBody(true, $protocol));
    }
}

<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use App\Site\Service\HealthBody;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/** Report whether Lotline can reach its database. */
final readonly class HealthApi implements Api
{
    /** @param Database $database The application database. */
    public function __construct(private Database $database)
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
            $this->database->fetchOne('SELECT 1 AS ready', []);
        } catch (QueryException) {
            return new JsonResponse(new HealthBody(false, $protocol), 503);
        }

        return new JsonResponse(new HealthBody(true, $protocol));
    }
}

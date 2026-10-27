<?php

declare(strict_types=1);

namespace App\Staff\Service;

use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;

/**
 * A staff page's request read as plain text: a query parameter and a route attribute, each through a native `string`
 * return, so the checks after it work on a string under elephc, which does not narrow an untyped value by
 * `is_string()` (no AIC ticket carries this yet). Customers and sales share it, so the reading lives once (gate:14).
 */
final readonly class StaffRequests
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          A query parameter's name.
     *
     * @return string Its text; empty when it is absent, or a list (`q[]=…`) rather than one value.
     */
    public function query(ServerRequestInterface $serverRequest, string $name): string
    {
        $parameters = $serverRequest->getQueryParams();

        return array_key_exists($name, $parameters) && is_string($parameters[$name])
            ? $parameters[$name]
            : '';
    }

    /**
     * @param ServerRequestInterface $serverRequest A routed request.
     * @param string                 $name          A route attribute's name, such as `id`.
     *
     * @return string Its text; empty when it is absent or not a string.
     */
    public function attribute(ServerRequestInterface $serverRequest, string $name): string
    {
        $attributes = $serverRequest->getAttributes();

        return array_key_exists($name, $attributes) && is_string($attributes[$name])
            ? $attributes[$name]
            : '';
    }
}

<?php

declare(strict_types=1);

namespace App\Customers\Service;

use App\Customers\Form\CustomerQuery;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;
use function preg_match;
use function trim;

/**
 * Reads the customers list's query parameters, `q` and `page`, each on its own: one that is missing, a list rather
 * than one value, or malformed falls back to its default.
 */
final readonly class CustomerQueries
{
    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     *
     * @return CustomerQuery What it asks for: the search trimmed, and the page when it is 1 to 9999, else 1.
     */
    public function read(ServerRequestInterface $serverRequest): CustomerQuery
    {
        $page = $this->parameter($serverRequest, 'page');

        return new CustomerQuery(
            trim($this->parameter($serverRequest, 'q')),
            preg_match('/^[1-9][0-9]{0,3}$/', $page) === 1 ? (int) $page : 1,
        );
    }

    /**
     * Gives the value through a native `string` return, so the checks after it work on a string under elephc, which
     * does not narrow an untyped value by `is_string()`. No AIC ticket carries this yet.
     *
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          A query parameter's name.
     *
     * @return string Its text; empty when it is absent, or a list (`q[]=…`) rather than one value.
     */
    private function parameter(ServerRequestInterface $serverRequest, string $name): string
    {
        $parameters = $serverRequest->getQueryParams();

        return array_key_exists($name, $parameters) && is_string($parameters[$name])
            ? $parameters[$name]
            : '';
    }
}

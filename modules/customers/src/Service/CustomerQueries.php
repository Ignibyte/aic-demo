<?php

declare(strict_types=1);

namespace App\Customers\Service;

use App\Customers\Form\CustomerQuery;
use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;

use function preg_match;
use function trim;

/**
 * Reads the customers list's query parameters, `q` and `page`, each on its own: one that is missing, a list rather
 * than one value, or malformed falls back to its default.
 */
final readonly class CustomerQueries
{
    /**
     * @param StaffRequests $staffRequests Reads each parameter as text.
     */
    public function __construct(
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     *
     * @return CustomerQuery What it asks for: the search trimmed, and the page when it is 1 to 9999, else 1.
     */
    public function read(ServerRequestInterface $serverRequest): CustomerQuery
    {
        $page = $this->staffRequests->query($serverRequest, 'page');

        return new CustomerQuery(
            trim($this->staffRequests->query($serverRequest, 'q')),
            preg_match('/^[1-9][0-9]{0,3}$/', $page) === 1 ? (int) $page : 1,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Sales\Entity\Choice;
use App\Sales\Form\SaleQuery;
use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function preg_match;

/**
 * Reads the sales list's query parameters, `month`, `salesperson` and `page`, each on its own: one that is missing, a
 * list rather than one value, malformed or not among what the list offers falls back to its default.
 */
final readonly class SaleQueries
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
     * @param list<string>           $months        The months that have sales, `YYYY-MM`.
     * @param list<Choice>           $staff         The staff, by login.
     *
     * @return SaleQuery What it asks for: the month and the salesperson when they are offered, and the page when it is
     *                   1 to 9999, else 1.
     */
    public function read(ServerRequestInterface $serverRequest, array $months, array $staff): SaleQuery
    {
        $month = $this->staffRequests->query($serverRequest, 'month');
        $salesperson = $this->staffRequests->query($serverRequest, 'salesperson');
        $page = $this->staffRequests->query($serverRequest, 'page');
        $logins = [];

        foreach ($staff as $choice) {
            $logins[] = $choice->key;
        }

        return new SaleQuery(
            in_array($month, $months, true) ? $month : '',
            in_array($salesperson, $logins, true) ? $salesperson : '',
            preg_match('/^[1-9][0-9]{0,3}$/', $page) === 1 ? (int) $page : 1,
        );
    }
}

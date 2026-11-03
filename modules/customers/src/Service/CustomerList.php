<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Repository\CustomerRepository;
use App\Staff\Service\StaffDealership;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The customers list's panel: the notice a delete left, a link to add a customer, the search and the results of the
 * page asked for, anchored `customer-results` for the controls that refresh them. The results come from their source,
 * `CustomerResults`, which reads the customers only when the results are rendered, so a refresh of them alone reads
 * their own rows and nothing more. The search is `CustomerFilters`' form, which submits on each pause in typing (#27).
 */
final readonly class CustomerList
{
    /**
     * @param CustomerRepository $customerRepository The customers.
     * @param StaffDealership    $staffDealership    The dealership's name, for the panel's line.
     * @param CustomerQueries    $customerQueries    Reads what the list is asked for.
     * @param CustomerTable      $customerTable      The table and the pages.
     * @param StaffNotices       $staffNotices       The last delete's notice.
     * @param CustomerFilters    $customerFilters    The search form.
     */
    public function __construct(
        private CustomerRepository $customerRepository,
        private StaffDealership $staffDealership,
        private CustomerQueries $customerQueries = new CustomerQueries(),
        private CustomerTable $customerTable = new CustomerTable(),
        private StaffNotices $staffNotices = new StaffNotices(),
        private CustomerFilters $customerFilters = new CustomerFilters(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its query parameters and session.
     *
     * @throws QueryException When the dealership's name cannot be read.
     * @throws LogicException When the request carries no session, or a key is malformed.
     *
     * @return Panel The list, in a wide panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $customerQuery = $this->customerQueries->read($serverRequest);
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'customers-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('add', new Link('Add a customer', Customer::PAGES . '/new'));
        $body[] = new KeyedComponent('filters', $this->customerFilters->filterBar($customerQuery->search));
        $customerResults = new CustomerResults($this->customerRepository, $this->customerTable, $customerQuery);
        $body[] = new KeyedComponent('table', new SourcedComponent($customerResults), '', CustomerTable::ANCHOR);

        return new Panel('customers', 'Customers', 'Search and page the people ' . $this->staffDealership->name() . ' serves', true, $body);
    }
}

<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\SourcedComponent;
use AIC\Database\Contracts\QueryException;
use App\Customers\Form\CustomerRowForm;
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
 * A row's own answer (#28) builds the panel under the same key, title and results' key, holding that row alone.
 */
final readonly class CustomerList
{
    /**
     * The panel's key on the page, and its id.
     */
    public const string KEY = 'customers';

    /**
     * The panel's title.
     */
    public const string TITLE = 'Customers';

    /**
     * The panel's line on a row's own answer (#28), which reads nothing else.
     */
    public const string ROW_LINE = 'One customer, edited in its row';

    /**
     * The results' key in the panel.
     */
    public const string RESULTS = 'table';

    /**
     * @param CustomerRepository $customerRepository The customers.
     * @param StaffDealership    $staffDealership    The dealership's name, for the panel's line.
     * @param CustomerRowForm    $customerRowForm    Each customer's row.
     * @param CustomerQueries    $customerQueries    Reads what the list is asked for.
     * @param CustomerTable      $customerTable      The table and the pages.
     * @param StaffNotices       $staffNotices       The last delete's notice.
     * @param CustomerFilters    $customerFilters    The search form.
     */
    public function __construct(
        private CustomerRepository $customerRepository,
        private StaffDealership $staffDealership,
        private CustomerRowForm $customerRowForm,
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

        $body[] = new KeyedComponent('add', new Link('Add a customer', CustomerTable::ADD));
        $body[] = new KeyedComponent('filters', $this->customerFilters->filterBar($customerQuery->search));
        $customerResults = new CustomerResults($this->customerRepository, $this->customerTable, $customerQuery, $this->customerRowForm);
        $body[] = new KeyedComponent(self::RESULTS, new SourcedComponent($customerResults), '', CustomerTable::ANCHOR);

        return new Panel(self::KEY, self::TITLE, 'Search and page the people ' . $this->staffDealership->name() . ' serves', true, $body);
    }
}

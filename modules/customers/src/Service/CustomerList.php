<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Repository\CustomerRepository;
use App\Staff\Service\StaffDealership;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\FilterBar\FilterBar;
use App\Theme\Components\FormField\FormField;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function intdiv;

/**
 * The customers list's panel: the notice a delete left, a link to add a customer, the search, the table of the page
 * asked for (15 customers a page, by name; a page past the last shows the first) and, past one page, the pages.
 */
final readonly class CustomerList
{
    /**
     * The customers on a page.
     */
    private const int PAGE = 15;

    /**
     * @param CustomerRepository $customerRepository The customers.
     * @param StaffDealership    $staffDealership    The dealership's name, for the panel's line.
     * @param CustomerQueries    $customerQueries    Reads what the list is asked for.
     * @param CustomerTable      $customerTable      The table and the pages.
     * @param StaffNotices       $staffNotices       The last delete's notice.
     */
    public function __construct(
        private CustomerRepository $customerRepository,
        private StaffDealership $staffDealership,
        private CustomerQueries $customerQueries = new CustomerQueries(),
        private CustomerTable $customerTable = new CustomerTable(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its query parameters and session.
     *
     * @throws QueryException When the customers cannot be read.
     * @throws LogicException When the request carries no session, or a key is malformed.
     *
     * @return Panel The list, in a wide panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $customerQuery = $this->customerQueries->read($serverRequest);
        $search = $customerQuery->search;
        $count = $this->customerRepository->count($search);
        $pages = intdiv($count + self::PAGE - 1, self::PAGE);
        $page = $customerQuery->page <= $pages
            ? $customerQuery->page
            : 1;
        $customers = $this->customerRepository->page($search, self::PAGE, ($page - 1) * self::PAGE);
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'customers-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('add', new Link('Add a customer', Customer::PAGES . '/new'));
        $body[] = new KeyedComponent('filters', $this->filterBar($search));
        $body[] = new KeyedComponent('table', $this->customerTable->table($customerQuery, $customers, $count, $page, $pages));

        if ($pages > 1) {
            $body[] = new KeyedComponent('pages', $this->customerTable->pagination($customerQuery, $page, $pages));
        }

        return new Panel('customers', 'Customers', 'Search and page the people ' . $this->staffDealership->name() . ' serves', true, $body);
    }

    /**
     * @param string $search The search asked for.
     *
     * @throws LogicException When a key is malformed.
     *
     * @return FilterBar The search, showing what was asked; a plain GET form, which Tessera answers in `content`.
     */
    private function filterBar(string $search): FilterBar
    {
        $formField = new FormField('filter-q', 'q', 'Search name, email or phone', 'search', $search, '', false, true);

        return new FilterBar('customers-filters', Customer::PAGES, 'content', [new KeyedComponent('q', $formField)], Customer::PAGES);
    }
}

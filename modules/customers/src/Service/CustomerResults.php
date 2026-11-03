<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\ComponentSource;
use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Customers\Form\CustomerQuery;
use App\Customers\Repository\CustomerRepository;
use App\Theme\Components\TableResults\TableResults;
use InvalidArgumentException;
use Override;

use function intdiv;

/**
 * The customers list's results as a source (#27, as inventory's are): it holds the repository and the query the list
 * was asked for, and reads only in `build()`, which the render pass calls when it renders the results. A Tessera visit
 * that names the results alone (`@customer-results`) builds them and nothing else's data. 15 customers a page, by
 * name; a page past the last shows the first.
 */
final readonly class CustomerResults implements ComponentSource
{
    /**
     * The customers on a page.
     */
    public const int PAGE = 15;

    /**
     * @param CustomerRepository $customerRepository The customers.
     * @param CustomerTable      $customerTable      The table and the pages.
     * @param CustomerQuery      $customerQuery      What the list was asked for.
     */
    public function __construct(
        private CustomerRepository $customerRepository,
        private CustomerTable $customerTable,
        private CustomerQuery $customerQuery,
    ) {
    }

    /**
     * @throws QueryException           When the customers cannot be read.
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return Component The page's table and, past one page, the pages.
     */
    #[Override]
    public function build(): Component
    {
        $search = $this->customerQuery->search;
        $count = $this->customerRepository->count($search);
        $pages = intdiv($count + self::PAGE - 1, self::PAGE);
        $page = $this->customerQuery->page <= $pages
            ? $this->customerQuery->page
            : 1;
        $customers = $this->customerRepository->page($search, self::PAGE, ($page - 1) * self::PAGE);
        $parts = [new KeyedComponent('table', $this->customerTable->table($this->customerQuery, $customers, $count, $page, $pages))];

        if ($pages > 1) {
            $parts[] = new KeyedComponent('pages', $this->customerTable->pagination($this->customerQuery, $page, $pages));
        }

        return new TableResults($parts);
    }
}

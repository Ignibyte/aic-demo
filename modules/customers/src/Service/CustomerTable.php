<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Customers\Entity\Customer;
use App\Customers\Form\CustomerQuery;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function implode;
use function number_format;
use function rawurlencode;

/**
 * The customers' tables: the list's, a row per customer on the page, its name linked to the customer's page, with a
 * link per page that keeps the search (Tessera answers the pages in `content`); and a customer's vehicles bought.
 */
final readonly class CustomerTable
{
    /**
     * The regions a page link updates.
     */
    private const string TARGET = 'content';

    /**
     * @param CustomerQuery  $customerQuery What the list was asked for.
     * @param list<Customer> $customers     The page's customers.
     * @param int            $count         How many customers match.
     * @param int            $page          The page shown.
     * @param int            $pages         How many pages there are.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable The page's rows under the heads, or the empty line.
     */
    public function table(CustomerQuery $customerQuery, array $customers, int $count, int $page, int $pages): DataTable
    {
        $rows = [];

        foreach ($customers as $customer) {
            $rows[] = new KeyedComponent('customer-' . $customer->id, $this->row($customer));
        }

        $empty = $customerQuery->search === ''
            ? 'No customers yet. Add the first one.'
            : 'No customer matches. Try another search, or clear it.';

        return new DataTable('customers-table', $this->summary($count, $page, $pages), $empty, self::TARGET, [
            new TableColumn('Name', '', 'none', '', false),
            new TableColumn('Email', '', 'none', '', false),
            new TableColumn('Phone', '', 'none', '', false),
            new TableColumn('Added', '', 'none', '', false),
        ], $rows);
    }

    /**
     * There is no sales table until sales are recorded (#7), so no row is read for it yet.
     *
     * @return DataTable A customer's vehicles bought: the heads, no rows, and the line saying so.
     */
    public function purchases(): DataTable
    {
        return new DataTable('customer-purchases', 'Vehicles bought', 'No vehicles bought yet.', self::TARGET, [
            new TableColumn('VIN', '', 'none', '', false),
            new TableColumn('Vehicle', '', 'none', '', false),
            new TableColumn('Sold on', '', 'none', '', false),
            new TableColumn('Price', '', 'none', '', true),
        ], []);
    }

    /**
     * @param CustomerQuery $customerQuery What the list was asked for.
     * @param int           $page          The page shown.
     * @param int           $pages         How many pages there are.
     *
     * @return Pagination A link per page, keeping the search.
     */
    public function pagination(CustomerQuery $customerQuery, int $page, int $pages): Pagination
    {
        $pageLinks = [];

        for ($number = 1; $number <= $pages; $number += 1) {
            $pageLinks[] = new PageLink((string) $number, $this->href($customerQuery, $number), $number === $page);
        }

        return new Pagination('customers-pages', self::TARGET, $pageLinks);
    }

    /**
     * @param Customer $customer A customer.
     *
     * @return TableRow Its row: the name, linked to its page, the email, the phone and the day it was added.
     */
    private function row(Customer $customer): TableRow
    {
        $customerDetails = $customer->customerDetails;

        return new TableRow([
            new TableCell($customerDetails->name, $customer->path(), false, false, null),
            new TableCell($customerDetails->email, '', false, false, null),
            new TableCell($customerDetails->phone, '', false, false, null),
            new TableCell($customer->addedOn, '', false, false, null),
        ]);
    }

    /**
     * @param CustomerQuery $customerQuery What the list was asked for; its search is kept.
     * @param int           $page          The page, from 1.
     *
     * @return string The list's path with the search and the page, each only when it is not its default.
     */
    private function href(CustomerQuery $customerQuery, int $page): string
    {
        $parameters = [];

        if ($customerQuery->search !== '') {
            $parameters[] = 'q=' . rawurlencode($customerQuery->search);
        }

        if ($page > 1) {
            $parameters[] = 'page=' . $page;
        }

        return $parameters === []
            ? Customer::PAGES
            : Customer::PAGES . '?' . implode('&', $parameters);
    }

    /**
     * @param int $count How many customers match.
     * @param int $page  The page shown.
     * @param int $pages How many pages there are.
     *
     * @return string What the table shows: the count, and which page when there is more than one.
     */
    private function summary(int $count, int $page, int $pages): string
    {
        $shown = number_format($count) . ($count === 1 ? ' customer' : ' customers');

        return $pages < 2
            ? $shown
            : $shown . ', page ' . $page . ' of ' . $pages;
    }
}

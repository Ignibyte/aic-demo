<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Component\Domain\KeyedComponent;
use App\Customers\Entity\Customer;
use App\Customers\Entity\Purchase;
use App\Customers\Form\CustomerQuery;
use App\Theme\Components\DataTable\DataTable;
use App\Theme\Components\DataTable\TableColumn;
use App\Theme\Components\Pagination\PageLink;
use App\Theme\Components\Pagination\Pagination;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\StatusBadge\StatusBadge;
use App\Theme\Components\TableRow\TableCell;
use App\Theme\Components\TableRow\TableRow;
use InvalidArgumentException;

use function count;
use function implode;
use function number_format;
use function rawurlencode;

/**
 * The customers' tables: the list's, a row per customer on the page, its name linked to the customer's page, with a
 * link per page that keeps the search (Tessera answers the pages with the list's results alone, `@customer-results`);
 * and a customer's vehicles bought, with the badge that counts them.
 */
final readonly class CustomerTable
{
    /**
     * The list's results' anchor: the list's panel carries it on the results, its search and pages target
     * `@customer-results`, and Tessera finds the results' path on the page, so no code here spells it.
     */
    public const string ANCHOR = 'customer-results';

    /**
     * What the list's table and pages update: its results, by their anchor.
     */
    private const string RESULTS = '@' . self::ANCHOR;

    /**
     * The region a customer's purchases table updates.
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

        return new DataTable('customers-table', $this->summary($count, $page, $pages), $empty, self::RESULTS, [
            new TableColumn('Name', '', 'none', '', false),
            new TableColumn('Email', '', 'none', '', false),
            new TableColumn('Phone', '', 'none', '', false),
            new TableColumn('Added', '', 'none', '', false),
        ], $rows);
    }

    /**
     * @param list<Purchase> $purchases What a customer bought.
     *
     * @throws InvalidArgumentException When a key is malformed.
     *
     * @return DataTable A customer's vehicles bought: a row each, the VIN linked to the vehicle and the day to the sale;
     *                   the line saying so when there are none.
     */
    public function purchases(array $purchases): DataTable
    {
        $rows = [];

        foreach ($purchases as $index => $purchase) {
            $tableRow = new TableRow([
                new TableCell($purchase->vin, $purchase->vehiclePath, true, false, null),
                new TableCell($purchase->vehicle, '', false, false, null),
                new TableCell($purchase->soldOn, $purchase->salePath, false, false, null),
                new TableCell('$' . number_format($purchase->price), '', false, true, null),
            ]);
            $rows[] = new KeyedComponent('purchase-' . $index, $tableRow);
        }

        return new DataTable('customer-purchases', 'Vehicles bought', 'No vehicles bought yet.', self::TARGET, [
            new TableColumn('VIN', '', 'none', '', false),
            new TableColumn('Vehicle', '', 'none', '', false),
            new TableColumn('Sold on', '', 'none', '', false),
            new TableColumn('Price', '', 'none', '', true),
        ], $rows);
    }

    /**
     * @param list<Purchase> $purchases What a customer bought.
     *
     * @return StatusBadge Its card's badge: how many purchases, or none yet.
     */
    public function badge(array $purchases): StatusBadge
    {
        $count = count($purchases);

        return $count === 0
            ? new StatusBadge('No purchases yet', BadgeTone::Neutral, 'customer-badge')
            : new StatusBadge($count . ($count === 1 ? ' purchase' : ' purchases'), BadgeTone::Success, 'customer-badge');
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

        return new Pagination('customers-pages', self::RESULTS, $pageLinks);
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

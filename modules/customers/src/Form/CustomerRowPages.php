<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use App\Customers\Repository\CustomerRecordRepository;
use App\Customers\Service\CustomerList;
use App\Customers\Service\CustomerRecords;
use App\Customers\Service\CustomerTable;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\Panel\Panel;
use LogicException;

/**
 * The page a customer's row answers with (#28): the customers page, its list's panel holding that row alone under the
 * keys the list gives it, so the row sits at the path it has in the list. Its line is a fixed one, so the answer reads
 * nothing but the customer. A Tessera request from the row gets the row's fragment alone; a plain one the page whole.
 * It also frames the customer pages' 404 and 400 pages, which a row's answer gives for a customer gone or another's
 * props.
 */
final readonly class CustomerRowPages
{
    /**
     * The staff pages' document.
     */
    private StaffPages $staffPages;

    /**
     * @param PageDocuments $pageDocuments The theme's documents, which the staff pages' document is built on.
     * @param CustomerTable $customerTable The list's results, of one row.
     */
    public function __construct(
        PageDocuments $pageDocuments,
        private CustomerTable $customerTable = new CustomerTable(),
    ) {
        $this->staffPages = new StaffPages($pageDocuments);
    }

    /**
     * @param KeyedComponent $keyedComponent The customer's row, shown or as a form.
     * @param int            $status         200, or 422 for a refused save.
     *
     * @throws LogicException When a key does not fit; or a region does not fit the theme.
     *
     * @return PageDocument The customers page, its list's panel holding that row alone where the list puts it.
     */
    public function page(KeyedComponent $keyedComponent, int $status): PageDocument
    {
        $results = new KeyedComponent(CustomerList::RESULTS, $this->customerTable->alone($keyedComponent), '', CustomerTable::ANCHOR);
        $panel = new Panel(CustomerList::KEY, CustomerList::TITLE, CustomerList::ROW_LINE, true, [$results]);

        return $this->staffPages->page('Customers', [new KeyedComponent(CustomerList::KEY, $panel)], $status);
    }

    /**
     * @param CustomerRecordRepository $customerRecordRepository Reads a customer by its id.
     *
     * @return CustomerRecords The route's customer, and the customer pages' 404 and 400 pages in the staff document.
     */
    public function records(CustomerRecordRepository $customerRecordRepository): CustomerRecords
    {
        return new CustomerRecords($customerRecordRepository, new StaffPanels($this->staffPages, 'customer'));
    }
}

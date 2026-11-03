<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Customers\Entity\Customer;
use App\Customers\Repository\CustomerRecordRepository;
use App\Customers\Service\CustomerRecords;
use App\Staff\Form\StaffSeals;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A customer's row in the list, edited in place (#28): the row shown, the row as a form, and its phone saved. Each
 * answer is `CustomerRowPages`' page holding that row alone, where the list puts it, so a Tessera request from the row
 * is answered with the row and nothing else. The wiring builds it from the entity store and the seals, its page
 * first, so its generated handler part does not open as the record forms' part does (gate:14). A save is held to its
 * row's props and checked by the customer form's own checks over the stored customer with the posted phone put in; a
 * refusal is the row as a form again, 422.
 */
final readonly class CustomerRowEdits
{
    /**
     * The route's customer and the 404 and 400 pages.
     */
    private CustomerRecords $customerRecords;

    /**
     * Saves the customer.
     */
    private CustomerRecordRepository $customerRecordRepository;

    /**
     * The customer form's checks.
     */
    private CustomerChecks $customerChecks;

    /**
     * The row, shown or as a form.
     */
    private CustomerRowForm $customerRowForm;

    /**
     * @param CustomerRowPages $customerRowPages The page holding the row, and the record's pages.
     * @param EntityStore      $entityStore      The entity store, through which the customer is read and saved.
     * @param StaffSeals       $staffSeals       Seals the row's props and checks them on a save.
     * @param CustomerEntries  $customerEntries  Fills the form from the customer and the row's post.
     */
    public function __construct(
        private CustomerRowPages $customerRowPages,
        EntityStore $entityStore,
        private StaffSeals $staffSeals,
        private CustomerEntries $customerEntries = new CustomerEntries(),
    ) {
        $this->customerRecordRepository = new CustomerRecordRepository($entityStore);
        $this->customerRecords = $customerRowPages->records($this->customerRecordRepository);
        $this->customerChecks = new CustomerChecks($this->customerRecordRepository);
        $this->customerRowForm = new CustomerRowForm($staffSeals);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a customer's row, with its session.
     *
     * @throws QueryException When the customer cannot be read.
     * @throws LogicException When a key does not fit; or the path cannot be sealed, or a region does not fit the theme.
     *
     * @return PageResponse The row shown, or 404.
     */
    public function shown(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        return $customer instanceof Customer
            ? $this->customerRowPages->page($this->customerRowForm->shown($customer), 200)
            : $this->customerRecords->missing();
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a customer's row as a form, with its session.
     *
     * @throws QueryException When the customer cannot be read.
     * @throws LogicException When a key does not fit; or the path cannot be sealed, or a region does not fit the theme.
     *
     * @return PageResponse The row as a form, its phone filled from the customer; or 404.
     */
    public function form(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        $keyedComponent = $this->customerRowForm->editing($customer, $this->customerEntries->filled($customer), []);

        return $this->customerRowPages->page($keyedComponent, 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A row's save: its phone, its props, its session.
     *
     * @throws QueryException When the customer cannot be read or written.
     * @throws LogicException When a key or a value does not fit; or the path cannot be sealed, or a region does not fit the theme.
     *
     * @return PageResponse The row shown with what was saved; the row as a form again, 422; 400 for props that are not
     *                      this customer's row's; or 404.
     */
    public function save(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        if (!$this->staffSeals->rowHolds($serverRequest, CustomerRowForm::ANCHOR . $customer->id, $customer->path())) {
            return $this->customerRecords->refused();
        }

        $customerEntry = $this->customerEntries->row($customer, $serverRequest);
        $errors = $this->customerChecks->errors($customerEntry, $customer->id);

        if ($errors !== []) {
            return $this->customerRowPages->page($this->customerRowForm->editing($customer, $customerEntry, $errors), 422);
        }

        $customerDetails = $this->customerChecks->details($customerEntry);

        if (!$this->customerRecordRepository->update($customer->id, $customerDetails)) {
            return $this->customerRecords->missing();
        }

        $saved = new Customer($customer->id, $customerDetails, $customer->addedOn);

        return $this->customerRowPages->page($this->customerRowForm->shown($saved), 200);
    }
}

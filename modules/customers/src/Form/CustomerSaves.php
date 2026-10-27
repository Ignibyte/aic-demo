<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Repository\CustomerRecordRepository;
use App\Customers\Service\CustomerRecords;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffNotices;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The customer form's flows: the blank form, a new customer checked and made, a stored one's form, and that form
 * checked and saved. A refusal shows the form again as 422 with what was typed; a save leaves a notice and goes to
 * the customer (303).
 */
final readonly class CustomerSaves
{
    /**
     * @param CustomerRecords          $customerRecords          The route's customer and the 404 and 400 pages.
     * @param CustomerRecordRepository $customerRecordRepository Saves the customer.
     * @param CustomerChecks           $customerChecks           Checks the form.
     * @param CustomerForm             $customerForm             The form's page.
     * @param StaffSeals               $staffSeals               Checks an edit form's envelope.
     * @param CustomerEntries          $customerEntries          Fills the form.
     * @param StaffNotices             $staffNotices             Leaves the save's notice.
     */
    public function __construct(
        private CustomerRecords $customerRecords,
        private CustomerRecordRepository $customerRecordRepository,
        private CustomerChecks $customerChecks,
        private CustomerForm $customerForm,
        private StaffSeals $staffSeals,
        private CustomerEntries $customerEntries = new CustomerEntries(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the new customer form, with its session.
     *
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The blank form.
     */
    public function start(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->customerForm->page($serverRequest, $this->customerEntries->blank(), [], '', 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted new customer, with its session.
     *
     * @throws QueryException When the customers cannot be read or written.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the new customer, or the form again as 422.
     */
    public function create(ServerRequestInterface $serverRequest): PageResponse
    {
        $customerEntry = $this->customerEntries->posted($serverRequest);
        $errors = $this->customerChecks->errors($customerEntry, 0);

        if ($errors !== []) {
            return $this->customerForm->page($serverRequest, $customerEntry, $errors, '', 422);
        }

        $id = $this->customerRecordRepository->create($this->customerChecks->details($customerEntry));

        if ($id === null) {
            return $this->customerForm->page($serverRequest, $customerEntry, ['email' => CustomerChecks::EMAIL_TAKEN], '', 422);
        }

        return $this->saved($serverRequest, Customer::PAGES . '/' . $id, 'Added this customer.');
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a customer's edit form, with its session.
     *
     * @throws QueryException When the customer cannot be read.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The form filled from the customer, or 404.
     */
    public function edit(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        return $this->customerForm->page($serverRequest, $this->customerEntries->filled($customer), [], $customer->path(), 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted edit form, with its session.
     *
     * @throws QueryException When the customers cannot be read or written.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the customer; the form again as 422; 400 for an envelope that is not this customer's;
     *                      or 404.
     */
    public function update(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        $path = $customer->path();

        if (!$this->staffSeals->holds($serverRequest, $path)) {
            return $this->customerRecords->refused();
        }

        $customerEntry = $this->customerEntries->posted($serverRequest);
        $errors = $this->customerChecks->errors($customerEntry, $customer->id);

        if ($errors !== []) {
            return $this->customerForm->page($serverRequest, $customerEntry, $errors, $path, 422);
        }

        return $this->changed($serverRequest, $customer, $customerEntry);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted edit form, with its session.
     * @param Customer               $customer      The customer as stored.
     * @param CustomerEntry          $customerEntry What was posted, found valid.
     *
     * @throws QueryException When the customer cannot be written for any reason but an email taken meanwhile.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the customer; the form again as 422 when another save took the email since the
     *                      check; or 404 when the customer went meanwhile.
     */
    private function changed(
        ServerRequestInterface $serverRequest,
        Customer $customer,
        CustomerEntry $customerEntry,
    ): PageResponse {
        $customerDetails = $this->customerChecks->details($customerEntry);

        try {
            $updated = $this->customerRecordRepository->update($customer->id, $customerDetails);
        } catch (QueryException $queryException) {
            if (!$this->customerRecordRepository->taken($customerDetails->email, $customer->id)) {
                throw $queryException;
            }

            $errors = ['email' => CustomerChecks::EMAIL_TAKEN];

            return $this->customerForm->page($serverRequest, $customerEntry, $errors, $customer->path(), 422);
        }

        if (!$updated) {
            return $this->customerRecords->missing();
        }

        return $this->saved($serverRequest, $customer->path(), 'Saved your changes.');
    }

    /**
     * @param ServerRequestInterface $serverRequest The post that saved, with its session.
     * @param string                 $path          The customer's path.
     * @param string                 $message       The notice its page shows.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return PageRedirect 303 to the customer.
     */
    private function saved(ServerRequestInterface $serverRequest, string $path, string $message): PageRedirect
    {
        $this->staffNotices->keep($serverRequest, $message);

        return new PageRedirect($path);
    }
}

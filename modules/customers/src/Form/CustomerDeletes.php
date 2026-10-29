<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Repository\CustomerRecordRepository;
use App\Customers\Service\BoughtVehicles;
use App\Customers\Service\CustomerRecords;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffDealership;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPanels;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Deleting a customer: the page that asks first, and the delete itself, which leaves a notice for the list and goes
 * there (303). A customer who bought a vehicle stays: the sale holds the record, so the page says so instead and a
 * delete posted anyway answers 422.
 */
final readonly class CustomerDeletes
{
    /**
     * The delete page, built here with the dealership's name (#23).
     */
    private CustomerDeleteForm $customerDeleteForm;

    /**
     * @param CustomerRecords          $customerRecords          The route's customer and the 404 and 400 pages.
     * @param CustomerRecordRepository $customerRecordRepository Deletes the customer.
     * @param StaffPanels              $staffPanels              The customer pages' frame, for the delete page.
     * @param StaffSeals               $staffSeals               Checks the form's envelope.
     * @param BoughtVehicles           $boughtVehicles           Whether the customer bought anything.
     * @param StaffDealership          $staffDealership          The dealership's name, which the delete page's refusal names.
     * @param StaffNotices             $staffNotices             Leaves the delete's notice for the list.
     */
    public function __construct(
        private CustomerRecords $customerRecords,
        private CustomerRecordRepository $customerRecordRepository,
        StaffPanels $staffPanels,
        private StaffSeals $staffSeals,
        private BoughtVehicles $boughtVehicles,
        StaffDealership $staffDealership,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
        $this->customerDeleteForm = new CustomerDeleteForm($staffPanels, $staffSeals, $staffDealership);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a customer's delete page, with its session.
     *
     * @throws RuntimeException When the customer, its purchases or the dealership's name cannot be read (`QueryException`).
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The page that asks first, or that says a customer who bought stays; or 404.
     */
    public function confirm(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        return $this->customerDeleteForm->page($serverRequest, $customer, $this->bought($customer), 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted delete form, with its session.
     *
     * @throws RuntimeException When the customer cannot be read or deleted, for any reason but a sale recorded since the
     *                          check, or the dealership's name cannot be read.
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the list; the delete page again as 422 for a customer who bought; 400 for an envelope
     *                      that is not this customer's; or 404, also when the customer went meanwhile.
     */
    public function delete(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        if (!$this->staffSeals->holds($serverRequest, $customer->path())) {
            return $this->customerRecords->refused();
        }

        if ($this->bought($customer)) {
            return $this->customerDeleteForm->page($serverRequest, $customer, true, 422);
        }

        return $this->removed($serverRequest, $customer);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted delete form, its envelope checked, with its session.
     * @param Customer               $customer      The customer, who bought nothing when it was checked.
     *
     * @throws RuntimeException When the customer cannot be deleted, for any reason but a sale recorded since the check, or
     *                          the dealership's name cannot be read.
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the list; 422 when a sale landed since the check; or 404 when the customer went.
     */
    private function removed(ServerRequestInterface $serverRequest, Customer $customer): PageResponse
    {
        try {
            $deleted = $this->customerRecordRepository->delete($customer->id);
        } catch (QueryException $queryException) {
            // A sale recorded since the check holds the row by its foreign key.
            if (!$this->bought($customer)) {
                throw $queryException;
            }

            return $this->customerDeleteForm->page($serverRequest, $customer, true, 422);
        }

        if (!$deleted) {
            return $this->customerRecords->missing();
        }

        $this->staffNotices->keep($serverRequest, 'Deleted ' . $customer->customerDetails->name . "'s record.");

        return new PageRedirect(Customer::PAGES);
    }

    /**
     * @param Customer $customer A customer.
     *
     * @throws QueryException When the purchases cannot be read.
     *
     * @return bool Whether they bought a vehicle.
     */
    private function bought(Customer $customer): bool
    {
        return $this->boughtVehicles->bought($customer) !== [];
    }
}

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
 * Deleting a customer: the page that asks first, and the delete itself, which leaves a notice for the list and goes
 * there (303).
 */
final readonly class CustomerDeletes
{
    /**
     * @param CustomerRecords          $customerRecords          The route's customer and the 404 and 400 pages.
     * @param CustomerRecordRepository $customerRecordRepository Deletes the customer.
     * @param CustomerDeleteForm       $customerDeleteForm       The delete page.
     * @param StaffSeals               $staffSeals               Checks the form's envelope.
     * @param StaffNotices             $staffNotices             Leaves the delete's notice for the list.
     */
    public function __construct(
        private CustomerRecords $customerRecords,
        private CustomerRecordRepository $customerRecordRepository,
        private CustomerDeleteForm $customerDeleteForm,
        private StaffSeals $staffSeals,
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a customer's delete page, with its session.
     *
     * @throws QueryException When the customer cannot be read.
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageResponse The page that asks first, or 404.
     */
    public function confirm(ServerRequestInterface $serverRequest): PageResponse
    {
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        return $this->customerDeleteForm->page($serverRequest, $customer);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted delete form, with its session.
     *
     * @throws QueryException When the customer cannot be read or deleted.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the list; 400 for an envelope that is not this customer's; or 404, also when the
     *                      customer went meanwhile.
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

        if (!$this->customerRecordRepository->delete($customer->id)) {
            return $this->customerRecords->missing();
        }

        $this->staffNotices->keep($serverRequest, 'Deleted ' . $customer->customerDetails->name . "'s record.");

        return new PageRedirect(Customer::PAGES);
    }
}

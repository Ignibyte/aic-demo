<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Entity\Purchase;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use App\Theme\Components\DetailCard\DetailCard;
use App\Theme\Components\DetailCard\Fact;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A customer's page: the notice its last save left, its card with what staff keep on it, a badge counting what it
 * bought and buttons to edit it, delete it or go back to the list, and the vehicles it bought.
 */
final readonly class CustomerCards
{
    /**
     * @param CustomerRecords $customerRecords The route's customer and the 404 page.
     * @param StaffPanels     $staffPanels     The customer pages' frame.
     * @param BoughtVehicles  $boughtVehicles  What the customer bought.
     * @param StaffNotices    $staffNotices    The last save's notice.
     * @param CustomerTable   $customerTable   The vehicles bought.
     */
    public function __construct(
        private CustomerRecords $customerRecords,
        private StaffPanels $staffPanels,
        private BoughtVehicles $boughtVehicles,
        private StaffNotices $staffNotices = new StaffNotices(),
        private CustomerTable $customerTable = new CustomerTable(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a customer's page, with its session.
     *
     * @throws QueryException When the customer or its purchases cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageResponse The customer's page, or 404.
     */
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        // The notice is taken first, so a save whose customer went before its 303 landed leaves nothing behind.
        $body = $this->staffNotices->take($serverRequest, 'customer-notice');
        $customer = $this->customerRecords->find($serverRequest);

        if (!$customer instanceof Customer) {
            return $this->customerRecords->missing();
        }

        $purchases = $this->boughtVehicles->bought($customer);
        $body['card'] = $this->card($customer, $purchases);
        $body['purchases'] = $this->customerTable->purchases($purchases);
        $name = $customer->customerDetails->name;

        return $this->staffPanels->page($name, 'Customer record', 'What Ridgeway Motors keeps on this customer.', $body, 200);
    }

    /**
     * @param Customer       $customer  A customer.
     * @param list<Purchase> $purchases What it bought.
     *
     * @return DetailCard Its card.
     */
    private function card(Customer $customer, array $purchases): DetailCard
    {
        $customerDetails = $customer->customerDetails;
        $path = $customer->path();
        $statusBadge = $this->customerTable->badge($purchases);

        return new DetailCard('customer-title', $customerDetails->name, $statusBadge, [
            new Fact('Email', $customerDetails->email, false),
            new Fact('Phone', $this->given($customerDetails->phone), false),
            new Fact('Address', $this->given($customerDetails->address), false),
            new Fact('Notes', $this->given($customerDetails->notes), false),
            new Fact('Added', $customer->addedOn, false),
        ], '', [
            new ButtonLink('Edit', $path . '/edit', 'customer-edit', ButtonTone::Primary),
            new ButtonLink('Delete', $path . '/delete', 'customer-delete', ButtonTone::Error),
            new ButtonLink('Back to the list', Customer::PAGES, 'customer-back', ButtonTone::Ghost),
        ]);
    }

    /**
     * @param string $detail A detail that may be empty.
     *
     * @return string It; `None` when it is empty.
     */
    private function given(string $detail): string
    {
        return $detail === ''
            ? 'None'
            : $detail;
    }
}

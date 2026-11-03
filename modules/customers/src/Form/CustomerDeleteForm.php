<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Customers\Entity\Customer;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffDealership;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\ConfirmCard\ConfirmCard;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * A customer's delete page: the question, with the form that deletes the record (its envelope sealing the customer's
 * path) and Keep it, back to the customer; or, for a customer who bought a vehicle, the warning that the record stays,
 * and no form.
 */
final readonly class CustomerDeleteForm
{
    /**
     * @param StaffPanels     $staffPanels     The customer pages' frame.
     * @param StaffSeals      $staffSeals      Seals the customer's path.
     * @param StaffDealership $staffDealership The dealership's name, for a refusal.
     * @param FormTokens      $formTokens      Issues the visitor's guard fields.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private StaffSeals $staffSeals,
        private StaffDealership $staffDealership,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Customer               $customer      The customer.
     * @param bool                   $bought        Whether they bought a vehicle.
     * @param int                    $status        200, or 422 after a refused delete.
     *
     * @throws LogicException   When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     * @throws RuntimeException When the dealership's name cannot be read.
     *
     * @return PageDocument The page that asks first, or the warning.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        Customer $customer,
        bool $bought,
        int $status,
    ): PageDocument {
        $customerDetails = $customer->customerDetails;
        $title = 'Delete ' . $customerDetails->name;

        if ($bought) {
            $message = 'This customer has bought from ' . $this->staffDealership->name() . ', so the record stays.';

            return $this->staffPanels->warning($title, $message, 'customer-bought', 'Back to the customer', $customer->path(), $status);
        }

        $description = "A deleted customer's record is gone for good.";

        return $this->staffPanels->page($title, 'Delete a customer', $description, [
            'confirm' => $this->confirmCard($serverRequest, $customer),
        ], $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Customer               $customer      A customer who bought nothing.
     *
     * @throws LogicException When the request carries no session, or the envelope cannot be sealed.
     *
     * @return ConfirmCard The question, the form that deletes the record, and Keep it, back to the customer.
     */
    private function confirmCard(ServerRequestInterface $serverRequest, Customer $customer): ConfirmCard
    {
        $path = $customer->path();
        $customerDetails = $customer->customerDetails;
        $form = new Form('customer-delete-form', $path . '/delete', [
            $this->staffSeals->field($path),
            new Button('Delete', 'submit', 'customer-delete-confirm'),
        ], $this->formTokens->guard($serverRequest));

        return new ConfirmCard(
            'Delete ' . $customerDetails->name . '?',
            $customerDetails->email,
            "This cannot be undone. Delete this customer's record?",
            $form,
            new ButtonLink('Keep it', $path, 'customer-keep', ButtonTone::Ghost),
        );
    }
}

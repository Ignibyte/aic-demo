<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Customers\Entity\Customer;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\ConfirmCard\ConfirmCard;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A customer's delete page: the question, with the form that deletes the record (its envelope sealing the customer's
 * path) and Keep it, back to the customer.
 */
final readonly class CustomerDeleteForm
{
    /**
     * @param StaffPanels $staffPanels The customer pages' frame.
     * @param StaffSeals  $staffSeals  Seals the customer's path.
     * @param FormTokens  $formTokens  Issues the visitor's CSRF token.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private StaffSeals $staffSeals,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Customer               $customer      The customer.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageDocument The page that asks first.
     */
    public function page(ServerRequestInterface $serverRequest, Customer $customer): PageDocument
    {
        $path = $customer->path();
        $customerDetails = $customer->customerDetails;
        $form = new Form('customer-delete-form', $path . '/delete', [
            $this->staffSeals->field($path),
            new Button('Delete', 'submit', 'customer-delete-confirm'),
        ], $this->formTokens->issue($serverRequest)->value());
        $confirmCard = new ConfirmCard(
            'Delete ' . $customerDetails->name . '?',
            $customerDetails->email,
            "This cannot be undone. Delete this customer's record?",
            $form,
            new ButtonLink('Keep it', $path, 'customer-keep', ButtonTone::Ghost),
        );
        $description = "A deleted customer's record is gone for good.";

        return $this->staffPanels->page('Delete ' . $customerDetails->name, 'Delete a customer', $description, [
            'confirm' => $confirmCard,
        ], 200);
    }
}

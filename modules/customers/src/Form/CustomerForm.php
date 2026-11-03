<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Customers\Entity\Customer;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\FormActions\FormActions;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The customer form's page, to add a customer or to edit one: AIC's Form around the grid of fields, an alert above
 * them when anything was refused, and, when editing, the envelope sealing the customer's path.
 */
final readonly class CustomerForm
{
    /**
     * @param StaffPanels    $staffPanels    The customer pages' frame.
     * @param StaffSeals     $staffSeals     Seals the edited customer's path.
     * @param CustomerFields $customerFields The grid of fields.
     * @param FormTokens     $formTokens     Issues the visitor's guard fields.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private StaffSeals $staffSeals,
        private CustomerFields $customerFields = new CustomerFields(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param CustomerEntry          $customerEntry The values to show.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     * @param string                 $path          The edited customer's path; empty to add a customer.
     * @param int                    $status        200, or 422 after a refusal.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageDocument The form's page: it posts to the list to add, or to the customer to edit, and Cancel goes
     *                      there.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        CustomerEntry $customerEntry,
        array $errors,
        string $path,
        int $status,
    ): PageDocument {
        $editing = $path !== '';
        $action = $editing
            ? $path
            : Customer::PAGES;
        $title = $editing
            ? 'Edit customer'
            : 'Add a customer';
        $alerts = $errors === []
            ? []
            : [new Alert(AlertSeverity::Error, 'Fix the fields marked below.', 'customer-error')];
        $formGrid = $this->customerFields->grid($customerEntry, $errors);
        $formActions = new FormActions('Save', 'customer-save', $action);
        $seal = $editing
            ? [$this->staffSeals->field($path)]
            : [];
        $fields = [...$alerts, ...$seal, $formGrid, $formActions];
        $form = new Form('customer-form', $action, $fields, $this->formTokens->guard($serverRequest));
        $description = 'A name and an email are needed, and no two customers share an email.';

        return $this->staffPanels->page($title, $title, $description, ['form' => $form], $status);
    }
}

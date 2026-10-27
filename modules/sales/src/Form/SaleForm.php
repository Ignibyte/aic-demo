<?php

declare(strict_types=1);

namespace App\Sales\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use App\Sales\Entity\Sale;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\FormActions\FormActions;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The sale form's page: AIC's `Form` (CSRF token and honeypot) posting to the sales list, the alert above the fields
 * when something was refused, the grid, and the save button with Cancel back to the list.
 */
final readonly class SaleForm
{
    /**
     * @param StaffPanels $staffPanels The sale pages' frame.
     * @param SaleFields  $saleFields  The grid of fields.
     * @param FormTokens  $formTokens  Issues the visitor's CSRF token.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private SaleFields $saleFields,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param SaleEntry              $saleEntry     The values to show.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     * @param int                    $status        200, or 422 after a refusal.
     *
     * @throws QueryException When the options cannot be read.
     * @throws LogicException When the request has no session, or a key does not fit the theme.
     *
     * @return PageDocument The form's page.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        SaleEntry $saleEntry,
        array $errors,
        int $status,
    ): PageDocument {
        $alerts = $errors === []
            ? []
            : [new Alert(AlertSeverity::Error, 'Fix the fields marked below.', 'sale-error')];
        $formGrid = $this->saleFields->grid($saleEntry, $errors);
        $formActions = new FormActions('Record the sale', 'sale-save', Sale::PAGES);
        $token = $this->formTokens->issue($serverRequest)->value();
        $form = new Form('sale-form', Sale::PAGES, [...$alerts, $formGrid, $formActions], $token);
        $description = 'Choose the vehicle, the customer and the salesperson. Recording the sale marks the vehicle sold.';

        return $this->staffPanels->page('Record a sale', 'Record a sale', $description, ['form' => $form], $status);
    }
}

<?php

declare(strict_types=1);

namespace App\Holds\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\FormTokens;
use App\Holds\Entity\Hold;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\FormActions\FormActions;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The hold form's page (#20): AIC's `Form` (CSRF token and honeypot) posting to the holds list, the alert above the
 * fields when something was refused, the grid, and the button with Cancel back to the list.
 */
final readonly class HoldForm
{
    /**
     * @param StaffPanels $staffPanels The hold pages' frame.
     * @param HoldFields  $holdFields  The grid of fields.
     * @param FormTokens  $formTokens  Issues the visitor's guard fields.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private HoldFields $holdFields,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param HoldEntry              $holdEntry     The values to show.
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
        HoldEntry $holdEntry,
        array $errors,
        int $status,
    ): PageDocument {
        $alerts = $errors === []
            ? []
            : [new Alert(AlertSeverity::Error, 'Fix the fields marked below.', 'hold-error')];
        $formGrid = $this->holdFields->grid($holdEntry, $errors);
        $formActions = new FormActions('Put on hold', 'hold-save', Hold::PAGES);
        $guardFields = $this->formTokens->guard($serverRequest);
        $form = new Form('hold-form', Hold::PAGES, [...$alerts, $formGrid, $formActions], $guardFields);
        $description = 'Keep a vehicle in stock for a customer for 48 hours. It leaves the public site and sells to no one else.';

        return $this->staffPanels->page('Put a vehicle on hold', 'Put a vehicle on hold', $description, ['form' => $form], $status);
    }
}

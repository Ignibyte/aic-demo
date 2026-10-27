<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Inventory\Entity\Vehicle;
use App\Staff\Form\StaffSeals;
use App\Staff\Service\StaffPanels;
use App\Theme\Components\FormActions\FormActions;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The vehicle form's page, to add a vehicle or to edit one: AIC's Form around the grid of fields, an alert above them
 * when anything was refused, and, when editing, the envelope sealing the vehicle's path.
 */
final readonly class VehicleForm
{
    /**
     * @param StaffPanels   $staffPanels   The vehicle pages' frame.
     * @param StaffSeals    $staffSeals    Seals the edited vehicle's path.
     * @param VehicleFields $vehicleFields The grid of fields.
     * @param FormTokens    $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private StaffPanels $staffPanels,
        private StaffSeals $staffSeals,
        private VehicleFields $vehicleFields = new VehicleFields(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param VehicleEntry           $vehicleEntry  The values to show.
     * @param array<string, string>  $errors        Why each refused field is refused, by name.
     * @param string                 $vin           The edited vehicle's VIN as stored; empty to add a vehicle.
     * @param int                    $status        200, or 422 after a refusal.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageDocument The form's page: it posts to the list to add, or to the vehicle to edit, and Cancel goes there.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        VehicleEntry $vehicleEntry,
        array $errors,
        string $vin,
        int $status,
    ): PageDocument {
        $editing = $vin !== '';
        $path = $editing
            ? Vehicle::PAGES . '/' . $vin
            : Vehicle::PAGES;
        $title = $editing
            ? 'Edit vehicle'
            : 'Add a vehicle';
        $alerts = $errors === []
            ? []
            : [new Alert(AlertSeverity::Error, 'Fix the fields marked below.', 'vehicle-error')];
        $grid = $this->vehicleFields->grid($vehicleEntry, $errors);
        $formActions = new FormActions('Save', 'vehicle-save', $path);
        // Each branch spreads the alerts alone: elephc's build of the site answered 500 when a second list was spread
        // after them (gate:23). No AIC ticket carries this yet.
        $fields = $editing
            ? [...$alerts, $this->staffSeals->field($path), $grid, $formActions]
            : [...$alerts, $grid, $formActions];
        $form = new Form('vehicle-form', $path, $fields, $this->formTokens->issue($serverRequest)->value());
        $description = "Prices are whole dollars. The cost stays off the vehicle's page.";

        return $this->staffPanels->page($title, $title, $description, ['form' => $form], $status);
    }
}

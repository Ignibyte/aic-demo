<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Controller\Domain\PageDocument;
use AIC\Form\Application\FormTokens;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Service\VehiclePanels;
use App\Theme\Components\ConfirmCard\ConfirmCard;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A vehicle's delete page: the question, with the form that deletes it (its envelope sealing the vehicle's path) and
 * Keep it, back to the vehicle; or, for a sold vehicle, the warning that it stays on the record, and no form.
 */
final readonly class VehicleDeleteForm
{
    /**
     * @param VehiclePanels $vehiclePanels The vehicle pages' frame.
     * @param VehicleSeals  $vehicleSeals  Seals the vehicle's path.
     * @param FormTokens    $formTokens    Issues the visitor's CSRF token.
     */
    public function __construct(
        private VehiclePanels $vehiclePanels,
        private VehicleSeals $vehicleSeals,
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Vehicle                $vehicle       The vehicle.
     * @param int                    $status        200, or 422 after a refused delete.
     *
     * @throws LogicException When the request has no session, the envelope cannot be sealed, or a key does not fit the theme.
     *
     * @return PageDocument The page.
     */
    public function page(ServerRequestInterface $serverRequest, Vehicle $vehicle, int $status): PageDocument
    {
        $title = 'Delete the ' . $vehicle->title();

        if ($vehicle->status === VehicleStatus::Sold) {
            $message = 'This vehicle is sold, so it stays on the record and cannot be deleted.';

            return $this->vehiclePanels->warning($title, $message, 'vehicle-sold', 'Back to the vehicle', $vehicle->path(), $status);
        }

        $description = 'A deleted vehicle is gone from the lot for good.';

        return $this->vehiclePanels->page($title, 'Delete a vehicle', $description, [
            'confirm' => $this->confirmCard($serverRequest, $vehicle, $title),
        ], $status);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param Vehicle                $vehicle       A vehicle that is not sold.
     * @param string                 $title         The question's start, `Delete the …`.
     *
     * @throws LogicException When the request carries no session, or the envelope cannot be sealed.
     *
     * @return ConfirmCard The question, the form that deletes it, and Keep it, back to its page.
     */
    private function confirmCard(ServerRequestInterface $serverRequest, Vehicle $vehicle, string $title): ConfirmCard
    {
        $path = $vehicle->path();
        $form = new Form('vehicle-delete-form', $path . '/delete', [
            $this->vehicleSeals->field($path),
            new Button('Delete', 'submit', 'vehicle-delete-confirm'),
        ], $this->formTokens->issue($serverRequest)->value());

        return new ConfirmCard(
            $title . '?',
            'VIN ' . $vehicle->vin,
            'This cannot be undone. Delete this vehicle from the lot?',
            $form,
            new ButtonLink('Keep it', $path, 'vehicle-keep', ButtonTone::Ghost),
        );
    }
}

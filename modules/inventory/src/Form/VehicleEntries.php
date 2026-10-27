<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Form\Application\PostedFields;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleStatus;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fills the vehicle form: from a post, each field as sent (a missing one or an array is empty); from a stored
 * vehicle; or blank, in stock and on the lot from today.
 */
final readonly class VehicleEntries
{
    /**
     * @param PostedFields $postedFields Reads the posted fields.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted vehicle form.
     *
     * @return VehicleEntry What it sent.
     */
    public function posted(ServerRequestInterface $serverRequest): VehicleEntry
    {
        $values = [];

        foreach (VehicleEntry::FIELDS as $name) {
            $values[$name] = $this->postedFields->value($serverRequest, $name);
        }

        return new VehicleEntry($values);
    }

    /**
     * @param Vehicle $vehicle A stored vehicle.
     *
     * @return VehicleEntry Its values, as the edit form starts.
     */
    public function filled(Vehicle $vehicle): VehicleEntry
    {
        return new VehicleEntry([
            'added_on' => $vehicle->addedOn,
            'body_style' => $vehicle->bodyStyle,
            'cost' => (string) $vehicle->price->cost,
            'exterior_colour' => $vehicle->exteriorColour,
            'list_price' => (string) $vehicle->price->list,
            'make' => $vehicle->make,
            'mileage' => (string) $vehicle->mileage,
            'model' => $vehicle->model,
            'status' => $vehicle->status->value,
            'trim' => $vehicle->trim,
            'vin' => $vehicle->vin,
            'year' => (string) $vehicle->year,
        ]);
    }

    /**
     * @param string $today The database's today, `YYYY-MM-DD`.
     *
     * @return VehicleEntry The new form's start: in stock, on the lot from today, everything else empty.
     */
    public function blank(string $today): VehicleEntry
    {
        return new VehicleEntry(['status' => VehicleStatus::InStock->value, 'added_on' => $today]);
    }
}

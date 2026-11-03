<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Form\Application\PostedFields;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleStatus;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fills the vehicle form: from a post, each field as sent (a missing one or an array is empty); from a stored
 * vehicle; from a stored vehicle with a list row's fields as sent (#28); or blank, in stock and on the lot from today.
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
        return $this->sent([], $serverRequest, VehicleEntry::FIELDS);
    }

    /**
     * @param Vehicle                $vehicle       The stored vehicle.
     * @param ServerRequestInterface $serverRequest A list row's save (#28).
     * @param list<string>           $names         The fields the row sends.
     *
     * @return VehicleEntry The stored vehicle's values, with those fields as the row sent them.
     */
    public function row(Vehicle $vehicle, ServerRequestInterface $serverRequest, array $names): VehicleEntry
    {
        return $this->sent($this->filled($vehicle)->values, $serverRequest, $names);
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

    /**
     * @param array<string, string>  $values        The values to start from.
     * @param ServerRequestInterface $serverRequest A post.
     * @param list<string>           $names         The fields it sends.
     *
     * @return VehicleEntry The values, each of those fields as posted.
     */
    private function sent(array $values, ServerRequestInterface $serverRequest, array $names): VehicleEntry
    {
        foreach ($names as $name) {
            $values[$name] = $this->postedFields->value($serverRequest, $name);
        }

        return new VehicleEntry($values);
    }
}

<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Form\Application\FormTokens;
use AIC\Form\Application\PostedFields;
use AIC\Form\Domain\SameOriginPath;
use AIC\Http\Domain\InputParser;
use AIC\Http\Domain\MalformedInputException;
use AIC\Tessera\Domain\TesseraRequest;
use App\Inventory\Entity\BulkAction;
use App\Inventory\Entity\BulkEdit;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Entity\VehicleLocation;
use App\Inventory\Entity\VehicleStatus;
use App\Inventory\Service\VehicleStatuses;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function array_keys;
use function array_slice;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Fills the vehicle form: from a post, each field as sent (a missing one or an array is empty); from a stored
 * vehicle; from a stored vehicle with a list row's fields as sent (#28); or blank, in stock and on the lot from today.
 * It also reads a bulk edit of the list as posted (#29): its choice, its VINs, its confirmation and its way back.
 */
final readonly class VehicleEntries
{
    /**
     * @param PostedFields    $postedFields    Reads the posted fields.
     * @param VehicleStatuses $vehicleStatuses The statuses and the lot's locations a bulk edit may name (#29).
     * @param SameOriginPath  $sameOriginPath  Keeps a bulk edit's way back on this site, else the list.
     * @param FormTokens      $formTokens      The visitor's form token, which a bulk edit's answer posts again.
     * @param InputParser     $inputParser     Reads the query of the list a bulk edit came from.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
        private VehicleStatuses $vehicleStatuses = new VehicleStatuses(),
        private SameOriginPath $sameOriginPath = new SameOriginPath(Vehicle::PAGES),
        private FormTokens $formTokens = new FormTokens(),
        private InputParser $inputParser = new InputParser(),
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
     * @return VehicleEntry Its values, as the edit form starts; its location too (#29), which no form field shows, so a
     *                      row's save gives the saved vehicle back where it stands.
     */
    public function filled(Vehicle $vehicle): VehicleEntry
    {
        return new VehicleEntry([
            'added_on' => $vehicle->lot->addedOn,
            'body_style' => $vehicle->bodyStyle,
            'cost' => (string) $vehicle->price->cost,
            'exterior_colour' => $vehicle->exteriorColour,
            'list_price' => (string) $vehicle->price->list,
            'location' => $vehicle->lot->location instanceof VehicleLocation ? $vehicle->lot->location->value : '',
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
     * @param ServerRequestInterface $serverRequest A bulk edit of the inventory list, posted (#29).
     *
     * @throws LogicException When the request carries no session.
     *
     * @return BulkEdit What it asks: the choice read as an action and its status or location (none for a choice the
     *                  action bar does not offer), the ticked VINs once each, the page's VINs, the confirmation, the
     *                  list it came from (kept only when it is the list's path) and its query, whether Tessera sent it,
     *                  and the visitor's form token.
     */
    public function bulk(ServerRequestInterface $serverRequest): BulkEdit
    {
        $choice = $this->postedFields->value($serverRequest, 'bulk');
        $vehicleStatus = $this->status($this->named($choice, BulkAction::Status));
        $vehicleLocation = $this->vehicleStatuses->location($this->named($choice, BulkAction::Location));
        $back = $this->back($this->sameOriginPath->resolve($this->postedFields->value($serverRequest, 'return')));

        return new BulkEdit(
            $this->action($choice, $vehicleStatus, $vehicleLocation),
            $vehicleStatus,
            $vehicleLocation,
            $this->vins($serverRequest, 'vins'),
            array_slice($this->vins($serverRequest, 'page'), 0, BulkEdit::MOST),
            $this->postedFields->value($serverRequest, 'confirmed') === 'yes',
            $back,
            $this->query($back),
            $serverRequest->getHeaderLine(TesseraRequest::REQUEST) !== '',
            $this->formTokens->issue($serverRequest)->value(),
        );
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
     * @param string $path A same-origin path a bulk edit names as its way back.
     *
     * @return string The path when it is the list's, with or without a query; the list's path otherwise.
     */
    private function back(string $path): string
    {
        return $path === Vehicle::PAGES || str_starts_with($path, Vehicle::PAGES . '?')
            ? $path
            : Vehicle::PAGES;
    }

    /**
     * @param string $back The list's path, with or without a query.
     *
     * @return array<string, string> Its query's plain fields by name, each decoded, as the list reads them; a list or
     *                               a map in it is left out, and a query AIC refuses as malformed gives none.
     */
    private function query(string $back): array
    {
        $mark = strpos($back, '?');
        $query = [];

        if ($mark === false) {
            return $query;
        }

        try {
            $fields = $this->inputParser->fields(substr($back, $mark + 1));
        } catch (MalformedInputException) {
            return $query;
        }

        foreach ($fields as $name => $value) {
            if (!is_string($value)) {
                continue;
            }

            $query[$name] = $value;
        }

        return $query;
    }

    /**
     * @param string           $choice          The action bar's choice, as posted.
     * @param ?VehicleStatus   $vehicleStatus   The status it names, if any.
     * @param ?VehicleLocation $vehicleLocation The location it names, if any.
     *
     * @return BulkAction|null What it does; null for a choice the bar does not offer.
     */
    private function action(
        string $choice,
        ?VehicleStatus $vehicleStatus,
        ?VehicleLocation $vehicleLocation,
    ): ?BulkAction {
        if ($vehicleStatus instanceof VehicleStatus) {
            return BulkAction::Status;
        }

        if ($vehicleLocation instanceof VehicleLocation) {
            return BulkAction::Location;
        }

        return $choice === BulkAction::Delete->value
            ? BulkAction::Delete
            : null;
    }

    /**
     * @param string     $choice     The action bar's choice, as posted (`status:on_hold`).
     * @param BulkAction $bulkAction An action that names a value after its own and a colon.
     *
     * @return string The value it names when the choice is that action's; empty otherwise.
     */
    private function named(string $choice, BulkAction $bulkAction): string
    {
        $prefix = $bulkAction->value . ':';

        return str_starts_with($choice, $prefix)
            ? substr($choice, strlen($prefix))
            : '';
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted bulk edit.
     * @param string                 $name          A list field's name, without its `[]`.
     *
     * @return list<string> Its values that are VINs in form (17 capitals and digits), each once, in the order they came.
     */
    private function vins(ServerRequestInterface $serverRequest, string $name): array
    {
        $vins = [];

        foreach ($this->listed($serverRequest, $name) as $value) {
            if (preg_match('/^[0-9A-Z]{17}$/', $value) !== 1 || in_array($value, $vins, true)) {
                continue;
            }

            $vins[] = $value;
        }

        return $vins;
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted form.
     * @param string                 $name          A list field's name, without its `[]`.
     *
     * @return list<string> Its values in the order they came, each one that is not a string read as empty; none when it
     *                      is absent or not a list.
     */
    private function listed(ServerRequestInterface $serverRequest, string $name): array
    {
        $body = $serverRequest->getParsedBody();
        $posted = is_array($body)
            ? $body[$name] ?? []
            : [];
        $values = [];

        if (!is_array($posted)) {
            return $values;
        }

        // By key, as AIC's own `ServerRequest::withQueryParams()` reads a list, so Psalm narrows each value it keeps.
        foreach (array_keys($posted) as $key) {
            $values[] = is_string($posted[$key])
                ? $posted[$key]
                : '';
        }

        return $values;
    }

    /**
     * @param string $value A status as a bulk edit's choice names it.
     *
     * @return VehicleStatus|null The status; null for none. Sold is read, so the checks can refuse it for every vehicle.
     */
    private function status(string $value): ?VehicleStatus
    {
        foreach ($this->vehicleStatuses->all() as $vehicleStatus) {
            if ($vehicleStatus->value === $value) {
                return $vehicleStatus;
            }
        }

        return null;
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

<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Application\FormTokens;
use AIC\Module\Attribute\Tagged;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Service\VehicleRecords;
use App\Inventory\Service\VehicleSales;
use App\Staff\Form\StaffSeals;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * A vehicle's row in the list, edited in place (#28): the row shown, the row as a form, and the form saved. Each answer
 * is `VehicleRowPages`' page holding that row alone, where the list puts it, so a Tessera request from the row is
 * answered with the row and nothing else. The wiring builds it from the entity store, the envelope codec and the sale plugins,
 * its page first, so its generated handler part does not open as the record forms' part does (gate:14). A save is held to its
 * row's props, checked by the vehicle form's own checks over the stored vehicle with the row's fields put in, and saved
 * as the form saves; a refusal is the row as a form again, 422.
 */
final readonly class VehicleRowEdits
{
    /**
     * The route's vehicle, its repository, and the 404 and 400 pages.
     */
    private VehicleRecords $vehicleRecords;

    /**
     * The vehicle form's checks.
     */
    private VehicleChecks $vehicleChecks;

    /**
     * The row, shown or as a form.
     */
    private VehicleRowForm $vehicleRowForm;

    /**
     * Checks the row's props on a save.
     */
    private StaffSeals $staffSeals;

    /**
     * @param VehicleRowPages $vehicleRowPages The page holding the row, the record's pages and the form's checks.
     * @param EntityStore     $entityStore     The entity store, through which the vehicle is read and saved.
     * @param EnvelopeCodec   $envelopeCodec   Seals with the app secret: the row's props, and their check on a save.
     * @param list<object>    $vehicleSales    The `VehicleSales` plugins, in tag order (`#[Tagged]`).
     * @param VehicleEntries  $vehicleEntries  Fills the form from the vehicle and the row's post.
     * @param FormTokens      $formTokens      Gives the guard fields the page's action bar posts (#29, AIC #246).
     */
    public function __construct(
        private VehicleRowPages $vehicleRowPages,
        EntityStore $entityStore,
        EnvelopeCodec $envelopeCodec,
        #[Tagged(VehicleSales::class)]
        array $vehicleSales,
        private VehicleEntries $vehicleEntries = new VehicleEntries(),
        private FormTokens $formTokens = new FormTokens(),
    ) {
        $this->vehicleRecords = $vehicleRowPages->records($entityStore);
        $this->vehicleChecks = $vehicleRowPages->checks($this->vehicleRecords, $entityStore, $vehicleSales);
        $this->vehicleRowForm = new VehicleRowForm($envelopeCodec);
        $this->staffSeals = new StaffSeals($envelopeCodec);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's row, with its session.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row shown, or 404.
     */
    public function shown(ServerRequestInterface $serverRequest): PageResponse
    {
        $guardFields = $this->formTokens->guard($serverRequest);
        $vehicle = $this->vehicleRecords->find($serverRequest);

        return $vehicle instanceof Vehicle
            ? $this->vehicleRowPages->page($guardFields, $this->vehicleRowForm->shown($vehicle), 200)
            : $this->vehicleRecords->missing();
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for a vehicle's row as a form, with its session.
     *
     * @throws QueryException           When the vehicle cannot be read.
     * @throws UnexpectedValueException When its stored status is no status.
     * @throws LogicException           When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row as a form, filled from the vehicle, or 404.
     */
    public function form(ServerRequestInterface $serverRequest): PageResponse
    {
        $vehicle = $this->vehicleRecords->find($serverRequest);

        if (!$vehicle instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        $guardFields = $this->formTokens->guard($serverRequest);
        $editing = $this->vehicleRowForm->editing($vehicle, $this->vehicleEntries->filled($vehicle), []);

        return $this->vehicleRowPages->page($guardFields, $editing, 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A row's save: its fields, its props, its session.
     *
     * @throws QueryException           When the vehicle cannot be read or written.
     * @throws UnexpectedValueException When a status is no status.
     * @throws LogicException           When the request has no session, a key or a value does not fit, or a region does not fit the theme.
     *
     * @return PageResponse The row shown with what was saved; the row as a form again, 422; 400 for props that are not
     *                      this vehicle's row's; or 404.
     */
    public function save(ServerRequestInterface $serverRequest): PageResponse
    {
        $current = $this->vehicleRecords->find($serverRequest);

        if (!$current instanceof Vehicle) {
            return $this->vehicleRecords->missing();
        }

        if (!$this->staffSeals->rowHolds($serverRequest, VehicleRowForm::ANCHOR . $current->vin, $current->path())) {
            return $this->vehicleRecords->refused();
        }

        $guardFields = $this->formTokens->guard($serverRequest);
        $vehicleRecordRepository = $this->vehicleRecords->repository();
        $vehicleEntry = $this->vehicleEntries->row($current, $serverRequest, VehicleRowForm::FIELDS);
        $errors = $this->vehicleChecks->errors($vehicleEntry, $current->vin, $vehicleRecordRepository->today());

        if ($errors !== []) {
            return $this->vehicleRowPages->page($guardFields, $this->vehicleRowForm->editing($current, $vehicleEntry, $errors), 422);
        }

        $vehicle = $this->vehicleChecks->vehicle($vehicleEntry);

        return $vehicleRecordRepository->update($current->vin, $vehicle)
            ? $this->vehicleRowPages->page($guardFields, $this->vehicleRowForm->shown($vehicle), 200)
            : $this->vehicleRecords->missing();
    }
}

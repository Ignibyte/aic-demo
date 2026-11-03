<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Entity\Application\EntityStore;
use AIC\Module\Attribute\Tagged;
use App\Inventory\Entity\BulkEdit;
use App\Inventory\Entity\BulkOutcome;
use App\Inventory\Entity\Vehicle;
use App\Inventory\Repository\BulkAuditRepository;
use App\Inventory\Repository\VehicleBatchRepository;
use App\Inventory\Repository\VehicleRecordRepository;
use App\Inventory\Service\SoldVehicles;
use App\Inventory\Service\VehicleSales;
use App\Photos\Entity\VehiclePhoto;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffSession;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_diff;
use function count;

/**
 * A bulk edit of the inventory list (#29): set a status, move to a lot location, or delete, for every ticked vehicle at
 * once. One transaction reads the vehicles `FOR UPDATE`, checks every one with the vehicle form's checks, and then
 * writes the change to all of them and queues the sales managers' audit, or, when any vehicle is refused, writes
 * nothing. A delete posted without its confirmation asks first; a deleted vehicle's photo files go once the delete has
 * committed. `VehicleBulkPages` answers. A repeat of a Tessera write is AIC's write ledger's, before this is reached.
 */
final readonly class VehicleBulkEdits
{
    /**
     * The vehicle form's checks, which check each vehicle of a batch.
     */
    private VehicleChecks $vehicleChecks;

    /**
     * The vehicles one at a time, for the database's today, which the checks count from.
     */
    private VehicleRecordRepository $vehicleRecordRepository;

    /**
     * @param VehicleBulkPages       $vehicleBulkPages       The answers.
     * @param VehicleBatchRepository $vehicleBatchRepository The batch's transaction, its vehicles and its writes.
     * @param BulkAuditRepository    $bulkAuditRepository    Delivers the audit to the sales managers.
     * @param EntityStore            $entityStore            The entity store, through which the checks read.
     * @param list<object>           $vehicleSales           The `VehicleSales` plugins, in tag order (`#[Tagged]`).
     * @param VehicleEntries         $vehicleEntries         Reads the post.
     * @param StaffSession           $staffSession           Who is signed in, whom the audit names.
     */
    public function __construct(
        private VehicleBulkPages $vehicleBulkPages,
        private VehicleBatchRepository $vehicleBatchRepository,
        private BulkAuditRepository $bulkAuditRepository,
        EntityStore $entityStore,
        #[Tagged(VehicleSales::class)]
        array $vehicleSales,
        private VehicleEntries $vehicleEntries = new VehicleEntries(),
        private StaffSession $staffSession = new StaffSession(),
    ) {
        $this->vehicleRecordRepository = new VehicleRecordRepository($entityStore);
        $this->vehicleChecks = new VehicleChecks($this->vehicleRecordRepository, new SoldVehicles($entityStore, $vehicleSales));
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted bulk edit, with its session.
     *
     * @throws Throwable When a read or a write fails (the transaction rolled back first), the request has no session, or
     *                   an answer does not fit the theme.
     *
     * @return PageResponse What `VehicleBulkPages` answers: the change written, a delete asking first, or a refusal.
     */
    public function post(ServerRequestInterface $serverRequest): PageResponse
    {
        $bulkEdit = $this->vehicleEntries->bulk($serverRequest);
        $asked = $this->asked($serverRequest, $bulkEdit);

        if ($asked instanceof PageResponse) {
            return $asked;
        }

        $photos = $bulkEdit->deletes()
            ? $this->vehicleBatchRepository->photos($bulkEdit->vins)
            : [];
        $bulkOutcome = new BulkOutcome();
        $vehicleBulkEdits = $this;
        $staffMember = $this->staffSession->member($serverRequest);
        $member = $staffMember instanceof StaffMember
            ? $staffMember->name
            : '';
        $this->vehicleBatchRepository->transaction(static function () use ($vehicleBulkEdits, $bulkEdit, $bulkOutcome, $member): void {
            $vehicleBulkEdits->apply($bulkEdit, $bulkOutcome, $member);
        });

        return $this->answer($serverRequest, $bulkEdit, $bulkOutcome, $photos);
    }

    /**
     * The batch's work, run inside its transaction: it reads the vehicles for update, checks each, and holds what it
     * found; when none is refused it writes the change to all of them and queues the audit.
     *
     * @param BulkEdit    $bulkEdit    A bulk edit that can be applied as posted, confirmed when it deletes.
     * @param BulkOutcome $bulkOutcome Holds the vehicles read and the refusals, for after the commit.
     * @param string      $member      Who made it, by name, for the audit.
     *
     * @throws Throwable When a read, a write or a job's queueing fails; the transaction is then rolled back.
     */
    public function apply(BulkEdit $bulkEdit, BulkOutcome $bulkOutcome, string $member): void
    {
        $vehicles = $this->vehicleBatchRepository->locked($bulkEdit->vins);
        $refusals = $this->refusals($bulkEdit, $vehicles);
        $bulkOutcome->hold($vehicles, $refusals);

        if ($refusals !== []) {
            return;
        }

        $this->vehicleBatchRepository->apply($bulkEdit);
        $this->bulkAuditRepository->send($bulkEdit, $vehicles, $member);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted bulk edit, with its session.
     * @param BulkEdit               $bulkEdit      What it asks.
     *
     * @throws Throwable When the vehicles cannot be read, or the answer cannot be built.
     *
     * @return PageResponse|null The answer before anything is written: a post refused as it stands, or a delete asking
     *                           first; null when the edit goes on to its transaction.
     */
    private function asked(ServerRequestInterface $serverRequest, BulkEdit $bulkEdit): ?PageResponse
    {
        if ($bulkEdit->problem() !== '') {
            return $this->vehicleBulkPages->refused($serverRequest, $bulkEdit, $this->ticked($bulkEdit), []);
        }

        return $bulkEdit->deletes() && !$bulkEdit->confirmed
            ? $this->vehicleBulkPages->confirm($serverRequest, $bulkEdit, $this->ticked($bulkEdit))
            : null;
    }

    /**
     * @param BulkEdit $bulkEdit A bulk edit.
     *
     * @throws Throwable When the vehicles cannot be read.
     *
     * @return list<Vehicle> The vehicles that have its VINs; none when it has none, or more than a page.
     */
    private function ticked(BulkEdit $bulkEdit): array
    {
        $vins = $bulkEdit->vins;

        return $vins === [] || count($vins) > BulkEdit::MOST
            ? []
            : $this->vehicleBatchRepository->vehicles($vins);
    }

    /**
     * @param ServerRequestInterface $serverRequest The bulk edit's request, with its session.
     * @param BulkEdit               $bulkEdit      What it asked.
     * @param BulkOutcome            $bulkOutcome   What its transaction found.
     * @param list<VehiclePhoto>     $vehiclePhotos A delete's photos, read before it; none for another edit.
     *
     * @throws Throwable When the vehicles or the list cannot be read, a file cannot be forgotten, or the answer cannot
     *                   be built.
     *
     * @return PageResponse The refusal; or, the change written, the rows as they are now, a delete's photo files gone.
     */
    private function answer(
        ServerRequestInterface $serverRequest,
        BulkEdit $bulkEdit,
        BulkOutcome $bulkOutcome,
        array $vehiclePhotos,
    ): PageResponse {
        $before = $bulkOutcome->vehicles();

        if ($bulkOutcome->refusals() !== []) {
            return $this->vehicleBulkPages->refused($serverRequest, $bulkEdit, $before, $bulkOutcome->refusals());
        }

        if (!$bulkEdit->deletes()) {
            $after = $this->vehicleBatchRepository->vehicles($bulkEdit->vins);

            return $this->vehicleBulkPages->applied($serverRequest, $bulkEdit, $before, $after);
        }

        $this->vehicleBatchRepository->forget($vehiclePhotos);

        return $this->vehicleBulkPages->applied($serverRequest, $bulkEdit, $before, []);
    }

    /**
     * @param BulkEdit      $bulkEdit A bulk edit that can be applied as posted.
     * @param list<Vehicle> $vehicles The vehicles that have its VINs, read for update.
     *
     * @throws Throwable When the database's today, a taken VIN or a sale cannot be read.
     *
     * @return array<string, string> Why each refused VIN is refused, by the vehicle form's checks, or as no longer on
     *                               the lot when no vehicle has it; empty when every vehicle may take the edit.
     */
    private function refusals(BulkEdit $bulkEdit, array $vehicles): array
    {
        $today = $this->vehicleRecordRepository->today();
        $refusals = [];
        $found = [];

        foreach ($vehicles as $vehicle) {
            $found[] = $vehicle->vin;
            $error = $this->vehicleChecks->bulkError($bulkEdit, $vehicle, $today);

            if ($error === '') {
                continue;
            }

            $refusals[$vehicle->vin] = $error;
        }

        return $this->missing($bulkEdit, $found, $refusals);
    }

    /**
     * @param BulkEdit              $bulkEdit A bulk edit.
     * @param list<string>          $found    The VINs of the vehicles found for it.
     * @param array<string, string> $refusals Why each refused vehicle was refused, by VIN.
     *
     * @return array<string, string> Those refusals, and each of its VINs no vehicle has, refused as no longer on the lot.
     */
    private function missing(BulkEdit $bulkEdit, array $found, array $refusals): array
    {
        foreach (array_diff($bulkEdit->vins, $found) as $vin) {
            $refusals[$vin] = VehicleChecks::NOT_LISTED;
        }

        return $refusals;
    }
}

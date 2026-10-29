<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\PostgresQueue;
use App\Inventory\Entity\ListedVehicle;
use App\Inventory\Repository\TodayRepository;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\LeadReply;
use App\Leads\Form\LeadChecks;
use App\Leads\Form\LeadEntries;
use App\Leads\Form\LeadForms;
use App\Leads\Repository\LeadRepository;
use App\Settings\Service\DealershipProfiles;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Where the public side hands over a car's leads (#18): the two blank lead panels for a car's page, and a sent form
 * checked, recorded for the salesperson whose turn it is, linked to the customer with that email, and its
 * `lead-notices` job queued. The request sends no mail; the job does, when `bin/aic queue:work` runs it.
 */
final readonly class LeadDesk
{
    /**
     * The job that tells the salesperson and the visitor, as the manifest names it.
     */
    public const string JOB = 'lead-notices';

    /**
     * @param PostgresQueue      $postgresQueue      Queues its job.
     * @param TodayRepository    $todayRepository    The database's today, which a test drive must come after.
     * @param LeadRepository     $leadRepository     Records the lead.
     * @param DealershipProfiles $dealershipProfiles The opening hours a test drive is held to (#22).
     * @param LeadForms          $leadForms          The panels.
     * @param LeadChecks         $leadChecks         Checks what was posted.
     * @param LeadEntries        $leadEntries        Reads it.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
        private TodayRepository $todayRepository,
        private LeadRepository $leadRepository,
        private DealershipProfiles $dealershipProfiles,
        private LeadForms $leadForms = new LeadForms(),
        private LeadChecks $leadChecks = new LeadChecks(),
        private LeadEntries $leadEntries = new LeadEntries(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request for the car's page, with its session.
     * @param string                 $path          The car's public page.
     *
     * @throws LogicException When the request carries no session, or a key or an anchor is malformed.
     * @throws QueryException When the dealership's profile cannot be read.
     *
     * @return list<KeyedComponent> Both lead panels, blank.
     */
    public function panels(ServerRequestInterface $serverRequest, string $path): array
    {
        return $this->leadForms->blank($serverRequest, $path, $this->dealershipProfiles->profile()->openingHours);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     * @param LeadKind               $leadKind      Which form was sent.
     * @param ListedVehicle          $listedVehicle The car, in stock.
     * @param string                 $path          Its public page.
     *
     * @throws Throwable When the leads cannot be read or written, the job cannot be queued, the request carries no
     *                   session, or a value or a key does not fit.
     *
     * @return LeadReply The panels and 200 when the lead was recorded; 422 with the errors when a field was refused.
     */
    public function send(
        ServerRequestInterface $serverRequest,
        LeadKind $leadKind,
        ListedVehicle $listedVehicle,
        string $path,
    ): LeadReply {
        $openingHours = $this->dealershipProfiles->profile()->openingHours;
        $leadEntry = $this->leadEntries->posted($serverRequest);
        $errors = $this->leadChecks->errors($leadKind, $leadEntry, $this->todayRepository->today(), $openingHours);

        if ($errors !== []) {
            return new LeadReply($this->leadForms->answered($serverRequest, $path, $leadKind, $leadEntry, $errors, $openingHours), 422);
        }

        $leadDetails = $this->leadChecks->details($leadKind, $leadEntry);
        $customerId = $this->leadRepository->customerId($leadDetails->email);
        $id = $this->leadRepository->record($listedVehicle->id, $leadDetails, $customerId);
        $this->postgresQueue->dispatch(self::JOB, ['lead' => (string) $id], 0);
        $sent = $this->leadEntries->sent($leadDetails);

        return new LeadReply($this->leadForms->answered($serverRequest, $path, $leadKind, $sent, [], $openingHours), 200);
    }
}

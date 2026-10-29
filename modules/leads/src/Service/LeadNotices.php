<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\Notification;
use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\Seller;
use JsonException;

use function implode;

/**
 * Tells people about a lead (#18), through AIC's Notifier, which queues one `notify` job per channel: the salesperson
 * it is assigned to gets a notification kept in the database and mailed, and the visitor a confirmation by mail. An
 * unassigned lead tells the visitor alone.
 */
final readonly class LeadNotices
{
    /**
     * @param Notifier $notifier Queues each delivery.
     */
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    /**
     * @param Lead $lead The lead, as stored.
     *
     * @throws JsonException  When a delivery's payload cannot be encoded.
     * @throws QueryException When a delivery cannot be queued.
     */
    public function send(Lead $lead): void
    {
        $seller = $lead->seller;

        if ($seller instanceof Seller) {
            $this->notifier->send($this->forSeller($lead, $seller), ['database', 'mail']);
        }

        $this->notifier->send($this->forVisitor($lead), ['mail']);
    }

    /**
     * @param Lead   $lead   The lead.
     * @param Seller $seller Its salesperson.
     *
     * @return Notification Who sent what about which car, and where the lead's page is.
     */
    private function forSeller(Lead $lead, Seller $seller): Notification
    {
        $leadDetails = $lead->leadDetails;
        $subject = $leadDetails->leadKind === LeadKind::Inquiry
            ? 'New question about the ' . $lead->leadCar->title
            : 'New test drive request for the ' . $lead->leadCar->title;
        $lines = [
            $leadDetails->name . ' sent this through the ' . $lead->leadCar->title . "'s page, and it is yours to answer.",
            '',
            'Email: ' . $leadDetails->email,
            'Phone: ' . ($leadDetails->phone === '' ? 'not given' : $leadDetails->phone),
        ];

        if ($leadDetails->leadKind === LeadKind::TestDrive) {
            $lines[] = 'Test drive: ' . $leadDetails->driveOn . ' at ' . $leadDetails->driveAt;
        }

        $lines[] = 'Message: ' . ($leadDetails->message === '' ? 'none' : $leadDetails->message);
        $lines[] = '';
        $lines[] = 'Open the lead: ' . $lead->path();

        return new Notification((string) $seller->id, $seller->email, $subject, implode("\n", $lines), $lead->path());
    }

    /**
     * @param Lead $lead The lead.
     *
     * @return Notification The visitor's confirmation of what they sent; it has no user, since visitors never sign in.
     */
    private function forVisitor(Lead $lead): Notification
    {
        $leadDetails = $lead->leadDetails;
        $title = $lead->leadCar->title;

        if ($leadDetails->leadKind === LeadKind::Inquiry) {
            $body = 'Hello ' . $leadDetails->name . ",\n\nThanks for asking about the " . $title . '. One of our salespeople has your'
                . " question and will reply to this address soon.\n\nRidgeway Motors";

            return new Notification('', $leadDetails->email, 'Your question about the ' . $title, $body);
        }

        $body = 'Hello ' . $leadDetails->name . ",\n\nThanks for asking to drive the " . $title . ' on ' . $leadDetails->driveOn
            . ' at ' . $leadDetails->driveAt . '. One of our salespeople will call you to confirm it.' . "\n\nRidgeway Motors";

        return new Notification('', $leadDetails->email, 'Your test drive of the ' . $title, $body);
    }
}

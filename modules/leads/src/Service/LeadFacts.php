<?php

declare(strict_types=1);

namespace App\Leads\Service;

use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\Seller;
use App\Theme\Components\DetailCard\ButtonLink;
use App\Theme\Components\DetailCard\ButtonTone;
use App\Theme\Components\DetailCard\DetailCard;
use App\Theme\Components\DetailCard\Fact;
use App\Theme\Components\StatusBadge\BadgeTone;
use App\Theme\Components\StatusBadge\StatusBadge;

use function number_format;

/**
 * A lead's card on its page (#18): what the visitor sent and when, the car (its title the link to its inventory page,
 * with its VIN and list price), the salesperson and the customer its email matched (linked to their page), and whether it is open.
 */
final readonly class LeadFacts
{
    /**
     * @param Lead $lead The lead.
     *
     * @return DetailCard Its facts, its status, and links to its car and its customer.
     */
    public function card(Lead $lead): DetailCard
    {
        $statusBadge = $lead->open
            ? new StatusBadge('Open', BadgeTone::Success, 'lead-status')
            : new StatusBadge('Closed', BadgeTone::Neutral, 'lead-status');

        return new DetailCard('lead-title', $lead->leadDetails->name, $statusBadge, $this->facts($lead), '', $this->actions($lead));
    }

    /**
     * @param Lead $lead The lead.
     *
     * @return list<Fact> What the visitor sent, then the car, the salesperson and the customer.
     */
    private function facts(Lead $lead): array
    {
        $leadDetails = $lead->leadDetails;
        $facts = [
            new Fact('Kind', $leadDetails->leadKind->label(), false),
            new Fact('Received', $lead->receivedOn, false),
            new Fact('Name', $leadDetails->name, false),
            new Fact('Email', $leadDetails->email, false),
            new Fact('Phone', $this->given($leadDetails->phone, 'Not given'), false),
        ];

        if ($leadDetails->leadKind === LeadKind::TestDrive) {
            $facts[] = new Fact('Test drive', $leadDetails->driveOn . ' at ' . $leadDetails->driveAt, false);
        }

        return [
            ...$facts,
            new Fact('Message', $this->given($leadDetails->message, 'None'), false),
            new Fact('Car', $lead->leadCar->title, false),
            new Fact('VIN', $lead->leadCar->vin, true),
            new Fact('List price', '$' . number_format($lead->leadCar->listPrice), false),
            new Fact('Salesperson', $this->sellerName($lead), false),
            new Fact('Customer', $this->given($lead->customerName, 'No customer has this email'), false),
        ];
    }

    /**
     * @param Lead $lead The lead.
     *
     * @return list<ButtonLink> To the car, to the customer when there is one, and back to the list.
     */
    private function actions(Lead $lead): array
    {
        $actions = [new ButtonLink($lead->leadCar->title, '/app/inventory/' . $lead->leadCar->vin, 'lead-car', ButtonTone::Primary)];

        if ($lead->customerId !== 0) {
            $actions[] = new ButtonLink('Open the customer', '/app/customers/' . $lead->customerId, 'lead-customer', ButtonTone::Secondary);
        }

        $actions[] = new ButtonLink('Back to the leads', Lead::PAGES, 'lead-back', ButtonTone::Ghost);

        return $actions;
    }

    /**
     * @param Lead $lead The lead.
     *
     * @return string Its salesperson's name, or that it has none.
     */
    private function sellerName(Lead $lead): string
    {
        $seller = $lead->seller;

        return $seller instanceof Seller
            ? $seller->name
            : 'Unassigned';
    }

    /**
     * @param string $detail A detail that may be empty.
     * @param string $none   What to show when it is.
     *
     * @return string The detail, or what stands for it.
     */
    private function given(string $detail, string $none): string
    {
        return $detail === ''
            ? $none
            : $detail;
    }
}

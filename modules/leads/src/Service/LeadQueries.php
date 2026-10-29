<?php

declare(strict_types=1);

namespace App\Leads\Service;

use App\Leads\Entity\LeadKind;
use App\Leads\Entity\Seller;
use App\Leads\Form\LeadQuery;
use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the leads list's parameters (#18): `kind` and `salesperson`. A kind that is not one, or an email that is no
 * salesperson's, falls back to every lead, as the other lists' unknown values do.
 */
final readonly class LeadQueries
{
    /**
     * @param StaffRequests $staffRequests Reads a query parameter.
     */
    public function __construct(
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     * @param list<Seller>           $sellers       The salespeople.
     *
     * @return LeadQuery What the list keeps.
     */
    public function read(ServerRequestInterface $serverRequest, array $sellers): LeadQuery
    {
        $kind = $this->staffRequests->query($serverRequest, 'kind');
        $known = $kind === LeadKind::Inquiry->value || $kind === LeadKind::TestDrive->value;
        $email = $this->staffRequests->query($serverRequest, 'salesperson');
        $seller = '';

        foreach ($sellers as $candidate) {
            if ($candidate->email !== $email) {
                continue;
            }

            $seller = $email;
        }

        return new LeadQuery($known ? $kind : '', $seller);
    }
}

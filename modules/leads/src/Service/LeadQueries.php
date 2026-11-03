<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Component\Domain\Tab;
use AIC\Component\Domain\Tabs;
use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\Seller;
use App\Leads\Form\LeadQuery;
use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the leads list's parameters (#18): `kind` and `salesperson`. A kind that is not one, or an email that is no
 * salesperson's, falls back to every lead, as the other lists' unknown values do. `view=board` asks for the board
 * (#30) in place of the list; any other view is the list. The tabs between the two link to each.
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
     * @param ServerRequestInterface $serverRequest The leads' request.
     *
     * @return bool Whether it asks for the board: its `view` is `board`.
     */
    public function board(ServerRequestInterface $serverRequest): bool
    {
        return $this->staffRequests->query($serverRequest, 'view') === 'board';
    }

    /**
     * @param bool $board Whether the board is the one shown.
     *
     * @return Tabs The tabs between the list and the board; neither link is under `/app/leads/`.
     */
    public function views(bool $board): Tabs
    {
        return new Tabs([new Tab('List', Lead::PAGES, !$board), new Tab('Board', Lead::BOARD, $board)], '', 'Lead views');
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

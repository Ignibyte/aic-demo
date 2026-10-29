<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Database\Contracts\QueryException;
use App\Leads\Form\LeadFilters;
use App\Leads\Repository\LeadRepository;
use App\Leads\Repository\SellerRepository;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The open leads, newest first (#18): a filter bar for the kind and the salesperson, a plain GET form that answers the
 * content region in place, and the table of the newest 50 the filter keeps, with how many it keeps in all.
 */
final readonly class LeadList
{
    /**
     * The most leads the table shows.
     */
    private const int SHOWN = 50;

    /**
     * @param LeadRepository   $leadRepository   The leads.
     * @param SellerRepository $sellerRepository The salespeople the filter offers.
     * @param LeadQueries      $leadQueries      Reads the filter.
     * @param LeadFilters      $leadFilters      The filter bar.
     * @param OpenLeads        $openLeads        The table.
     * @param StaffNotices     $staffNotices     A close's notice.
     */
    public function __construct(
        private LeadRepository $leadRepository,
        private SellerRepository $sellerRepository,
        private LeadQueries $leadQueries = new LeadQueries(),
        private LeadFilters $leadFilters = new LeadFilters(),
        private OpenLeads $openLeads = new OpenLeads(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its session.
     *
     * @throws QueryException When the leads cannot be read.
     * @throws LogicException When the request carries no session, or a key or a type is malformed.
     *
     * @return Panel The list's panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $sellers = $this->sellerRepository->all();
        $leadQuery = $this->leadQueries->read($serverRequest, $sellers);
        $count = $this->leadRepository->countOpen($leadQuery->kind, $leadQuery->seller);
        $leads = $this->leadRepository->open($leadQuery->kind, $leadQuery->seller, self::SHOWN);
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'leads-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('filters', $this->leadFilters->filterBar($leadQuery, $sellers));
        $body[] = new KeyedComponent('table', $this->openLeads->table($leads, $count));
        $description = 'Questions and test drive requests from the public car pages, newest first';

        return new Panel('leads', 'Open leads', $description, true, $body);
    }
}

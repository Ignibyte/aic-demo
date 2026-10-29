<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use AIC\Database\Contracts\QueryException;
use App\Sales\Entity\Sale;
use App\Sales\Repository\SaleChoiceRepository;
use App\Sales\Repository\SaleRepository;
use App\Staff\Service\StaffDealership;
use App\Staff\Service\StaffNotices;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function intdiv;

/**
 * The sales list's panel: the notice a save left, a link to record a sale, the filters, the totals of the sales they
 * keep, the table of the page asked for (15 sales a page, newest first; a page past the last shows the first) and,
 * past one page, the pages.
 */
final readonly class SaleList
{
    /**
     * The sales on a page.
     */
    private const int PAGE = 15;

    /**
     * @param SaleRepository       $saleRepository       The sales.
     * @param SaleChoiceRepository $saleChoiceRepository The staff the salesperson filter offers.
     * @param StaffDealership      $staffDealership      The dealership's name, for the panel's line.
     * @param SaleQueries          $saleQueries          Reads what the list is asked for.
     * @param SaleFilters          $saleFilters          The filters and the totals.
     * @param SaleTable            $saleTable            The table and the pages.
     * @param StaffNotices         $staffNotices         The last save's notice.
     */
    public function __construct(
        private SaleRepository $saleRepository,
        private SaleChoiceRepository $saleChoiceRepository,
        private StaffDealership $staffDealership,
        private SaleQueries $saleQueries = new SaleQueries(),
        private SaleFilters $saleFilters = new SaleFilters(),
        private SaleTable $saleTable = new SaleTable(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The list's request, with its query parameters and session.
     *
     * @throws QueryException When the sales cannot be read.
     * @throws LogicException When the request carries no session, or a key is malformed.
     *
     * @return Panel The list, in a wide panel.
     */
    public function panel(ServerRequestInterface $serverRequest): Panel
    {
        $months = $this->saleRepository->months();
        $staff = $this->saleChoiceRepository->staff();
        $saleQuery = $this->saleQueries->read($serverRequest, $months, $staff);
        $saleTotals = $this->saleRepository->totals($saleQuery);
        $pages = intdiv($saleTotals->count + self::PAGE - 1, self::PAGE);
        $page = $this->shown($saleQuery->page, $pages);
        $sales = $this->saleRepository->page($saleQuery, self::PAGE, ($page - 1) * self::PAGE);
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'sales-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        $body[] = new KeyedComponent('record', new Link('Record a sale', Sale::PAGES . '/new'));
        $body[] = new KeyedComponent('filters', $this->saleFilters->filterBar($saleQuery, $months, $staff));
        $body[] = new KeyedComponent('totals', $this->saleFilters->totals($saleTotals));
        $body[] = new KeyedComponent('table', $this->saleTable->table($saleQuery, $sales, $saleTotals->count, $page, $pages));

        if ($pages > 1) {
            $body[] = new KeyedComponent('pages', $this->saleTable->pagination($saleQuery, $page, $pages));
        }

        $line = 'What ' . $this->staffDealership->name() . ' sold, by month and salesperson, with the totals';

        return new Panel('sales', 'Sales', $line, true, $body);
    }

    /**
     * @param int $asked The page asked for, from 1.
     * @param int $pages How many pages there are.
     *
     * @return int The page to show: the one asked for, or the first when it is past the last.
     */
    private function shown(int $asked, int $pages): int
    {
        return $asked <= $pages
            ? $asked
            : 1;
    }
}

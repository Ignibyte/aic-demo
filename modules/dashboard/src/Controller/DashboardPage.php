<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Dashboard\Repository\DashboardRepository;
use App\Dashboard\Service\DashboardCharts;
use App\Dashboard\Service\DashboardFeed;
use App\Dashboard\Service\DashboardTiles;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffSession;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function rawurlencode;

/**
 * GET /app: the dashboard, the first page signed-in staff see. The header greets the member by name and role; under it
 * come this month's figures as stat tiles, the sales recorded last, then sales by month, inventory by make and revenue
 * by salesperson as charts. The tiles and the latest sales stay live over `/app/events` (#10), which the page opens
 * with the sales' fingerprint it was drawn with. The prefix is protected, so an anonymous visitor never reaches it; a
 * session without the member goes to sign in.
 */
final readonly class DashboardPage implements Page
{
    /**
     * @param StaffPages          $staffPages          The staff pages' document.
     * @param DashboardRepository $dashboardRepository The figures.
     * @param DashboardCharts     $dashboardCharts     The chart panels.
     * @param DashboardTiles      $dashboardTiles      The stat tiles.
     * @param DashboardFeed       $dashboardFeed       The latest sales.
     * @param StaffSession        $staffSession        Who is signed in.
     */
    public function __construct(
        private StaffPages $staffPages,
        private DashboardRepository $dashboardRepository,
        private DashboardCharts $dashboardCharts,
        private DashboardTiles $dashboardTiles = new DashboardTiles(),
        private DashboardFeed $dashboardFeed = new DashboardFeed(),
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     *
     * @throws QueryException           When the figures cannot be read.
     * @throws LogicException           When a key does not fit the theme, or the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The dashboard, or 303 to sign in.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $staffMember = $this->staffSession->member($serverRequest);

        if ($staffMember === null) {
            return new PageRedirect('/sign-in');
        }

        // The fingerprint is read before the figures, so a sale recorded between the reads makes the stream patch at
        // once instead of going unseen.
        $stream = '/app/events?seen=' . rawurlencode($this->dashboardRepository->fingerprint());
        $latest = $this->dashboardRepository->latest(DashboardFeed::LIMIT);
        $items = [
            new KeyedComponent(DashboardTiles::KEY, $this->dashboardTiles->tiles($this->dashboardRepository->tiles())),
            new KeyedComponent(DashboardFeed::KEY, $this->dashboardFeed->panel($stream, $latest)),
            new KeyedComponent('sales', $this->dashboardCharts->sales()),
            new KeyedComponent('makes', $this->dashboardCharts->makes()),
            new KeyedComponent('salespeople', $this->dashboardCharts->salespeople()),
        ];

        return $this->staffPages->page(
            'Welcome, ' . $staffMember->name,
            $items,
            200,
            $staffMember->role->label() . ' at Ridgeway Motors',
            'Dashboard',
        );
    }
}

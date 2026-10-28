<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use App\Dashboard\Service\DashboardCharts;
use App\Dashboard\Service\DashboardLive;
use App\Dashboard\Service\DashboardPoll;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffSession;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app: the dashboard, the first page signed-in staff see. The header greets the member by name and role; under it
 * come this month's figures as stat tiles and the sales recorded last, then sales by month, inventory by make and
 * revenue by salesperson as charts. Each part is built from its source when it is rendered (#14, AIC #192), so a
 * Tessera answer for one part reads that part's figures alone. The tiles and the latest sales sit in one live part,
 * which Tessera refreshes from this page every five seconds (#12); it seals its version in its props, and a refresh of
 * it alone whose version still holds answers 204 before anything is built (AIC #193). The prefix is protected, so an
 * anonymous visitor never reaches it; a session without the member goes to sign in.
 */
final readonly class DashboardPage implements Page
{
    /**
     * @param StaffPages      $staffPages      The staff pages' document.
     * @param DashboardLive   $dashboardLive   The tiles and the latest sales, and what their figures are now.
     * @param DashboardPoll   $dashboardPoll   The live part's poll: its 204, and the part with its version sealed.
     * @param DashboardCharts $dashboardCharts The chart panels.
     * @param StaffSession    $staffSession    Who is signed in.
     */
    public function __construct(
        private StaffPages $staffPages,
        private DashboardLive $dashboardLive,
        private DashboardPoll $dashboardPoll,
        private DashboardCharts $dashboardCharts,
        private StaffSession $staffSession = new StaffSession(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff, maybe a refresh of the
     *                                              live part carrying its props.
     *
     * @throws QueryException           When the figures cannot be read.
     * @throws JsonException            When the live part's props cannot be sealed.
     * @throws LogicException           When a key does not fit the theme, or the request carries no session.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The dashboard; 204 for a refresh of the live part whose figures have not changed; 400 when
     *                      its props were altered; or 303 to sign in.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $staffMember = $this->staffSession->member($serverRequest);

        if ($staffMember === null) {
            return new PageRedirect('/sign-in');
        }

        $version = $this->dashboardLive->version();
        $answer = $this->dashboardPoll->answer($serverRequest, $version);

        if ($answer instanceof PageResponse) {
            return $answer;
        }

        return $this->staffPages->page(
            'Welcome, ' . $staffMember->name,
            [$this->dashboardPoll->part($version), ...$this->dashboardCharts->parts()],
            200,
            $staffMember->role->label() . ' at Ridgeway Motors',
            'Dashboard',
        );
    }
}

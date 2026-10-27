<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Repository\DashboardRepository;
use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;

use function preg_match;

/**
 * Reads a request to the dashboard's stream into its source (#10): what the client saw is `Last-Event-ID` when it is a
 * fingerprint, else the `seen` parameter when it is one, else nothing; `polls`, from 1 to 60, bounds the looks, and
 * anything else leaves them endless.
 */
final readonly class DashboardSources
{
    /**
     * A fingerprint as `DashboardRepository::fingerprint()` writes it.
     */
    private const string FINGERPRINT = '/^[0-9]+-[0-9]+-[0-9]+$/';

    /**
     * A number of looks `polls` may ask for: 1 to 99, written without a leading 0; above `MOST_POLLS` it is refused.
     */
    private const string POLLS = '/^[1-9][0-9]?$/';

    /**
     * The most looks `polls` may ask for.
     */
    private const int MOST_POLLS = 60;

    /**
     * @param DashboardRepository $dashboardRepository The sales and the figures.
     * @param DashboardTiles      $dashboardTiles      The tiles a patch carries.
     * @param DashboardFeed       $dashboardFeed       The feed's table a patch carries.
     * @param StaffRequests       $staffRequests       Reads the query.
     */
    public function __construct(
        private DashboardRepository $dashboardRepository,
        private DashboardTiles $dashboardTiles = new DashboardTiles(),
        private DashboardFeed $dashboardFeed = new DashboardFeed(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to the stream, with `Last-Event-ID` on a reconnect.
     *
     * @return DashboardSource Its events, from what the client saw.
     */
    public function source(ServerRequestInterface $serverRequest): DashboardSource
    {
        $seen = $this->fingerprint($serverRequest->getHeaderLine('Last-Event-ID'));

        if ($seen === '') {
            $seen = $this->fingerprint($this->staffRequests->query($serverRequest, 'seen'));
        }

        $polls = $this->staffRequests->query($serverRequest, 'polls');
        $looks = preg_match(self::POLLS, $polls) === 1 && (int) $polls <= self::MOST_POLLS
            ? (int) $polls
            : -1;

        return new DashboardSource($this->dashboardRepository, $this->dashboardTiles, $this->dashboardFeed, $seen, $looks);
    }

    /**
     * Takes the text through a native `string` parameter, as `VehicleRecords::vin()` does: elephc refuses `preg_match()`
     * on a value it does not know to be a string (gate:23). No AIC ticket carries this yet.
     *
     * @param string $text A header's or a parameter's text.
     *
     * @return string The text when it is a fingerprint; else empty.
     */
    private function fingerprint(string $text): string
    {
        return preg_match(self::FINGERPRINT, $text) === 1
            ? $text
            : '';
    }
}

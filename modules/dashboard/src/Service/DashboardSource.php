<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\QueryException;
use AIC\Tessera\Domain\TesseraEvents;
use App\Dashboard\Repository\DashboardRepository;
use InvalidArgumentException;
use Override;

use function usleep;

/**
 * The live dashboard's events (#10). It looks at the sales once a second; when their fingerprint differs from the one
 * the client last saw, it patches the tiles, then the feed's table under the new fingerprint as the event's id, so a
 * client that reconnects with `Last-Event-ID` resumes from it, and one that dropped between the two patches gets both
 * again. A look that finds nothing new gives a keep-alive. It never ends unless it was given a number of looks, after
 * which a last keep-alive ends it and AIC closes the stream.
 */
final class DashboardSource implements EventSource
{
    /**
     * Microseconds between looks.
     */
    private const int PAUSE = 1_000_000;

    /**
     * The feed's patch, due after the tiles; null when none is due.
     */
    private ?ServerEvent $serverEvent = null;

    /**
     * Whether the first look has been made; it is made at once, the later ones after a pause.
     */
    private bool $looked = false;

    /**
     * @param DashboardRepository $dashboardRepository The sales and the figures.
     * @param DashboardTiles      $dashboardTiles      The tiles a patch carries.
     * @param DashboardFeed       $dashboardFeed       The feed's table a patch carries.
     * @param string              $seen                The fingerprint the client last saw; empty for none.
     * @param int                 $looks               How many looks are left; -1 for no end.
     * @param TesseraEvents       $tesseraEvents       Builds Tessera's events.
     */
    public function __construct(
        private readonly DashboardRepository $dashboardRepository,
        private readonly DashboardTiles $dashboardTiles,
        private readonly DashboardFeed $dashboardFeed,
        private string $seen,
        private int $looks,
        private readonly TesseraEvents $tesseraEvents = new TesseraEvents(),
    ) {
    }

    /**
     * @throws QueryException           When the sales or the figures cannot be read.
     * @throws InvalidArgumentException When a target or a key is malformed.
     *
     * @return ServerEvent The feed's patch when it is due; else the last keep-alive when the looks are spent; else the
     *                     next look's tiles patch or keep-alive.
     */
    #[Override]
    public function next(): ServerEvent
    {
        $serverEvent = $this->serverEvent;

        if ($serverEvent instanceof ServerEvent) {
            $this->serverEvent = null;

            return $serverEvent;
        }

        if ($this->looks === 0) {
            return $this->tesseraEvents->keepAlive(true);
        }

        return $this->look();
    }

    /**
     * @throws QueryException           When the sales or the figures cannot be read.
     * @throws InvalidArgumentException When a target or a key is malformed.
     *
     * @return ServerEvent The tiles' patch, with the feed's queued behind it, when the sales changed; else a keep-alive.
     */
    private function look(): ServerEvent
    {
        if ($this->looked) {
            usleep(self::PAUSE);
        }

        $this->looked = true;

        if ($this->looks > 0) {
            $this->looks -= 1;
        }

        $fingerprint = $this->dashboardRepository->fingerprint();

        if ($fingerprint === $this->seen) {
            return $this->tesseraEvents->keepAlive(false);
        }

        $this->seen = $fingerprint;
        $table = $this->dashboardFeed->table($this->dashboardRepository->latest(DashboardFeed::LIMIT));
        $this->serverEvent = $this->tesseraEvents->patchElements(DashboardFeed::TARGET, $table, 'inner', $fingerprint, false);
        $tiles = $this->dashboardTiles->tiles($this->dashboardRepository->tiles());

        return $this->tesseraEvents->patchElements(DashboardTiles::TARGET, $tiles, 'inner', '', false);
    }
}

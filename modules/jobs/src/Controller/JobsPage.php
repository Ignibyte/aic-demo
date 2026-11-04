<?php

declare(strict_types=1);

namespace App\Jobs\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use App\Jobs\Service\JobsAccess;
use App\Jobs\Service\JobsIntro;
use App\Jobs\Service\JobsLive;
use App\Jobs\Service\JobsPoll;
use App\Staff\Service\StaffPages;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * GET /app/jobs (#35): AIC's queue for an admin. An intro panel says what the page shows and names the console that
 * works the queue, with the notice of a retry or a discard when one was kept; under it the live part holds what waits,
 * runs or retries and what failed, newest first, which Tessera refreshes every five seconds. Every answer reads the
 * queue once for the part's version; a refresh of the part alone whose version still holds answers 204 before the
 * part is built. The notice is taken only when the whole page is built: a refresh of the part alone that the queue
 * has changed builds the intro without it, as its answer sends the part alone. Anyone but an admin gets 403.
 */
final readonly class JobsPage implements Page
{
    /**
     * @param StaffPages $staffPages The staff pages' document.
     * @param JobsAccess $jobsAccess Admins only.
     * @param JobsLive   $jobsLive   The live part and its version.
     * @param JobsPoll   $jobsPoll   A refresh's 204 and 400.
     * @param JobsIntro  $jobsIntro  The intro panel.
     */
    public function __construct(
        private StaffPages $staffPages,
        private JobsAccess $jobsAccess,
        private JobsLive $jobsLive,
        private JobsPoll $jobsPoll,
        private JobsIntro $jobsIntro = new JobsIntro(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff, maybe a refresh of the
     *                                              live part carrying its props.
     *
     * @throws QueryException           When the queue cannot be read.
     * @throws JsonException            When the live part's props cannot be sealed.
     * @throws LogicException           When the request carries no session, or a key or the status does not fit the
     *                                  theme.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse The jobs page; 204 for a refresh of the live part whose version holds; 400 when its props
     *                      were altered; 403 for anyone but an admin.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $refused = $this->jobsAccess->guard($serverRequest);

        if ($refused !== null) {
            return $refused;
        }

        $version = $this->jobsLive->version();
        $answer = $this->jobsPoll->answer($serverRequest, $version);

        if ($answer instanceof PageResponse) {
            return $answer;
        }

        $intro = $this->jobsPoll->partAlone($serverRequest)
            ? $this->jobsIntro->withoutNotice()
            : $this->jobsIntro->panel($serverRequest);

        return $this->staffPages->page('Jobs', [$intro, $this->jobsLive->part($serverRequest, $version)], 200);
    }
}

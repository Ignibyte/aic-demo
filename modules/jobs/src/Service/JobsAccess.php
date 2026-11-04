<?php

declare(strict_types=1);

namespace App\Jobs\Service;

use AIC\Controller\Domain\PageDocument;
use App\Staff\Entity\Role;
use App\Staff\Entity\StaffMember;
use App\Staff\Service\StaffPages;
use App\Staff\Service\StaffPanels;
use App\Staff\Service\StaffRequests;
use App\Staff\Service\StaffSession;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function preg_match;

/**
 * Who may see and change the jobs (#35): an admin alone. A sales manager or a salesperson is refused with 403 in the
 * staff frame at every jobs route, a GET or a POST, before the queue is read or changed. The session's role is the
 * users table's, which staff's `StaffStanding` refreshes on each request. Also the failed job a route's id names, and
 * the jobs' other refusals: no failed job has the id (404), and a refresh whose props were altered (400).
 */
final readonly class JobsAccess
{
    /**
     * The jobs page, where a refusal's link and a change's redirect go.
     */
    public const string PAGE = '/app/jobs';

    /**
     * The panel id the refusals show under.
     */
    private const string PANEL = 'jobs';

    /**
     * @param StaffPages    $staffPages    The staff pages' document.
     * @param StaffSession  $staffSession  Who is signed in.
     * @param StaffRequests $staffRequests Reads the route's id.
     */
    public function __construct(
        private StaffPages $staffPages,
        private StaffSession $staffSession = new StaffSession(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request, with its session.
     *
     * @throws LogicException           When the request carries no session, or the status does not fit the theme.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageDocument|null Null for an admin, who may go on; the 403 page for anyone else.
     */
    public function guard(ServerRequestInterface $serverRequest): ?PageDocument
    {
        $staffMember = $this->staffSession->member($serverRequest);

        if ($staffMember instanceof StaffMember && $staffMember->role === Role::Admin) {
            return null;
        }

        $message = 'Only an admin can see and change the jobs.';

        return $this->warning('Not allowed', $message, 'jobs-refused', 'Back to the dashboard', '/app', 403);
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a failed job's route.
     *
     * @return int The failed job's id the route names; 0 when it is not 1 to 999,999,999.
     */
    public function failedId(ServerRequestInterface $serverRequest): int
    {
        $id = $this->staffRequests->attribute($serverRequest, 'id');

        return preg_match('/^[1-9][0-9]{0,8}$/', $id) === 1
            ? (int) $id
            : 0;
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument 404: no failed job has the id.
     */
    public function missing(): PageDocument
    {
        $message = 'No failed job has that number. It may have been retried or discarded already.';

        return $this->warning('Failed job not found', $message, 'failed-missing', 'Back to the jobs', self::PAGE, 404);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument 400: a refresh of the lists carried altered props.
     */
    public function altered(): PageDocument
    {
        $message = 'The jobs could not be refreshed. Reload the page.';

        return $this->warning('Jobs', $message, 'jobs-altered', 'Reload the jobs', self::PAGE, 400);
    }

    /**
     * @param string $title    The top bar's title and the panel's heading.
     * @param string $message  What went wrong.
     * @param string $id       The notice's id.
     * @param string $back     The link's text.
     * @param string $backHref Where it goes.
     * @param int    $status   The page's status.
     *
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page: the message as a warning, and the link.
     */
    private function warning(
        string $title,
        string $message,
        string $id,
        string $back,
        string $backHref,
        int $status,
    ): PageDocument {
        return (new StaffPanels($this->staffPages, self::PANEL))->warning($title, $message, $id, $back, $backHref, $status);
    }
}

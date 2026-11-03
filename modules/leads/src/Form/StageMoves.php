<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Form\Application\PostedFields;
use AIC\Queue\Application\PostgresQueue;
use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadStage;
use App\Leads\Repository\LeadMoveRepository;
use App\Leads\Service\LeadAccess;
use App\Leads\Service\LeadBoard;
use App\Leads\Service\LeadCards;
use App\Staff\Service\StaffNotices;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * A lead moved to another stage (#30), the one flow for the board's two stage routes and the lead page's close (#18),
 * which is a move to Lost: 303 to the list with its notice, 409 for a lead closed already. A move
 * locks the lead's row, writes its stage and status, records the move and, for Won with a salesperson, queues the
 * `lead-won` job, all in one transaction. With script the board's `PATCH` is answered with the board page, which AIC
 * answers with the two columns the action names, the moved card taking focus; without script the `POST` is answered 303
 * to the board with a notice. A lead that is not there answers 404, one the viewer may not move 403, and a stage that is
 * none of the five 422.
 */
final readonly class StageMoves
{
    /**
     * Why a move is refused when its stage is none of the board's.
     */
    public const string NO_STAGE = "Choose one of the board's stages.";

    /**
     * The job a move to Won queues.
     */
    private const string JOB = 'lead-won';

    /**
     * The board comes first so the stage routes' generated handler part does not open as the lead pages' part does,
     * with the lead cards' services (gate:14).
     *
     * @param LeadBoard          $leadBoard          The board, which answers a move.
     * @param LeadMoveRepository $leadMoveRepository Moves the lead and records it.
     * @param PostgresQueue      $postgresQueue      Queues the Won job, through the move's connection.
     * @param LeadCards          $leadCards          Finds the route's lead, and the 404 and 403 pages.
     * @param LeadAccess         $leadAccess         Who moves it, and whether they may.
     * @param StaffNotices       $staffNotices       Leaves a move's notice.
     * @param PostedFields       $postedFields       Reads the posted stage.
     */
    public function __construct(
        private LeadBoard $leadBoard,
        private LeadMoveRepository $leadMoveRepository,
        private PostgresQueue $postgresQueue,
        private LeadCards $leadCards,
        private LeadAccess $leadAccess = new LeadAccess(),
        private StaffNotices $staffNotices = new StaffNotices(),
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The board's move, with its `stage`, its session and, from Tessera,
     *                                              the two columns it names.
     *
     * @throws Throwable When the lead cannot be read or written, the job cannot be queued, the request carries no
     *                   session, or a key or a type does not fit; the move's transaction is rolled back first.
     *
     * @return PageResponse The board, 200, built with the moved lead; 422, 403 or 404.
     */
    public function patch(ServerRequestInterface $serverRequest): PageResponse
    {
        $lead = $this->held($serverRequest);

        if (!$lead instanceof Lead) {
            return $lead;
        }

        $moved = $this->moveTo($serverRequest, $lead);

        return $moved instanceof LeadStage
            ? $this->leadBoard->page($serverRequest, $lead->id, '', 200)
            : $moved;
    }

    /**
     * @param ServerRequestInterface $serverRequest A card's form posted without script, with its `stage` and session.
     *
     * @throws Throwable When the lead cannot be read or written, the job cannot be queued, the request carries no
     *                   session, or a key or a type does not fit; the move's transaction is rolled back first.
     *
     * @return PageResponse 303 to the board with a notice; 422, 403 or 404.
     */
    public function post(ServerRequestInterface $serverRequest): PageResponse
    {
        $lead = $this->held($serverRequest);

        if (!$lead instanceof Lead) {
            return $lead;
        }

        $moved = $this->moveTo($serverRequest, $lead);

        if (!$moved instanceof LeadStage) {
            return $moved;
        }

        $this->staffNotices->keep($serverRequest, 'Moved the lead from ' . $lead->leadDetails->name . ' to ' . $moved->label() . '.');

        return new PageRedirect(Lead::BOARD);
    }

    /**
     * @param ServerRequestInterface $serverRequest The lead page's close form, with its session.
     *
     * @throws Throwable When the lead cannot be read or written, the request carries no session, or a key, a type or a
     *                   status does not fit; the move's transaction is rolled back first.
     *
     * @return PageResponse 303 to the list with a notice; 409, 403 or 404.
     */
    public function close(ServerRequestInterface $serverRequest): PageResponse
    {
        $lead = $this->held($serverRequest);

        if (!$lead instanceof Lead) {
            return $lead;
        }

        if (!$lead->stage->open()) {
            return $this->leadCards->closed($lead);
        }

        $this->write($serverRequest, $lead, LeadStage::Lost);
        $this->staffNotices->keep($serverRequest, 'Closed the lead from ' . $lead->leadDetails->name . '.');

        return new PageRedirect(Lead::PAGES);
    }

    /**
     * Moves the lead in one transaction: the lead's row locked, its stage and status written, the move recorded and, for
     * Won with a salesperson, the `lead-won` job queued through the same connection, so all of it commits or none. A lead
     * gone meanwhile, or at that stage already, is left as it is and nothing is written.
     *
     * @param ServerRequestInterface $serverRequest The request, with its session: who moves it.
     * @param Lead                   $lead          The lead, as read before the lock.
     * @param LeadStage              $leadStage     Where it goes.
     *
     * @throws Throwable When the lead cannot be read or written, or the job cannot be queued; the transaction is rolled
     *                   back first.
     */
    public function write(ServerRequestInterface $serverRequest, Lead $lead, LeadStage $leadStage): void
    {
        $boardViewer = $this->leadAccess->viewer($serverRequest);
        $payload = $leadStage === LeadStage::Won && $lead->seller !== null
            ? ['by' => $boardViewer->name, 'lead' => (string) $lead->id]
            : [];
        $this->leadMoveRepository->transaction($this->work($lead->id, $leadStage, $boardViewer->userId, $payload));
    }

    /**
     * @param ServerRequestInterface $serverRequest The board's move, with its `stage`.
     * @param Lead                   $lead          The route's lead, which the viewer may move.
     *
     * @throws Throwable When the lead cannot be read or written, the job cannot be queued, or a key or a type does not
     *                   fit; the move's transaction is rolled back first.
     *
     * @return LeadStage|PageResponse The stage it moved to; the board, 422, for a stage that is none of the board's.
     */
    private function moveTo(ServerRequestInterface $serverRequest, Lead $lead): LeadStage|PageResponse
    {
        $leadStage = LeadStage::New->stored($this->postedFields->value($serverRequest, 'stage'));

        if (!$leadStage instanceof LeadStage) {
            return $this->leadBoard->page($serverRequest, 0, self::NO_STAGE, 422);
        }

        $this->write($serverRequest, $lead, $leadStage);

        return $leadStage;
    }

    /**
     * The move's transaction: the lead moved under its lock, and the Won job queued when there is one to queue and the
     * lead moved.
     *
     * @param int                   $leadId    The lead's id.
     * @param LeadStage             $leadStage Where it goes.
     * @param int                   $userId    Who moves it; 0 for no user.
     * @param array<string, string> $payload   The Won job's payload; empty for no job.
     *
     * @return Closure(): void The work `transaction()` runs.
     */
    private function work(int $leadId, LeadStage $leadStage, int $userId, array $payload): Closure
    {
        $leadMoveRepository = $this->leadMoveRepository;
        $postgresQueue = $this->postgresQueue;

        return static function () use ($leadMoveRepository, $postgresQueue, $leadId, $leadStage, $userId, $payload): void {
            $stageMove = $leadMoveRepository->moveLocked($leadId, $leadStage, $userId);

            if ($stageMove === null || $payload === []) {
                return;
            }

            $postgresQueue->dispatch(self::JOB, $payload, 0);
        };
    }

    /**
     * @param ServerRequestInterface $serverRequest A request naming a lead by its route's `id`, with its session.
     *
     * @throws Throwable When the lead cannot be read, the request carries no session, or a key or a type does not fit.
     *
     * @return Lead|PageResponse The route's lead when whoever is signed in may move it; else why not: 404 or 403.
     */
    private function held(ServerRequestInterface $serverRequest): Lead|PageResponse
    {
        $lead = $this->leadCards->find($serverRequest);

        if (!$lead instanceof Lead) {
            return $this->leadCards->missing();
        }

        return $this->leadAccess->mayMove($serverRequest, $lead)
            ? $lead
            : $this->leadCards->refused($lead);
    }
}

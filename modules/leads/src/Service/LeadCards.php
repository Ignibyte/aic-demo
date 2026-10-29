<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use App\Leads\Entity\Lead;
use App\Leads\Form\LeadActions;
use App\Leads\Repository\LeadRepository;
use App\Leads\Repository\SellerRepository;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPanels;
use App\Staff\Service\StaffRequests;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

use function ctype_digit;
use function strlen;

/**
 * A lead's page (#18): its card, and, while it is open and for someone who may move it, the reassign and close forms.
 * Also the pages that refuse: no such lead (404), not yours to move (403) and already closed (409).
 */
final readonly class LeadCards
{
    /**
     * @param LeadRepository   $leadRepository   The lead.
     * @param SellerRepository $sellerRepository The salespeople the reassign form offers.
     * @param StaffPanels      $staffPanels      The lead's pages' frame.
     * @param LeadAccess       $leadAccess       Who may move it.
     * @param LeadActions      $leadActions      The reassign and close forms.
     * @param LeadFacts        $leadFacts        Its card.
     * @param StaffNotices     $staffNotices     A reassign's notice.
     * @param StaffRequests    $staffRequests    Reads the route's id.
     */
    public function __construct(
        private LeadRepository $leadRepository,
        private SellerRepository $sellerRepository,
        private StaffPanels $staffPanels,
        private LeadAccess $leadAccess = new LeadAccess(),
        private LeadActions $leadActions = new LeadActions(),
        private LeadFacts $leadFacts = new LeadFacts(),
        private StaffNotices $staffNotices = new StaffNotices(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request naming a lead by its route's `id`.
     *
     * @throws QueryException           When the lead cannot be read.
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return Lead|null The lead; null when the id is malformed or names none.
     */
    public function find(ServerRequestInterface $serverRequest): ?Lead
    {
        $id = $this->staffRequests->attribute($serverRequest, 'id');

        return $id !== '' && strlen($id) <= 18 && ctype_digit($id)
            ? $this->leadRepository->find((int) $id)
            : null;
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, with its session.
     * @param Lead                   $lead          The lead.
     * @param string                 $error         Why a reassign was refused; empty when none was.
     * @param int                    $status        200, or 422 after a refused reassign.
     *
     * @throws QueryException           When the salespeople cannot be read.
     * @throws LogicException           When the request carries no session, or a key, a type or the status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageDocument The lead's page.
     */
    public function page(ServerRequestInterface $serverRequest, Lead $lead, string $error, int $status): PageDocument
    {
        $body = $this->staffNotices->take($serverRequest, 'lead-notice');
        $body['card'] = $this->leadFacts->card($lead);

        if ($lead->open && $this->leadAccess->mayMove($serverRequest, $lead)) {
            $body['assign'] = $this->leadActions->assign($serverRequest, $lead, $this->sellerRepository->all(), $error);
            $body['close'] = $this->leadActions->close($serverRequest, $lead);
        }

        $title = $lead->leadDetails->leadKind->label() . ' from ' . $lead->leadDetails->name;

        return $this->staffPanels->page($title, 'Lead', 'What a visitor sent from a public car page.', $body, $status);
    }

    /**
     * @throws InvalidArgumentException When a key or the status does not fit.
     *
     * @return PageDocument 404: no lead has that id.
     */
    public function missing(): PageDocument
    {
        $message = 'No lead has that number.';

        return $this->staffPanels->warning('Lead not found', $message, 'lead-missing', 'Back to the leads', Lead::PAGES, 404);
    }

    /**
     * @param Lead $lead The lead.
     *
     * @throws InvalidArgumentException When a key or the status does not fit.
     *
     * @return PageDocument 403: only its salesperson or a manager may move it.
     */
    public function refused(Lead $lead): PageDocument
    {
        $message = 'Only the salesperson this lead is assigned to, or a manager, may reassign or close it.';

        return $this->staffPanels->warning('Not your lead', $message, 'lead-refused', 'Back to the lead', $lead->path(), 403);
    }

    /**
     * @param Lead $lead The lead.
     *
     * @throws InvalidArgumentException When a key or the status does not fit.
     *
     * @return PageDocument 409: a closed lead is not moved.
     */
    public function closed(Lead $lead): PageDocument
    {
        $message = 'This lead is closed already.';

        return $this->staffPanels->warning('Lead closed', $message, 'lead-closed', 'Back to the lead', $lead->path(), 409);
    }
}

<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageDocument;
use AIC\Controller\Domain\PageRedirect;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Form\Application\PostedFields;
use App\Leads\Entity\Lead;
use App\Leads\Repository\LeadRepository;
use App\Leads\Repository\SellerRepository;
use App\Staff\Service\StaffNotices;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * A lead reassigned (#18), by its salesperson or a manager: it answers 303 to the lead's page with a notice. A lead
 * that is not there answers 404, someone else's 403 and a closed one 409; a reassign to someone who is not a
 * salesperson shows the page again as 422. A close is a move to Lost on the board (#30), so it is `StageMoves`'.
 */
final readonly class LeadMoves
{
    /**
     * Why a reassign is refused.
     */
    private const string NO_SELLER = 'Choose a salesperson on the list.';

    /**
     * @param LeadRepository   $leadRepository   Writes the lead.
     * @param SellerRepository $sellerRepository The salespeople a lead may go to.
     * @param LeadCards        $leadCards        Finds it, and its pages.
     * @param LeadAccess       $leadAccess       Who may move it.
     * @param StaffNotices     $staffNotices     Leaves the reassign's notice.
     * @param PostedFields     $postedFields     Reads the chosen salesperson.
     */
    public function __construct(
        private LeadRepository $leadRepository,
        private SellerRepository $sellerRepository,
        private LeadCards $leadCards,
        private LeadAccess $leadAccess = new LeadAccess(),
        private StaffNotices $staffNotices = new StaffNotices(),
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted reassign form, with its session.
     *
     * @throws QueryException           When the lead cannot be read or written.
     * @throws EntityNotFoundException  When the lead went meanwhile.
     * @throws LogicException           When the request carries no session, or a key, a type or a status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageResponse 303 to the lead; 422, 409, 403 or 404.
     */
    public function assign(ServerRequestInterface $serverRequest): PageResponse
    {
        $lead = $this->held($serverRequest);

        if ($lead instanceof PageDocument) {
            return $lead;
        }

        $email = $this->postedFields->value($serverRequest, 'salesperson');

        foreach ($this->sellerRepository->all() as $seller) {
            if ($seller->email === $email) {
                $this->leadRepository->assign($lead->id, $seller->id);
                $this->staffNotices->keep($serverRequest, 'Assigned this lead to ' . $seller->name . '.');

                return new PageRedirect($lead->path());
            }
        }

        return $this->leadCards->page($serverRequest, $lead, self::NO_SELLER, 422);
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws QueryException           When the lead cannot be read.
     * @throws LogicException           When the request carries no session, or a key, a type or a status does not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return Lead|PageDocument The route's lead when whoever is signed in may move it; else why not: 404, 403 or 409.
     */
    private function held(ServerRequestInterface $serverRequest): Lead|PageDocument
    {
        $lead = $this->leadCards->find($serverRequest);

        if (!$lead instanceof Lead) {
            return $this->leadCards->missing();
        }

        if (!$this->leadAccess->mayMove($serverRequest, $lead)) {
            return $this->leadCards->refused($lead);
        }

        return $lead->stage->open()
            ? $lead
            : $this->leadCards->closed($lead);
    }
}

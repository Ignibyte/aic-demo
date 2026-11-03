<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Domain\PageDocument;
use App\Leads\Form\BoardColumns;
use App\Leads\Repository\LeadRepository;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffPages;
use App\Theme\Components\LeadBoard\LeadBoard as BoardGrid;
use App\Theme\Components\Notice\Notice;
use App\Theme\Components\Notice\NoticeTone;
use App\Theme\Components\Panel\Panel;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;

/**
 * The leads board's page (#30): the staff page `Leads` with the list's panel key, holding a move's notice, the tabs
 * between the list and the board, and the board, its five columns each keyed by its stage, anchored, and built from a
 * source (`BoardColumns`), so a Tessera answer that names two columns reads those two alone. A move's answer is this
 * page, built with the lead that moved, whose card takes focus in its new column. It builds its staff page from the
 * theme's documents itself, as `LeadRepository` builds its salespeople, so the stage routes' generated wiring stays
 * small enough for their three handlers to share one part (gate:14).
 */
final readonly class LeadBoard
{
    /**
     * The board's stream, which the board opens while it is on the page.
     */
    public const string STREAM = '/app/leads/events';

    /**
     * The board's anchor.
     */
    public const string ANCHOR = 'leads-board';

    /**
     * The staff pages' document, built from the theme's documents as the staff pages build it.
     */
    private StaffPages $staffPages;

    /**
     * @param PageDocuments  $pageDocuments  The theme's documents.
     * @param LeadRepository $leadRepository The leads the columns read.
     * @param BoardColumns   $boardColumns   The board's columns.
     * @param StaffNotices   $staffNotices   A move's notice.
     * @param LeadQueries    $leadQueries    The tabs between the list and the board.
     */
    public function __construct(
        PageDocuments $pageDocuments,
        private LeadRepository $leadRepository,
        private BoardColumns $boardColumns = new BoardColumns(),
        private StaffNotices $staffNotices = new StaffNotices(),
        private LeadQueries $leadQueries = new LeadQueries(),
    ) {
        $this->staffPages = new StaffPages($pageDocuments);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, from a signed-in member of staff.
     * @param int                    $moved         The lead that just moved, whose card takes focus; 0 for none.
     * @param string                 $refusal       Why a move was refused; empty when none was.
     * @param int                    $status        200, or 422 after a refused move.
     *
     * @throws LogicException           When the request carries no session, or a key, an anchor or the status does
     *                                  not fit.
     * @throws UnexpectedValueException When the kept role is no role.
     *
     * @return PageDocument The board.
     */
    public function page(ServerRequestInterface $serverRequest, int $moved, string $refusal, int $status): PageDocument
    {
        $body = [];

        foreach ($this->staffNotices->take($serverRequest, 'leads-notice') as $key => $notice) {
            $body[] = new KeyedComponent($key, $notice);
        }

        if ($refusal !== '') {
            $body[] = new KeyedComponent('refusal', new Notice($refusal, NoticeTone::Warning, 'leads-refusal'));
        }

        $leadBoard = new BoardGrid(self::STREAM, $this->boardColumns->columns($this->leadRepository, $serverRequest, $moved));
        $body[] = new KeyedComponent('views', $this->leadQueries->views(true));
        $body[] = new KeyedComponent('board', $leadBoard, '', self::ANCHOR);
        $description = 'Every lead by where it stands; move a card to change its stage';
        $panel = new Panel('leads', 'Leads board', $description, true, $body);

        return $this->staffPages->page('Leads', [new KeyedComponent('leads', $panel)], $status);
    }
}

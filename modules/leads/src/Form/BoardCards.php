<?php

declare(strict_types=1);

namespace App\Leads\Form;

use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\KeyedComponent;
use App\Leads\Entity\BoardViewer;
use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadStage;
use App\Leads\Entity\Seller;
use App\Theme\Components\BoardColumn\BoardColumn;
use App\Theme\Components\BoardStack\BoardStack;
use App\Theme\Components\CardMove\CardMove;
use App\Theme\Components\LeadCard\CardMenu;
use App\Theme\Components\LeadCard\LeadCard;
use App\Theme\Components\LeadCard\MoveChoice;
use InvalidArgumentException;

/**
 * A leads board column from its leads (#30), the one shape the board page, a move's answer and the board's stream all
 * render: the column, its stack (heading, count, cards), and on each card a viewer may move its "Move to" menu, one
 * form per other stage posting to the lead's stage route with the viewer's guard fields
 * (their form token and the honeypot, AIC #246).
 */
final readonly class BoardCards
{
    /**
     * @param LeadStage   $leadStage   The column's stage.
     * @param list<Lead>  $leads       The leads it shows, newest first.
     * @param int         $count       How many leads are at the stage.
     * @param BoardViewer $boardViewer Who looks, which decides the menus.
     * @param GuardFields $guardFields The viewer's form token and honeypot, which each menu's forms carry.
     * @param int         $moved       The lead that just moved here, whose menu takes focus; 0 for none.
     *
     * @throws InvalidArgumentException When a card's key is malformed.
     *
     * @return BoardColumn The column.
     */
    public function column(
        LeadStage $leadStage,
        array $leads,
        int $count,
        BoardViewer $boardViewer,
        GuardFields $guardFields,
        int $moved,
    ): BoardColumn {
        return new BoardColumn(
            $leadStage->anchor(),
            $leadStage->value,
            $this->stack($leadStage, $leads, $count, $boardViewer, $guardFields, $moved),
        );
    }

    /**
     * @param LeadStage   $leadStage   The column's stage.
     * @param list<Lead>  $leads       The leads it shows, newest first.
     * @param int         $count       How many leads are at the stage.
     * @param BoardViewer $boardViewer Who looks, which decides the menus.
     * @param GuardFields $guardFields The viewer's form token and honeypot.
     * @param int         $moved       The lead that just moved here; 0 for none.
     *
     * @throws InvalidArgumentException When a card's key is malformed.
     *
     * @return BoardStack Its heading, count and cards, which the stream sends as the column's children.
     */
    public function stack(
        LeadStage $leadStage,
        array $leads,
        int $count,
        BoardViewer $boardViewer,
        GuardFields $guardFields,
        int $moved,
    ): BoardStack {
        $cards = [];

        foreach ($leads as $lead) {
            $cardMenu = $this->menu($lead, $leadStage, $guardFields, $lead->id === $moved);
            $cards[] = new KeyedComponent('lead-' . $lead->id, $this->card($lead, $boardViewer->mayMove($lead), $cardMenu));
        }

        return new BoardStack($leadStage->anchor(), $leadStage->label(), $count, $cards);
    }

    /**
     * @param Lead     $lead     The lead.
     * @param bool     $movable  Whether the viewer may move it.
     * @param CardMenu $cardMenu Its menu, shown only when they may.
     *
     * @return LeadCard Its card.
     */
    private function card(Lead $lead, bool $movable, CardMenu $cardMenu): LeadCard
    {
        $seller = $lead->seller;

        return new LeadCard(
            $lead->id,
            $lead->path(),
            $lead->leadDetails->name,
            $lead->leadCar->title,
            $lead->leadDetails->leadKind->label(),
            $seller instanceof Seller ? $seller->name : 'Unassigned',
            $lead->receivedOn,
            $movable,
            $cardMenu,
        );
    }

    /**
     * @param Lead        $lead        The lead.
     * @param LeadStage   $leadStage   The stage it is at.
     * @param GuardFields $guardFields The viewer's form token and honeypot.
     * @param bool        $focus       Whether it just moved here.
     *
     * @throws InvalidArgumentException When a form's key is malformed.
     *
     * @return CardMenu Its menu: a guarded form for every other stage, in board order, keyed by the stage.
     */
    private function menu(Lead $lead, LeadStage $leadStage, GuardFields $guardFields, bool $focus): CardMenu
    {
        $action = $lead->path() . '/stage';
        $from = $leadStage->anchor();
        $moves = [];

        foreach ($leadStage->board() as $stage) {
            if ($stage === $leadStage) {
                continue;
            }

            $moveChoice = new MoveChoice($stage->value, $stage->label(), $stage->anchor());
            $moves[] = new KeyedComponent($stage->value, new CardMove($lead->id, $action, $from, $moveChoice, $guardFields));
        }

        return new CardMenu($action, $from, $focus, $moves);
    }
}

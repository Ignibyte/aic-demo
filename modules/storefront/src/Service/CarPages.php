<?php

declare(strict_types=1);

namespace App\Storefront\Service;

use AIC\Component\Domain\KeyedComponent;
use AIC\Controller\Domain\PageDocument;
use App\Chat\Service\ChatDesk;
use App\Inventory\Entity\ListedVehicle;
use App\Leads\Entity\LeadKind;
use App\Leads\Service\LeadDesk;
use App\Theme\Components\PanelGrid\PanelGrid;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

/**
 * A car's public page (#17, #18): its photos, details and price, with finance's payment calculator under the price at
 * the dealership's APR (#26), and below them the two lead forms, "Ask about this car" and "Book a test drive", which
 * leads' `LeadDesk` builds and records. The page reads the dealership's profile once, for the APR and the frame. Shown
 * to a GET with both forms blank, and answering a sent form with that form thanked (200) or refused (422) in its
 * panel. A car that is not in stock, or a slug that is not its own, answers 404 for a post as for the page. The page's
 * chat dock (#32) is the start form for this car, or the visitor's open chat; a start posted below the car answers
 * the car's page with the dock open (200), or the start form again with its reason (422).
 */
final readonly class CarPages
{
    /**
     * @param StorePages  $storePages  The public pages' document.
     * @param CarFinder   $carFinder   Finds the car the slug names.
     * @param CarProfiles $carProfiles The car's profile.
     * @param LeadDesk    $leadDesk    The lead forms, and a sent one recorded.
     * @param ChatDesk    $chatDesk    A chat started on the car's page (#32).
     * @param CarSlugs    $carSlugs    The car's path, which the forms post below.
     */
    public function __construct(
        private StorePages $storePages,
        private CarFinder $carFinder,
        private CarProfiles $carProfiles,
        private LeadDesk $leadDesk,
        private ChatDesk $chatDesk,
        private CarSlugs $carSlugs = new CarSlugs(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the slug, with its session.
     *
     * @throws LogicException   When an entity type is malformed, the request has no session, or a key or a region does
     *                          not fit the theme.
     * @throws RuntimeException When the vehicles or their photos cannot be read.
     *
     * @return PageDocument The car's page with both forms blank, or the 404.
     */
    public function show(ServerRequestInterface $serverRequest): PageDocument
    {
        $listedVehicle = $this->carFinder->car($serverRequest);

        if (!$listedVehicle instanceof ListedVehicle) {
            return $this->storePages->missing($this->chatDesk->pageDock($serverRequest));
        }

        $panels = $this->leadDesk->panels($serverRequest, $this->carSlugs->path($listedVehicle));

        return $this->page($listedVehicle, $panels, 200, $this->carDock($serverRequest, $listedVehicle));
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, routed with the slug, with its session.
     * @param LeadKind               $leadKind      Which form was sent.
     *
     * @throws Throwable When the vehicles, their photos or the leads cannot be read or written, the lead's job cannot be
     *                   queued, recording the lead fails and is rolled back, the request has no session, or a key or a
     *                   region does not fit the theme.
     *
     * @return PageDocument The car's page, the sent form thanked (200) or refused (422); or the 404.
     */
    public function send(ServerRequestInterface $serverRequest, LeadKind $leadKind): PageDocument
    {
        $listedVehicle = $this->carFinder->car($serverRequest);

        if (!$listedVehicle instanceof ListedVehicle) {
            return $this->storePages->missing($this->chatDesk->pageDock($serverRequest));
        }

        $leadReply = $this->leadDesk->send($serverRequest, $leadKind, $listedVehicle, $this->carSlugs->path($listedVehicle));

        return $this->page($listedVehicle, $leadReply->panels, $leadReply->status, $this->carDock($serverRequest, $listedVehicle));
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted start form, routed with the slug, with its session.
     *
     * @throws Throwable When the vehicles, their photos, the leads or the chats cannot be read or written, the
     *                   notification cannot be queued, the request has no session, or a key or a region does not fit
     *                   the theme.
     *
     * @return PageDocument The car's page, the dock open on the new chat (200) or the start refused (422); or the 404.
     */
    public function chat(ServerRequestInterface $serverRequest): PageDocument
    {
        $listedVehicle = $this->carFinder->car($serverRequest);

        if (!$listedVehicle instanceof ListedVehicle) {
            return $this->storePages->missing($this->chatDesk->pageDock($serverRequest));
        }

        $path = $this->carSlugs->path($listedVehicle);
        $chatReply = $this->chatDesk->start($serverRequest, $listedVehicle, $path);
        $panels = $this->leadDesk->panels($serverRequest, $path);
        $dock = $this->chatDesk->dock($serverRequest, $path, $path . '/chat', $chatReply, true);

        return $this->page($listedVehicle, $panels, $chatReply->status, $dock);
    }

    /**
     * @param ServerRequestInterface $serverRequest The car page's request, with its session.
     * @param ListedVehicle          $listedVehicle The car.
     *
     * @throws RuntimeException When the chat or its messages cannot be read, or a stored side is no side.
     * @throws LogicException   When the request carries no session, or a type or a key is malformed.
     *
     * @return KeyedComponent|null The car page's dock: the visitor's open chat, else the start form for this car.
     */
    private function carDock(ServerRequestInterface $serverRequest, ListedVehicle $listedVehicle): ?KeyedComponent
    {
        $path = $this->carSlugs->path($listedVehicle);

        return $this->chatDesk->dock($serverRequest, $path, $path . '/chat', null, false);
    }

    /**
     * @param ListedVehicle        $listedVehicle  The car.
     * @param list<KeyedComponent> $panels         Its lead panels.
     * @param int                  $status         200, or 422 after a refused form.
     * @param KeyedComponent|null  $keyedComponent The chat's dock.
     *
     * @throws LogicException   When an entity type is malformed, or a key or a region does not fit the theme.
     * @throws RuntimeException When its photos cannot be read.
     *
     * @return PageDocument The car's page: its profile with its calculator, then the lead panels side by side, and the
     *                      chat's dock.
     */
    private function page(
        ListedVehicle $listedVehicle,
        array $panels,
        int $status,
        ?KeyedComponent $keyedComponent,
    ): PageDocument {
        $dealershipProfile = $this->storePages->profile();
        $parts = [
            new KeyedComponent('car', $this->carProfiles->profile($listedVehicle, $dealershipProfile->aprBasisPoints)),
            new KeyedComponent('leads', new PanelGrid($panels)),
        ];

        return $this->storePages->framed($dealershipProfile, $listedVehicle->title(), $parts, $status, $keyedComponent);
    }
}

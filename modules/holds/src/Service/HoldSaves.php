<?php

declare(strict_types=1);

namespace App\Holds\Service;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Holds\Entity\Hold;
use App\Holds\Entity\HoldDetails;
use App\Holds\Form\HoldChecks;
use App\Holds\Form\HoldEntries;
use App\Holds\Form\HoldEntry;
use App\Holds\Form\HoldForm;
use App\Holds\Repository\HoldChoiceRepository;
use App\Holds\Repository\HoldRepository;
use App\Inventory\Entity\VehicleStatus;
use App\Staff\Service\StaffNotices;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The hold form's flows (#20): the form, with the vehicle `?vehicle=` names chosen when it is in stock, and a posted
 * hold checked and placed. A refusal shows the form again as 422 with what was sent; a placed hold leaves a notice and
 * goes to the holds list (303). The notice names no time, so gate:23's two passes answer alike.
 */
final readonly class HoldSaves
{
    /**
     * @param HoldRepository       $holdRepository       Places the hold.
     * @param HoldChoiceRepository $holdChoiceRepository Finds the chosen vehicle.
     * @param HoldChecks           $holdChecks           Checks the form.
     * @param HoldForm             $holdForm             The form's page.
     * @param HoldEntries          $holdEntries          Reads the requests and who sent them.
     * @param StaffNotices         $staffNotices         Leaves the hold's notice.
     */
    public function __construct(
        private HoldRepository $holdRepository,
        private HoldChoiceRepository $holdChoiceRepository,
        private HoldChecks $holdChecks,
        private HoldForm $holdForm,
        private HoldEntries $holdEntries = new HoldEntries(),
        private StaffNotices $staffNotices = new StaffNotices(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the form, with its session; `?vehicle=` may name a VIN.
     *
     * @throws Throwable When the vehicle or the options cannot be read, the request has no session, or a key does not
     *                   fit the theme.
     *
     * @return PageResponse The form, the named vehicle chosen when it is in stock.
     */
    public function start(ServerRequestInterface $serverRequest): PageResponse
    {
        $vin = $this->holdEntries->chosenVin($serverRequest);
        $chosen = $vin !== '' && $this->holdChoiceRepository->vehicleStatus($vin) === VehicleStatus::InStock->value
            ? $vin
            : '';

        return $this->holdForm->page($serverRequest, new HoldEntry(['vehicle' => $chosen]), [], 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted hold, with its session.
     *
     * @throws Throwable When the hold cannot be checked or placed (the transaction rolled back), the request has no
     *                   session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the holds list, or the form again as 422, also when the vehicle left stock meanwhile.
     */
    public function create(ServerRequestInterface $serverRequest): PageResponse
    {
        $holdEntry = $this->holdEntries->posted($serverRequest);
        $holdCheck = $this->holdChecks->check($holdEntry, $this->holdEntries->login($serverRequest));
        $holdDetails = $holdCheck->holdDetails;

        if (!$holdDetails instanceof HoldDetails) {
            return $this->holdForm->page($serverRequest, $holdEntry, $holdCheck->errors, 422);
        }

        $id = $this->holdRepository->place($holdDetails);
        $hold = $id === null
            ? null
            : $this->holdRepository->find($id);

        if (!$hold instanceof Hold) {
            return $this->holdForm->page($serverRequest, $holdEntry, ['vehicle' => HoldChecks::NOT_FOR_SALE], 422);
        }

        $this->staffNotices->keep($serverRequest, 'Put the ' . $hold->title . ' on hold for ' . $hold->customerName . ' for 48 hours.');

        return new PageRedirect(Hold::PAGES);
    }
}

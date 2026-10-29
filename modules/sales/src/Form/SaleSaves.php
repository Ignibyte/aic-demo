<?php

declare(strict_types=1);

namespace App\Sales\Form;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use App\Sales\Entity\Sale;
use App\Sales\Entity\SaleDetails;
use App\Sales\Entity\SaleVehicle;
use App\Sales\Repository\SaleChoiceRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Staff\Service\StaffNotices;
use App\Staff\Service\StaffRequests;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function strtoupper;
use function trim;

/**
 * The sale form's flows: the form, with the vehicle a vehicle's page chose, and a posted sale checked and recorded.
 * A refusal shows the form again as 422 with what was sent; a recorded sale leaves a notice and goes to the sale (303).
 */
final readonly class SaleSaves
{
    /**
     * @param SaleChoiceRepository $saleChoiceRepository Finds the chosen vehicle, and the day.
     * @param SaleRecordRepository $saleRecordRepository Records the sale.
     * @param SaleChecks           $saleChecks           Checks the form.
     * @param SaleForm             $saleForm             The form's page.
     * @param SaleEntries          $saleEntries          Fills the form.
     * @param StaffNotices         $staffNotices         Leaves the sale's notice.
     * @param StaffRequests        $staffRequests        Reads `?vehicle=` as text.
     */
    public function __construct(
        private SaleChoiceRepository $saleChoiceRepository,
        private SaleRecordRepository $saleRecordRepository,
        private SaleChecks $saleChecks,
        private SaleForm $saleForm,
        private SaleEntries $saleEntries = new SaleEntries(),
        private StaffNotices $staffNotices = new StaffNotices(),
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request for the sale form, with its session; `?vehicle=` may
     *                                              name a VIN.
     *
     * @throws Throwable When the vehicle, the day or the options cannot be read, the request has no session, or a key
     *                   does not fit the theme.
     *
     * @return PageResponse The form: the named vehicle and its list price when it is on the lot and not sold, and today.
     */
    public function start(ServerRequestInterface $serverRequest): PageResponse
    {
        $vin = strtoupper(trim($this->staffRequests->query($serverRequest, 'vehicle')));
        $saleVehicle = $vin === ''
            ? null
            : $this->saleChoiceRepository->vehicle($vin);
        $today = $this->saleChoiceRepository->currentDay();
        $saleEntry = $saleVehicle instanceof SaleVehicle && !$saleVehicle->sold
            ? $this->saleEntries->start($vin, (string) $saleVehicle->listPrice, $today)
            : $this->saleEntries->start('', '', $today);

        return $this->saleForm->page($serverRequest, $saleEntry, [], 200);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted sale, with its session.
     *
     * @throws Throwable When the sale cannot be checked or recorded (the transaction rolled back), the request has no
     *                   session, or a key does not fit the theme.
     *
     * @return PageResponse 303 to the new sale, or the form again as 422, also when the vehicle was sold or held
     *                      meanwhile: the entry is checked again then, so the form says which.
     */
    public function create(ServerRequestInterface $serverRequest): PageResponse
    {
        $saleEntry = $this->saleEntries->posted($serverRequest);
        $saleCheck = $this->saleChecks->check($saleEntry, $this->saleChoiceRepository->currentDay());
        $saleDetails = $saleCheck->saleDetails;

        if (!$saleDetails instanceof SaleDetails) {
            return $this->saleForm->page($serverRequest, $saleEntry, $saleCheck->errors, 422);
        }

        $id = $this->saleRecordRepository->record($saleDetails);

        if ($id === null) {
            $errors = $this->saleChecks->check($saleEntry, $this->saleChoiceRepository->currentDay())->errors;
            $errors = $errors === []
                ? ['vehicle' => SaleChecks::ALREADY_SOLD]
                : $errors;

            return $this->saleForm->page($serverRequest, $saleEntry, $errors, 422);
        }

        $this->staffNotices->keep($serverRequest, 'Recorded the sale.');

        return new PageRedirect(Sale::PAGES . '/' . $id);
    }
}

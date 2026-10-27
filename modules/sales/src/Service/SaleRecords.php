<?php

declare(strict_types=1);

namespace App\Sales\Service;

use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use App\Sales\Entity\Sale;
use App\Sales\Repository\SaleRecordRepository;
use App\Staff\Service\StaffPanels;
use App\Staff\Service\StaffRequests;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function preg_match;

/**
 * The sale a `/app/sales/{id}` route names, its id checked as 1 to 9 digits before any query; and the pages for a
 * sale that is not there (404) and for a void form refused for its envelope (400).
 */
final readonly class SaleRecords
{
    /**
     * @param SaleRecordRepository $saleRecordRepository Reads a sale by its id.
     * @param StaffPanels          $staffPanels          The sale pages' frame.
     * @param StaffRequests        $staffRequests        Reads the route's id as text.
     */
    public function __construct(
        private SaleRecordRepository $saleRecordRepository,
        private StaffPanels $staffPanels,
        private StaffRequests $staffRequests = new StaffRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a sale's route.
     *
     * @throws QueryException           When the sale cannot be read.
     * @throws InvalidArgumentException When an entity type it goes through is malformed.
     *
     * @return Sale|null The sale its `id` names; null when that is not 1 to 999,999,999 or no sale has it.
     */
    public function find(ServerRequestInterface $serverRequest): ?Sale
    {
        $id = $this->staffRequests->attribute($serverRequest, 'id');

        if (preg_match('/^[1-9][0-9]{0,8}$/', $id) !== 1) {
            return null;
        }

        return $this->saleRecordRepository->find((int) $id);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page for an id no sale has, 404.
     */
    public function missing(): PageDocument
    {
        $message = 'No sale has that record. It may have been voided.';

        return $this->staffPanels->warning('Sale not found', $message, 'sale-missing', 'Back to the list', Sale::PAGES, 404);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page for a void form whose envelope is missing, altered or another sale's, 400.
     */
    public function refused(): PageDocument
    {
        $message = 'This form was issued for another sale, or it changed on the way, so nothing was voided. Open the sale again.';

        return $this->staffPanels->warning('Form refused', $message, 'sale-refused', 'Back to the list', Sale::PAGES, 400);
    }
}

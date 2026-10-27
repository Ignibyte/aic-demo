<?php

declare(strict_types=1);

namespace App\Customers\Service;

use AIC\Controller\Domain\PageDocument;
use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\Customer;
use App\Customers\Repository\CustomerRecordRepository;
use App\Staff\Service\StaffPanels;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;
use function preg_match;

/**
 * The customer a `/app/customers/{id}` route names, its id checked as 1 to 9 digits before any query; and the pages
 * for a customer that is not there (404) and for a form refused for its envelope (400).
 */
final readonly class CustomerRecords
{
    /**
     * @param CustomerRecordRepository $customerRecordRepository Reads a customer by its id.
     * @param StaffPanels              $staffPanels              The customer pages' frame.
     */
    public function __construct(
        private CustomerRecordRepository $customerRecordRepository,
        private StaffPanels $staffPanels,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a customer's route.
     *
     * @throws QueryException When the customer cannot be read.
     *
     * @return Customer|null The customer its `id` names; null when that is not 1 to 999,999,999 or no customer has it.
     */
    public function find(ServerRequestInterface $serverRequest): ?Customer
    {
        $id = $this->routeId($serverRequest);

        if (preg_match('/^[1-9][0-9]{0,8}$/', $id) !== 1) {
            return null;
        }

        return $this->customerRecordRepository->find((int) $id);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page for an id no customer has, 404.
     */
    public function missing(): PageDocument
    {
        $message = 'No customer has that record. It may have been deleted.';

        return $this->staffPanels->warning('Customer not found', $message, 'customer-missing', 'Back to the list', Customer::PAGES, 404);
    }

    /**
     * @throws InvalidArgumentException When the status does not fit the theme.
     *
     * @return PageDocument The page for an edit or delete form whose envelope is missing, altered or another customer's,
     *                      400.
     */
    public function refused(): PageDocument
    {
        $message = 'This form was issued for another customer, or it changed on the way, so nothing was saved. Open the customer again.';

        return $this->staffPanels->warning('Form refused', $message, 'customer-refused', 'Back to the list', Customer::PAGES, 400);
    }

    /**
     * Reads the attribute through a native `string` return, as `VehicleRecords::vin()` does: elephc does not narrow an
     * untyped value by `is_string()`. No AIC ticket carries this yet.
     *
     * @param ServerRequestInterface $serverRequest A request to a customer's route.
     *
     * @return string Its `id` attribute; empty when it is not a string.
     */
    private function routeId(ServerRequestInterface $serverRequest): string
    {
        $attributes = $serverRequest->getAttributes();

        return array_key_exists('id', $attributes) && is_string($attributes['id'])
            ? $attributes['id']
            : '';
    }
}

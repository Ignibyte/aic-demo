<?php

declare(strict_types=1);

namespace App\Sales\Form;

use AIC\Form\Application\PostedFields;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fills the sale form: from a post, each field as sent (a missing one or an array is empty); or at its start, with
 * the vehicle a vehicle's page chose and its list price, and the day.
 */
final readonly class SaleEntries
{
    /**
     * @param PostedFields $postedFields Reads the posted fields.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted sale form.
     *
     * @return SaleEntry What it sent.
     */
    public function posted(ServerRequestInterface $serverRequest): SaleEntry
    {
        $values = [];

        foreach (SaleEntry::FIELDS as $field) {
            $values[$field] = $this->postedFields->value($serverRequest, $field);
        }

        return new SaleEntry($values);
    }

    /**
     * @param string $vin   The chosen vehicle's VIN; empty for none.
     * @param string $price Its list price; empty for none.
     * @param string $today The day, `YYYY-MM-DD`.
     *
     * @return SaleEntry The form's start: the vehicle and its price when one was chosen, and the day.
     */
    public function start(string $vin, string $price, string $today): SaleEntry
    {
        return new SaleEntry(['price' => $price, 'sold_on' => $today, 'vehicle' => $vin]);
    }
}

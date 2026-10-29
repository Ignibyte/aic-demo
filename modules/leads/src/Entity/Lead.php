<?php

declare(strict_types=1);

namespace App\Leads\Entity;

/**
 * A stored lead as staff read it (#18): what the visitor sent, the day it came in, whether it is still open, its car,
 * its salesperson (null while unassigned) and the customer its email matched (0 and empty for none).
 */
final readonly class Lead
{
    /**
     * Where the staff pages of leads begin.
     */
    public const string PAGES = '/app/leads';

    /**
     * @param int         $id           Its row.
     * @param LeadDetails $leadDetails  What the visitor sent.
     * @param string      $receivedOn   The day it came in, `YYYY-MM-DD`.
     * @param bool        $open         Whether it is still open.
     * @param LeadCar     $leadCar      The car it is about.
     * @param Seller|null $seller       Its salesperson; null while unassigned.
     * @param int         $customerId   The customer its email matched; 0 for none.
     * @param string      $customerName That customer's name; empty for none.
     */
    public function __construct(
        public int $id,
        public LeadDetails $leadDetails,
        public string $receivedOn,
        public bool $open,
        public LeadCar $leadCar,
        public ?Seller $seller,
        public int $customerId,
        public string $customerName,
    ) {
    }

    /**
     * @return string Its staff page, `/app/leads/{id}`.
     */
    public function path(): string
    {
        return self::PAGES . '/' . $this->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Leads\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use App\Leads\Entity\Lead;
use App\Leads\Entity\LeadCar;
use App\Leads\Entity\LeadDetails;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\LeadStage;
use App\Leads\Entity\Seller;

use function substr;
use function trim;

/**
 * Reads a lead's row, joined with its car (`v_`), its salesperson (`u_`) and its customer (`c_`), into a `Lead` (#18).
 * A null column reads as empty or 0.
 */
final readonly class LeadRows
{
    /**
     * @param Row $row A lead joined with its car, and its salesperson and customer when it has them.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Lead The lead.
     */
    public function lead(Row $row): Lead
    {
        $title = trim($row->int('v_year') . ' ' . $row->string('v_make') . ' ' . $row->string('v_model') . ' ' . $row->string('v_trim'));
        $customerId = $row->intOrNull('customer_id') ?? 0;

        return new Lead(
            $row->int('id'),
            $this->details($row),
            substr($row->string('created_at'), 0, 10),
            $this->stage($row),
            new LeadCar($title, $row->string('v_vin'), $row->int('v_list_price')),
            $this->seller($row),
            $customerId,
            $customerId === 0 ? '' : $row->string('c_name'),
        );
    }

    /**
     * @param Row $row A lead's row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return LeadStage Its stage (#30); a lead with none reads from its status as `AddLeadStage` filled the stored
     *                   ones: closed is Lost, open New.
     */
    public function stage(Row $row): LeadStage
    {
        $stage = LeadStage::New->stored($row->stringOrNull('stage') ?? '');

        if ($stage instanceof LeadStage) {
            return $stage;
        }

        return $row->string('status') === 'closed'
            ? LeadStage::Lost
            : LeadStage::New;
    }

    /**
     * @param Row $row A lead's row.
     *
     * @throws QueryException When the column is of another type.
     *
     * @return int Its salesperson's user id; 0 while it is unassigned.
     */
    public function sellerId(Row $row): int
    {
        return $row->intOrNull('salesperson_id') ?? 0;
    }

    /**
     * @param Row $row A lead's row.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return LeadDetails What the visitor sent.
     */
    private function details(Row $row): LeadDetails
    {
        return new LeadDetails(
            LeadKind::Inquiry->stored($row->string('kind')),
            $row->string('name'),
            $row->string('email'),
            $row->string('phone'),
            $row->string('message'),
            $row->stringOrNull('drive_on') ?? '',
            $row->stringOrNull('drive_at') ?? '',
        );
    }

    /**
     * @param Row $row A lead joined with its salesperson.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return Seller|null Its salesperson; null while it is unassigned.
     */
    private function seller(Row $row): ?Seller
    {
        $id = $this->sellerId($row);

        return $id === 0
            ? null
            : new Seller($id, $row->string('u_email'), $row->string('u_name'));
    }
}

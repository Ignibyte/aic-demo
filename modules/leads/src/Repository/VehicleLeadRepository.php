<?php

declare(strict_types=1);

namespace App\Leads\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityQuery;
use AIC\Entity\Application\EntityStore;
use App\Leads\Entity\LeadKind;
use App\Leads\Entity\LeadMoveTable;
use App\Leads\Entity\LeadTable;
use App\Leads\Entity\VehicleLead;
use App\Leads\Service\LeadRows;
use InvalidArgumentException;

use function count;
use function implode;
use function substr;

/**
 * A vehicle's leads for its page (#31), by its VIN: the newest, with their kind, stage and salesperson, and their
 * version, which the page's leads and holds panel polls. Exported, so the panels module reads them while the leads and
 * their moves stay this module's tables.
 */
final readonly class VehicleLeadRepository
{
    /**
     * @param EntityStore   $entityStore   The entity store.
     * @param LeadTable     $leadTable     The leads' type.
     * @param LeadMoveTable $leadMoveTable The lead moves' type.
     * @param LeadRows      $leadRows      Reads a lead's stage.
     */
    public function __construct(
        private EntityStore $entityStore,
        private LeadTable $leadTable = new LeadTable(),
        private LeadMoveTable $leadMoveTable = new LeadMoveTable(),
        private LeadRows $leadRows = new LeadRows(),
    ) {
    }

    /**
     * @param string $vin   A vehicle's VIN.
     * @param int    $limit How many to read, at least 1.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a type is malformed or the limit is below 1.
     *
     * @return list<VehicleLead> Its newest leads, at most `$limit`, newest first.
     */
    public function forVehicle(string $vin, int $limit): array
    {
        $rows = $this->leads($vin)
            ->with('salesperson_id', 'u', true)
            ->orderByDescending('id')
            ->limit($limit)
            ->rows();
        $leads = [];

        foreach ($rows as $row) {
            $seller = $row->intOrNull('salesperson_id') === null
                ? ''
                : $row->string('u_name');
            $leads[] = new VehicleLead(
                $row->int('id'),
                substr($row->string('created_at'), 0, 10),
                $row->string('name'),
                LeadKind::Inquiry->stored($row->string('kind'))->label(),
                $this->leadRows->stage($row)->label(),
                $seller,
            );
        }

        return $leads;
    }

    /**
     * @param string $vin   A vehicle's VIN.
     * @param int    $shown How many of its newest leads the panel lists, at least 1.
     *
     * @throws QueryException           When a query fails.
     * @throws InvalidArgumentException When a type is malformed or the number is below 1.
     *
     * @return string What its leads are now: their count and newest id, the salesperson of each lead the panel lists
     *                (`id:user`, 0 for none), and their moves' count and newest id; a new lead, a reassignment or a
     *                stage move changes it.
     */
    public function version(string $vin, int $shown): string
    {
        $ids = $this->leads($vin)->totals()->values('id');
        $version = count($ids) . '.' . ($this->leads($vin)->totals()->max('id') ?? '0') . '.' . $this->sellers($vin, $shown);

        if ($ids === []) {
            return $version;
        }

        $entityQuery = $this->entityStore->query($this->leadMoveTable->type())->whereIn('lead_id', $ids);

        return $version . '.' . $entityQuery->totals()->count() . '.' . ($entityQuery->totals()->max('id') ?? '0');
    }

    /**
     * @param string $vin A vehicle's VIN.
     *
     * @throws InvalidArgumentException When a type is malformed.
     *
     * @return EntityQuery Its leads, joined to it by its VIN.
     */
    private function leads(string $vin): EntityQuery
    {
        return $this->entityStore->query($this->leadTable->type())
            ->with('vehicle_id', 'v')
            ->where('v.vin', $vin);
    }

    /**
     * @param string $vin   A vehicle's VIN.
     * @param int    $shown How many of its newest leads to read.
     *
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When a type is malformed or the number is below 1.
     *
     * @return string Each of its newest leads with its salesperson, `id:user` joined by `,`; `0` stands for none.
     */
    private function sellers(string $vin, int $shown): string
    {
        $pairs = [];

        foreach ($this->leads($vin)->orderByDescending('id')->limit($shown)->rows() as $row) {
            $pairs[] = $row->int('id') . ':' . ($row->intOrNull('salesperson_id') ?? 0);
        }

        return implode(',', $pairs);
    }
}

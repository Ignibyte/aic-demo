<?php

declare(strict_types=1);

namespace App\Reports\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The reports' entity types (#20): the two read-only views, `report_aged_stock` and `report_day`.
 */
final readonly class ReportTables
{
    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The vehicles not sold with their days on the lot.
     */
    public function agedStock(): EntityType
    {
        return new EntityType('report_aged_stock', 'id', [
            new Field('vin', FieldType::Text),
            new Field('title', FieldType::Text),
            new Field('list_price', FieldType::Integer),
            new Field('days', FieldType::Integer),
        ], true);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The day's figures, one row.
     */
    public function day(): EntityType
    {
        return new EntityType('report_day', 'today', [
            new Field('sales', FieldType::Integer),
            new Field('revenue', FieldType::Integer),
            new Field('leads', FieldType::Integer),
            new Field('inquiries', FieldType::Integer),
            new Field('test_drives', FieldType::Integer),
            new Field('new_stock', FieldType::Integer),
        ], true);
    }
}

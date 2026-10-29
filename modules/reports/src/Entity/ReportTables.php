<?php

declare(strict_types=1);

namespace App\Reports\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The reports' entity types, every one a read-only view: `report_aged_stock` and `report_day` for the two scheduled
 * reports (#20); and for the reports page (#21) the calendar, the presets, and one row per sale, per vehicle and per
 * lead, each day as text.
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

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType Every day a range may name, with its month.
     */
    public function calendar(): EntityType
    {
        return new EntityType('report_calendar', 'day', [new Field('month', FieldType::Text)], true);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType The presets, by name.
     */
    public function ranges(): EntityType
    {
        return new EntityType('report_ranges', 'name', [
            new Field('position', FieldType::Integer),
            new Field('label', FieldType::Text),
            new Field('from_day', FieldType::Text),
            new Field('to_day', FieldType::Text),
        ], true);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType Each sale with its day, month, price and salesperson.
     */
    public function sales(): EntityType
    {
        return new EntityType('report_sales', 'id', [
            new Field('sold_on', FieldType::Text),
            new Field('month', FieldType::Text),
            new Field('price', FieldType::Integer),
            new Field('salesperson_email', FieldType::Text),
            new Field('salesperson', FieldType::Text),
        ], true);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType Each vehicle with its make, the days it came and left, and its days on the lot.
     */
    public function stock(): EntityType
    {
        return new EntityType('report_stock', 'id', [
            new Field('make', FieldType::Text),
            new Field('added_on', FieldType::Text),
            new Field('left_on', FieldType::Text),
            new Field('days', FieldType::Integer),
        ], true);
    }

    /**
     * @throws InvalidArgumentException When a field or the type is malformed.
     *
     * @return EntityType Each lead with its kind, the day it came in and whether it became a sale.
     */
    public function leads(): EntityType
    {
        return new EntityType('report_leads', 'id', [
            new Field('kind', FieldType::Text),
            new Field('came_on', FieldType::Text),
            new Field('sold', FieldType::Boolean),
        ], true);
    }
}

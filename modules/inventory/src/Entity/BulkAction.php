<?php

declare(strict_types=1);

namespace App\Inventory\Entity;

/**
 * What a bulk edit of the inventory list does to the ticked vehicles (#29), as the action bar's choice starts.
 */
enum BulkAction: string
{
    /** Gives them a status. */
    case Status = 'status';

    /** Moves them to a lot location. */
    case Location = 'location';

    /** Deletes them, once asked. */
    case Delete = 'delete';
}

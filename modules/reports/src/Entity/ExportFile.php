<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * Where an export's CSV is kept (#23): its key in AIC's private files, named by the export's id. The job writes it, the
 * prune deletes it, the download sends it and `reports:move-exports` moves an old file to it.
 */
final readonly class ExportFile
{
    /**
     * @param int $id The export's id.
     */
    public function __construct(
        public int $id,
    ) {
    }

    /**
     * @return string Its key, `exports/{id}.csv`.
     */
    public function key(): string
    {
        return 'exports/' . $this->id . '.csv';
    }
}

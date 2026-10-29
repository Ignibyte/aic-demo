<?php

declare(strict_types=1);

namespace App\Reports\Service;

use RuntimeException;

use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function tempnam;
use function unlink;

/**
 * Where the exports' files are kept (#21): the project's `var/exports/{id}.csv`, outside `public/`, each named by the
 * server from its row. A file is written to a temporary file in that directory and renamed over its name, so a
 * download never sees half of one (`tempnam()`, since elephc has no `random_bytes()`, AIC #209). The directory is
 * found from this file, as photos' `PhotoFiles` finds `var/photos/`.
 */
final readonly class ExportFiles
{
    /**
     * @param int $id An export's id.
     *
     * @return string Its file.
     */
    public function path(int $id): string
    {
        return $this->directory() . '/' . $id . '.csv';
    }

    /**
     * @param int $id An export's id.
     *
     * @return bool Whether its file is there.
     */
    public function exists(int $id): bool
    {
        return is_file($this->path($id));
    }

    /**
     * @param int    $id   An export's id.
     * @param string $text Its CSV.
     *
     * @throws RuntimeException When the directory cannot be made, or the file cannot be written or renamed.
     */
    public function write(int $id, string $text): void
    {
        $directory = $this->made();
        $temporary = tempnam($directory, 'export-');

        if ($temporary === false || file_put_contents($temporary, $text) === false) {
            throw new RuntimeException('Export ' . $id . ' cannot be written in ' . $directory . '.');
        }

        if (!rename($temporary, $this->path($id))) {
            unlink($temporary);

            throw new RuntimeException('Export ' . $id . ' cannot be renamed into place.');
        }
    }

    /**
     * @param int $id An export's id: its file is removed, and nothing happens when it is gone already.
     */
    public function remove(int $id): void
    {
        $path = $this->path($id);

        if (!is_file($path)) {
            return;
        }

        unlink($path);
    }

    /**
     * @throws RuntimeException When the directory is not there and cannot be made.
     *
     * @return string The exports' directory, made when it was not there.
     */
    private function made(): string
    {
        $directory = $this->directory();

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException('The export directory ' . $directory . ' cannot be made.');
        }

        return $directory;
    }

    /**
     * @return string The exports' directory, under the project's `var/`.
     */
    private function directory(): string
    {
        return __DIR__ . '/../../../../var/exports';
    }
}

<?php

declare(strict_types=1);

namespace App\Reports\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Storage\Application\PrivateFiles;
use App\Reports\Entity\ExportFile;
use InvalidArgumentException;
use Override;
use RuntimeException;

use function count;
use function file_get_contents;
use function is_dir;
use function preg_match;
use function rmdir;
use function scandir;
use function substr;
use function unlink;

/**
 * `bin/aic reports:move-exports`: moves the export files kept before AIC's private files (#23), at the project's
 * `var/exports/{id}.csv`, into the private files under each export's key, then removes the folder once it is empty. A
 * file whose name is not an export's is left. Run again, it finds nothing to move. The old folder is found from this
 * file, as the removed `ExportFiles` found it. Exit 2 with its usage for any argument.
 */
final readonly class ReportsMoveExports implements Command
{
    /**
     * @param PrivateFiles $privateFiles AIC's private files, where the exports go.
     */
    public function __construct(
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @throws InvalidArgumentException When a key is not one, or the exit code is invalid.
     * @throws RuntimeException         When an old file cannot be read or its new one written.
     *
     * @return CommandResponse How many files it moved.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (count($commandInput->arguments()) !== 0) {
            return new CommandResult(['usage: bin/aic reports:move-exports'], 2);
        }

        $folder = __DIR__ . '/../../../../var/exports';
        $names = is_dir($folder)
            ? scandir($folder)
            : false;

        if ($names === false) {
            return new CommandResult(['reports:move-exports moved 0 export(s).'], 0);
        }

        $moved = 0;

        foreach ($names as $name) {
            $moved += $this->move($folder, $name);
        }

        $this->removeFolder($folder);

        return new CommandResult(['reports:move-exports moved ' . $moved . ' export(s).'], 0);
    }

    /**
     * @param string $folder The old exports' folder: it goes once it holds nothing but `.` and `..`.
     */
    private function removeFolder(string $folder): void
    {
        $left = scandir($folder);

        if ($left === false || count($left) > 2) {
            return;
        }

        rmdir($folder);
    }

    /**
     * @param string $folder The old exports' folder.
     * @param string $name   A name in it.
     *
     * @throws InvalidArgumentException When the key is not one.
     * @throws RuntimeException         When the file cannot be read or its new one written.
     *
     * @return int 1 when the name was an export's file and it moved; 0 otherwise.
     */
    private function move(string $folder, string $name): int
    {
        if (preg_match('/^[1-9]\\d{0,17}\\.csv$/', $name) !== 1) {
            return 0;
        }

        $old = $folder . '/' . $name;
        $contents = file_get_contents($old);

        if ($contents === false) {
            throw new RuntimeException('The export file ' . $old . ' cannot be read.');
        }

        $this->privateFiles->put((new ExportFile((int) substr($name, 0, -4)))->key(), $contents);
        unlink($old);

        return 1;
    }
}

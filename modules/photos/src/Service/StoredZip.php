<?php

declare(strict_types=1);

namespace App\Photos\Service;

use App\Photos\Entity\ZipEntry;

use function chr;
use function count;
use function crc32;
use function strlen;

/**
 * Writes a ZIP whose entries are stored, not compressed (#36), by hand: elephc compiles no `ZipArchive` and no
 * `pack()`, so each record is written field by field, every integer little-endian through `chr()` and each entry's
 * CRC-32 from `crc32()`. Per entry a local file header and its bytes, then the central directory, then its end record
 * (APPNOTE 6.3.x, 4.3). Every entry is dated 1980-01-01 00:00, the first DOS date, so the same entries always give
 * the same bytes. No zip64: a vehicle's photos stay far below 4 GiB and 65,535 entries.
 */
final readonly class StoredZip
{
    /**
     * A local file header's signature, `PK\3\4`.
     */
    private const int LOCAL = 0x04034b50;

    /**
     * A central directory record's signature, `PK\1\2`.
     */
    private const int CENTRAL = 0x02014b50;

    /**
     * The end of central directory record's signature, `PK\5\6`.
     */
    private const int END = 0x06054b50;

    /**
     * The version needed to extract a stored entry, 1.0.
     */
    private const int NEEDED = 10;

    /**
     * The version the archive is made by: 2.0, on MS-DOS's attributes.
     */
    private const int MADE_BY = 20;

    /**
     * 1980-01-01 as a DOS date: year 0 from 1980, month 1, day 1.
     */
    private const int DOS_DATE = 0x0021;

    /**
     * 00:00:00 as a DOS time.
     */
    private const int DOS_TIME = 0;

    /**
     * @param list<ZipEntry> $entries The files, in the order they are written.
     *
     * @return string The archive's bytes.
     */
    public function bytes(array $entries): string
    {
        $archive = '';
        $directory = '';

        foreach ($entries as $entry) {
            $directory .= $this->central($entry, strlen($archive));
            $archive .= $this->local($entry);
        }

        return $archive . $directory . $this->end(count($entries), strlen($directory), strlen($archive));
    }

    /**
     * @param ZipEntry $zipEntry A file.
     *
     * @return string Its local file header, its name and its bytes.
     */
    private function local(ZipEntry $zipEntry): string
    {
        return $this->long(self::LOCAL) . $this->fields($zipEntry) . $zipEntry->name . $zipEntry->contents;
    }

    /**
     * @param ZipEntry $zipEntry A file.
     * @param int      $offset   Where its local header starts in the archive.
     *
     * @return string Its central directory record: no comment, disk 0, no attributes.
     */
    private function central(ZipEntry $zipEntry, int $offset): string
    {
        return $this->long(self::CENTRAL)
            . $this->short(self::MADE_BY)
            . $this->fields($zipEntry)
            . $this->short(0)
            . $this->short(0)
            . $this->short(0)
            . $this->long(0)
            . $this->long($offset)
            . $zipEntry->name;
    }

    /**
     * @param ZipEntry $zipEntry A file.
     *
     * @return string The fields a local header and a central record share, from the version needed to the extra
     *                field's length: no flags, stored, the fixed date, its CRC-32, its size twice, no extra field.
     */
    private function fields(ZipEntry $zipEntry): string
    {
        $size = strlen($zipEntry->contents);

        return $this->short(self::NEEDED)
            . $this->short(0)
            . $this->short(0)
            . $this->short(self::DOS_TIME)
            . $this->short(self::DOS_DATE)
            . $this->long(crc32($zipEntry->contents))
            . $this->long($size)
            . $this->long($size)
            . $this->short(strlen($zipEntry->name))
            . $this->short(0);
    }

    /**
     * @param int $count     How many entries the archive holds.
     * @param int $size      The central directory's length.
     * @param int $directory Where the central directory starts.
     *
     * @return string The end of central directory record: one disk, no comment.
     */
    private function end(int $count, int $size, int $directory): string
    {
        return $this->long(self::END)
            . $this->short(0)
            . $this->short(0)
            . $this->short($count)
            . $this->short($count)
            . $this->long($size)
            . $this->long($directory)
            . $this->short(0);
    }

    /**
     * @param int $value A whole number from 0 to 65,535.
     *
     * @return string It as two bytes, low byte first.
     */
    private function short(int $value): string
    {
        return chr($value & 0xFF) . chr(($value >> 8) & 0xFF);
    }

    /**
     * @param int $value A whole number from 0 to 4,294,967,295.
     *
     * @return string It as four bytes, low byte first.
     */
    private function long(int $value): string
    {
        return $this->short($value & 0xFFFF) . $this->short(($value >> 16) & 0xFFFF);
    }
}

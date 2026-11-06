<?php

declare(strict_types=1);

namespace App\Photos\Service;

use App\Photos\Entity\ZipEntry;

use function count;
use function crc32;
use function pack;
use function strlen;

/**
 * Writes a ZIP whose entries are stored, not compressed (#36), by hand, since elephc compiles no `ZipArchive`. Each
 * record is one `pack()` call (AIC #266 brings `pack()` to the compiled site), its integers little-endian through the
 * `v` and `V` codes in APPNOTE 6.3.x's field order (4.3), and each entry's CRC-32 comes from `crc32()`. Per entry a
 * local file header and its bytes, then the central directory, then its end record. Every entry is dated 1980-01-01
 * 00:00, the first DOS date, so the same entries always give the same bytes. No zip64: a vehicle's photos stay far
 * below 4 GiB and 65,535 entries.
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
     * @return string Its local file header, its name and its bytes: version 1.0 needed, no flags, stored, the fixed
     *                date, its CRC-32, its size twice, no extra field.
     */
    private function local(ZipEntry $zipEntry): string
    {
        return pack(
            'VvvvvvVVVvv',
            self::LOCAL,
            self::NEEDED,
            0,
            0,
            self::DOS_TIME,
            self::DOS_DATE,
            crc32($zipEntry->contents),
            strlen($zipEntry->contents),
            strlen($zipEntry->contents),
            strlen($zipEntry->name),
            0,
        ) . $zipEntry->name . $zipEntry->contents;
    }

    /**
     * @param ZipEntry $zipEntry A file.
     * @param int      $offset   Where its local header starts in the archive.
     *
     * @return string Its central directory record and its name: made by 2.0, the local header's fields from the
     *                version needed to the extra field's length, then no comment, disk 0, no attributes, the offset.
     */
    private function central(ZipEntry $zipEntry, int $offset): string
    {
        return pack(
            'VvvvvvvVVVvvvvvVV',
            self::CENTRAL,
            self::MADE_BY,
            self::NEEDED,
            0,
            0,
            self::DOS_TIME,
            self::DOS_DATE,
            crc32($zipEntry->contents),
            strlen($zipEntry->contents),
            strlen($zipEntry->contents),
            strlen($zipEntry->name),
            0,
            0,
            0,
            0,
            0,
            $offset,
        ) . $zipEntry->name;
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
        return pack('VvvvvVVv', self::END, 0, 0, $count, $count, $size, $directory, 0);
    }
}

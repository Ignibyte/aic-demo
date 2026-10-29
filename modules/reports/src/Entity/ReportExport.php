<?php

declare(strict_types=1);

namespace App\Reports\Entity;

/**
 * One CSV export (#21), as its member and its job read it: which report over which range, and whether its file is
 * written.
 */
final readonly class ReportExport
{
    /**
     * The path the download answers at, the export's id after it.
     */
    public const string PATH = '/app/reports/exports/';

    /**
     * @param int         $id          Its id.
     * @param int         $userId      The member who asked for it.
     * @param ReportKind  $reportKind  The report.
     * @param ReportRange $reportRange Its range.
     * @param bool        $ready       Whether its file is written.
     */
    public function __construct(
        public int $id,
        public int $userId,
        public ReportKind $reportKind,
        public ReportRange $reportRange,
        public bool $ready,
    ) {
    }

    /**
     * @return string Where it downloads.
     */
    public function path(): string
    {
        return self::PATH . $this->id;
    }

    /**
     * @return string The name the browser saves it as: `<report>-<from>-to-<to>.csv`.
     */
    public function fileName(): string
    {
        return $this->reportKind->value . '-' . $this->reportRange->from . '-to-' . $this->reportRange->until . '.csv';
    }
}

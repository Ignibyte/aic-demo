<?php

declare(strict_types=1);

namespace App\Reports\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\HiddenInput;
use App\Reports\Entity\ReportKind;
use App\Reports\Entity\ReportRange;

/**
 * A report's "Export CSV" button (#21): AIC's `Form`, with its token and honeypot, posting the report and its range
 * to `/app/reports/exports`.
 */
final readonly class ExportForms
{
    /**
     * Where an export is asked for.
     */
    public const string ACTION = '/app/reports/exports';

    /**
     * @param ReportKind  $reportKind  The report.
     * @param ReportRange $reportRange The range shown.
     * @param GuardFields $guardFields The form's CSRF token and honeypot.
     *
     * @return Form The button, in its form.
     */
    public function form(ReportKind $reportKind, ReportRange $reportRange, GuardFields $guardFields): Form
    {
        $id = 'report-export-' . $reportKind->value;

        return new Form($id, self::ACTION, [
            new HiddenInput('report', $reportKind->value),
            new HiddenInput('from', $reportRange->from),
            new HiddenInput('to', $reportRange->until),
            new Button('Export CSV', 'submit', $id . '-button'),
        ], $guardFields);
    }
}

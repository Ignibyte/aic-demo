<?php

declare(strict_types=1);

namespace App\Reports\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Notifier;
use AIC\Notification\Contracts\Notification;
use App\Reports\Entity\AgedVehicle;
use App\Reports\Entity\DayFigures;
use App\Reports\Entity\Recipient;
use JsonException;

use function count;
use function implode;
use function number_format;

/**
 * The reports' notifications (#20), through AIC's Notifier, one `notify` job per channel, so each delivery retries and
 * fails on its own: the aged-stock list, kept in the database and mailed to each sales manager; and the day's digest,
 * mailed alone to each manager. No mail goes to a login that is not an address.
 */
final readonly class ReportNotices
{
    /**
     * Where the aged-stock notification points: the inventory, longest on the lot first.
     */
    public const string AGED_LINK = '/app/inventory?sort=added';

    /**
     * @param Notifier $notifier Queues each delivery.
     */
    public function __construct(
        private Notifier $notifier,
    ) {
    }

    /**
     * @param list<AgedVehicle> $vehicles   The aged vehicles, longest on the lot first; at least one.
     * @param int               $days       The threshold.
     * @param list<Recipient>   $recipients The sales managers.
     *
     * @throws JsonException  When a delivery's payload cannot be encoded.
     * @throws QueryException When a delivery cannot be queued.
     */
    public function agedStock(array $vehicles, int $days, array $recipients): void
    {
        $count = count($vehicles);
        $subject = $count === 1
            ? '1 vehicle on the lot more than ' . $days . ' days'
            : $count . ' vehicles on the lot more than ' . $days . ' days';
        $lines = [$subject . ', longest first:', ''];

        foreach ($vehicles as $vehicle) {
            $lines[] = '- ' . $vehicle->title . ', VIN ' . $vehicle->vin . ', ' . $vehicle->days . ' days, '
                . $this->money($vehicle->listPrice);
        }

        $lines[] = '';
        $lines[] = 'The inventory, oldest first: ' . self::AGED_LINK;
        $body = implode("\n", $lines);

        foreach ($recipients as $recipient) {
            $channels = $recipient->mailable()
                ? ['database', 'mail']
                : ['database'];
            $notification = new Notification((string) $recipient->id, $recipient->login, $subject, $body, self::AGED_LINK);
            $this->notifier->send($notification, $channels);
        }
    }

    /**
     * @param DayFigures      $dayFigures The day's figures.
     * @param list<Recipient> $recipients The managers.
     *
     * @throws JsonException  When a delivery's payload cannot be encoded.
     * @throws QueryException When a delivery cannot be queued.
     */
    public function digest(DayFigures $dayFigures, array $recipients): void
    {
        $subject = 'Ridgeway Motors on ' . $dayFigures->today . ': ' . $this->count($dayFigures->sales, 'sale', 'sales') . ', '
            . $this->count($dayFigures->leads, 'lead', 'leads') . ', ' . $dayFigures->newStock . ' new on the lot';
        $body = implode("\n", [
            'The day at Ridgeway Motors, ' . $dayFigures->today . ' (UTC):',
            '',
            'Sales: ' . $dayFigures->sales . ', ' . $this->money($dayFigures->revenue) . ' in all',
            'Leads: ' . $dayFigures->leads . ' (' . $this->count($dayFigures->inquiries, 'question', 'questions') . ', '
                . $this->count($dayFigures->testDrives, 'test drive', 'test drives') . ')',
            'New stock: ' . $this->count($dayFigures->newStock, 'vehicle', 'vehicles'),
            '',
            'The dashboard: /app',
        ]);

        foreach ($recipients as $recipient) {
            if (!$recipient->mailable()) {
                continue;
            }

            $this->notifier->send(new Notification((string) $recipient->id, $recipient->login, $subject, $body, '/app'), ['mail']);
        }
    }

    /**
     * @param int $dollars Whole dollars.
     *
     * @return string `$41,500`.
     */
    private function money(int $dollars): string
    {
        return '$' . number_format($dollars);
    }

    /**
     * @param int    $count A count.
     * @param string $one   The word for one.
     * @param string $many  The word for any other count.
     *
     * @return string `1 sale`, `3 sales`.
     */
    private function count(int $count, string $one, string $many): string
    {
        return $count . ' ' . ($count === 1 ? $one : $many);
    }
}

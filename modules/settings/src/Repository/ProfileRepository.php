<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Application\EntityStore;
use App\Finance\Service\Payments;
use App\Settings\Entity\DealershipProfile;
use App\Settings\Entity\OpeningHours;
use App\Settings\Entity\ProfileTable;
use App\Settings\Entity\SettingsWrite;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The dealership's profile (#22), through the entity store: its one row, or the defaults while there is none. A save is
 * one `EntityStore::transaction()`: the defaults are inserted if the row is missing (so there is always a row to lock),
 * the row is read `FOR UPDATE` and compared with the values the form showed, and only when they still match is the
 * profile upserted on its slot, so a save made on a stale copy writes nothing. The closure is static and calls
 * `saveLocked()`: natively a closure does not bind `$this` (AIC #184). Its outcome travels out in a `SettingsWrite`.
 */
final readonly class ProfileRepository
{
    /**
     * @param EntityStore  $entityStore  The entity store.
     * @param ProfileTable $profileTable The profile's type.
     */
    public function __construct(
        private EntityStore $entityStore,
        private ProfileTable $profileTable = new ProfileTable(),
    ) {
    }

    /**
     * @throws QueryException           When the query fails.
     * @throws InvalidArgumentException When the entity type is malformed.
     *
     * @return DealershipProfile The profile as saved; the defaults until a first save.
     */
    public function profile(): DealershipProfile
    {
        $row = $this->entityStore->query($this->profileTable->type())->where('slot', ProfileTable::SLOT)->first();

        return $row instanceof Row
            ? $this->fromRow($row)
            : $this->defaults();
    }

    /**
     * @param array<string, string> $seen              Each field's value as the form showed it, by name.
     * @param DealershipProfile     $dealershipProfile The profile to save, checked.
     *
     * @throws Throwable When a statement fails; the transaction is rolled back first.
     *
     * @return string `SettingsWrite::SAVED`, or `SettingsWrite::STALE` when the stored profile is no longer the one shown.
     */
    public function save(array $seen, DealershipProfile $dealershipProfile): string
    {
        $profileRepository = $this;
        $settingsWrite = new SettingsWrite();
        $this->entityStore->transaction(static function () use ($profileRepository, $settingsWrite, $seen, $dealershipProfile): void {
            $settingsWrite->record($profileRepository->saveLocked($seen, $dealershipProfile));
        });

        return $settingsWrite->outcome();
    }

    /**
     * `save()` calls this inside its transaction.
     *
     * @param array<string, string> $seen              Each field's value as the form showed it, by name.
     * @param DealershipProfile     $dealershipProfile The profile to save.
     *
     * @throws RuntimeException         When a statement fails, or the row is gone though just inserted.
     * @throws InvalidArgumentException When a value does not fit its field.
     *
     * @return string `SettingsWrite::SAVED` or `SettingsWrite::STALE`.
     */
    public function saveLocked(array $seen, DealershipProfile $dealershipProfile): string
    {
        $entityType = $this->profileTable->type();
        $this->entityStore->insertIfAbsent($entityType, $this->row($this->defaults()), 'slot');
        $row = $this->entityStore->query($entityType)->where('slot', ProfileTable::SLOT)->forUpdate()->one();
        $stored = $this->fromRow($row)->values();

        foreach (DealershipProfile::FIELDS as $field) {
            if ($this->value($stored, $field) !== $this->value($seen, $field)) {
                return SettingsWrite::STALE;
            }
        }

        $this->entityStore->upsert($entityType, $this->row($dealershipProfile), 'slot');

        return SettingsWrite::SAVED;
    }

    /**
     * Reads one value through a native `string` return, so the compiled site compares strings (elephc types an element
     * read straight from an array as mixed; no AIC ticket carries it yet).
     *
     * @param array<string, string> $values Values by field name.
     * @param string                $field  A field's name.
     *
     * @return string Its value; empty when it has none.
     */
    private function value(array $values, string $field): string
    {
        return $values[$field] ?? '';
    }

    /**
     * @return DealershipProfile Ridgeway Motors, open Monday to Saturday from 09:00 to 18:00, aged after 60 days, at the
     *                           standard 6.90% APR, with no address or phone: the profile until an admin saves one, as
     *                           the app had it before.
     */
    private function defaults(): DealershipProfile
    {
        $openingHours = new OpeningHours('09:00', '18:00', '123456');

        return new DealershipProfile('Ridgeway Motors', '', '', $openingHours, 60, Payments::STANDARD_APR);
    }

    /**
     * @param DealershipProfile $dealershipProfile A profile.
     *
     * @return array<string, string|int> Its row's writable values, the slot included.
     */
    private function row(DealershipProfile $dealershipProfile): array
    {
        $openingHours = $dealershipProfile->openingHours;

        return [
            'address' => $dealershipProfile->address,
            'aged_stock_days' => $dealershipProfile->agedStockDays,
            'closes_at' => $openingHours->closesAt,
            'default_apr_bp' => $dealershipProfile->aprBasisPoints,
            'name' => $dealershipProfile->name,
            'opens_at' => $openingHours->opensAt,
            'open_days' => $openingHours->openDays,
            'phone' => $dealershipProfile->phone,
            'slot' => ProfileTable::SLOT,
        ];
    }

    /**
     * @param Row $row The profile's row.
     *
     * @throws QueryException When a column is missing.
     *
     * @return DealershipProfile The profile it holds; a null APR, as a row saved before #26 has, is the standard one.
     */
    private function fromRow(Row $row): DealershipProfile
    {
        return new DealershipProfile(
            $row->string('name'),
            $row->string('address'),
            $row->string('phone'),
            new OpeningHours($row->string('opens_at'), $row->string('closes_at'), $row->string('open_days')),
            $row->int('aged_stock_days'),
            $row->intOrNull('default_apr_bp') ?? Payments::STANDARD_APR,
        );
    }
}

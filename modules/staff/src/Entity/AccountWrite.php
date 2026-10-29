<?php

declare(strict_types=1);

namespace App\Staff\Entity;

/**
 * What a guarded write to a member did (#22): `EntityStore::transaction()` runs a closure that returns nothing, so the
 * write keeps its outcome here and `AccountRepository` reads it once the transaction has committed, as holds'
 * `HoldWrite` keeps a hold's id.
 */
final class AccountWrite
{
    /**
     * The member was written.
     */
    public const string SAVED = 'saved';

    /**
     * The stored member differs from what the form showed, so nothing was written.
     */
    public const string STALE = 'stale';

    /**
     * The write would leave no active admin, so nothing was written.
     */
    public const string LAST_ADMIN = 'last-admin';

    /**
     * No member has that id.
     */
    public const string MISSING = 'missing';

    /**
     * The outcome; MISSING until the transaction says otherwise.
     */
    private string $outcome = self::MISSING;

    /**
     * @param string $outcome One of the constants.
     */
    public function record(string $outcome): void
    {
        $this->outcome = $outcome;
    }

    /**
     * @return string The outcome the transaction recorded.
     */
    public function outcome(): string
    {
        return $this->outcome;
    }
}

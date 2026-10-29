<?php

declare(strict_types=1);

namespace App\Settings\Entity;

/**
 * What a settings transaction did (#22): the profile saved or found stale, a password link used or found used already.
 * `EntityStore::transaction()` runs a closure that returns nothing, so the closure keeps its outcome here and the
 * repository reads it once the transaction has committed, as staff's `AccountWrite` does for a member.
 */
final class SettingsWrite
{
    /**
     * The row was written.
     */
    public const string SAVED = 'saved';

    /**
     * Nothing was written: the profile is no longer the one shown, or the link was used meanwhile.
     */
    public const string STALE = 'stale';

    /**
     * The outcome; STALE until the transaction says otherwise.
     */
    private string $outcome = self::STALE;

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

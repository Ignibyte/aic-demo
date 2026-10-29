<?php

declare(strict_types=1);

namespace App\Settings\Service;

use function implode;

/**
 * What a 409 says (#22): a save was made on a stale copy, so nothing was written, and here is each field that someone
 * else changed since the form was opened, what the form showed then and what is stored now.
 */
final readonly class ChangeLines
{
    /**
     * @param string                $what   What was saved, such as `the profile` or `this member`.
     * @param array<string, string> $labels Each field's label, by name, in the order to list them.
     * @param array<string, string> $then   Each field's value as the form showed it, by name.
     * @param array<string, string> $now    Each field's value as stored now, by name.
     *
     * @return string The message: who saved, that nothing was, what changed, and to save again.
     */
    public function message(string $what, array $labels, array $then, array $now): string
    {
        $changes = [];

        foreach ($labels as $field => $label) {
            $before = $this->value($then, $field);
            $after = $this->value($now, $field);

            if ($before === $after) {
                continue;
            }

            $changes[] = $label . ' was "' . $before . '" and is now "' . $after . '"';
        }

        return 'Someone saved ' . $what . ' after you opened it, so your changes were not saved. Since then: '
            . implode('; ', $changes) . '. Your changes are still in the form: check them and save again to keep them.';
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
}

<?php

declare(strict_types=1);

namespace App\Site\Entity;

use InvalidArgumentException;

use function str_ends_with;
use function str_starts_with;

/**
 * One row of the Coverage page (#38): a surface `bin/aic code:coverage` lists, by its group and name, and how Lotline
 * covers it. A shown row names the page where the surface's work can be seen and the kept browser test that holds it;
 * a reasoned row says why Lotline does not use it. A row is one or the other, never both and never neither.
 * `config/coverage.php` holds every row; the Coverage page's browser test holds them to `code:coverage`.
 */
final readonly class CoverageRow
{
    /**
     * @param string $group   Its group, as `code:coverage` names it (`contracts`, `tessera`).
     * @param string $name    The surface, as `code:coverage` names it.
     * @param string $example The path of the page where its work shows; empty for a reasoned row.
     * @param string $test    The file name of the browser test in `tests/browser/` that holds it; empty for a reasoned
     *                        row.
     * @param string $reason  Why Lotline does not use it; empty for a shown row.
     *
     * @throws InvalidArgumentException When the row is both shown and reasoned, or neither, or a part is malformed.
     */
    private function __construct(
        public string $group,
        public string $name,
        public string $example,
        public string $test,
        public string $reason,
    ) {
        if ($group === '' || $name === '') {
            throw new InvalidArgumentException('A coverage row names its group and its surface.');
        }

        if (($example !== '' || $test !== '') === ($reason !== '')) {
            throw new InvalidArgumentException('The coverage row of "' . $name . '" is shown or reasoned, not both or neither.');
        }
    }

    /**
     * @return bool Whether Lotline uses the surface: the row is shown, not reasoned.
     */
    public function used(): bool
    {
        return $this->reason === '';
    }

    /**
     * @param string $group   Its group.
     * @param string $name    The surface.
     * @param string $example The path of the page where its work shows, such as `/app/inventory`.
     * @param string $test    The kept browser test that holds it, such as `inventory-filter.spec.ts`.
     *
     * @throws InvalidArgumentException When a part is empty or malformed.
     *
     * @return self A row for a surface Lotline uses.
     */
    public static function shown(string $group, string $name, string $example, string $test): self
    {
        if (!str_starts_with($example, '/') || !str_ends_with($test, '.spec.ts')) {
            throw new InvalidArgumentException('The coverage row of "' . $name . '" names a path and a browser test.');
        }

        return new self($group, $name, $example, $test, '');
    }

    /**
     * @param string $group  Its group.
     * @param string $name   The surface.
     * @param string $reason Why Lotline does not use it.
     *
     * @throws InvalidArgumentException When a part is empty.
     *
     * @return self A row for a surface Lotline does not use.
     */
    public static function reasoned(string $group, string $name, string $reason): self
    {
        return new self($group, $name, '', '', $reason);
    }
}

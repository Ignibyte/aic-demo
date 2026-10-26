<?php

declare(strict_types=1);

namespace App\Site\Form;

use function array_key_exists;
use function preg_match;
use function strlen;
use function trim;

/**
 * The style guide's sample vehicle form as posted, and what is wrong with it: a name, a price in whole dollars, a body
 * style from the list, and optional notes.
 */
final readonly class ThemeEntry
{
    /**
     * The body styles the form offers, by value.
     */
    public const array BODY_STYLES = [
        'coupe' => 'Coupe',
        'hatchback' => 'Hatchback',
        'sedan' => 'Sedan',
        'suv' => 'SUV',
        'truck' => 'Truck',
    ];

    /**
     * What each field's problem says, by field name.
     */
    private const array PROBLEMS = [
        'body_style' => 'Choose a body style.',
        'name' => 'Give the vehicle a name of up to 80 characters.',
        'notes' => 'Keep the notes under 500 characters.',
        'price' => 'Give a price in whole dollars, up to 1,000,000.',
    ];

    /**
     * @param string $name      The vehicle's name.
     * @param string $price     Its price, as typed.
     * @param string $bodyStyle Its body style's value.
     * @param string $notes     Its notes.
     */
    public function __construct(
        public string $name,
        public string $price,
        public string $bodyStyle,
        public string $notes,
    ) {
    }

    /**
     * @return array<string, string> What is wrong, by field name; empty when the entry is valid.
     */
    public function problems(): array
    {
        $wrong = [
            'body_style' => !array_key_exists($this->bodyStyle, self::BODY_STYLES),
            'name' => trim($this->name) === '' || strlen($this->name) > 80,
            'notes' => strlen($this->notes) > 500,
            'price' => preg_match('/^[0-9]{1,7}$/', $this->price) !== 1 || (int) $this->price > 1_000_000,
        ];

        $problems = [];

        foreach ($wrong as $field => $isWrong) {
            if (!$isWrong) {
                continue;
            }

            $problems[$field] = self::PROBLEMS[$field];
        }

        return $problems;
    }
}

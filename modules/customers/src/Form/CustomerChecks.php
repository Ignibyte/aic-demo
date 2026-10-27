<?php

declare(strict_types=1);

namespace App\Customers\Form;

use AIC\Database\Contracts\QueryException;
use App\Customers\Entity\CustomerDetails;
use App\Customers\Repository\CustomerRecordRepository;

use function mb_strlen;
use function preg_match;
use function strlen;
use function strtolower;
use function trim;

/**
 * The customer form's checks, one per field: a name, a valid email no other customer has, a plausible phone when one
 * is given, and the address's and the notes' lengths. A valid entry becomes the `CustomerDetails` it describes.
 */
final readonly class CustomerChecks
{
    /**
     * Why an email another customer holds is refused.
     */
    public const string EMAIL_TAKEN = 'Another customer has this email.';

    /**
     * A valid email as the HTML standard defines it, with at least one dot in its domain.
     */
    private const string EMAIL = '/^[A-Za-z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?'
        . '(?:\\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/';

    /**
     * A plausible phone: 7 to 30 digits, spaces and `+ ( ) - .`, at least 7 of them digits.
     */
    private const string PHONE = '/^(?=(?:[^0-9]*[0-9]){7})[0-9+().\\- ]{7,30}$/';

    /**
     * @param CustomerRecordRepository $customerRecordRepository Knows which emails are taken.
     */
    public function __construct(
        private CustomerRecordRepository $customerRecordRepository,
    ) {
    }

    /**
     * @param CustomerEntry $customerEntry What was posted.
     * @param int           $currentId     The id of the customer being changed, which may keep its email; 0 for a new
     *                                     one.
     *
     * @throws QueryException When the taken emails cannot be read.
     *
     * @return array<string, string> Why each refused field is refused, by name; empty when the entry is valid.
     */
    public function errors(CustomerEntry $customerEntry, int $currentId): array
    {
        $errors = [
            'address' => $this->lengthError($customerEntry->value('address'), 300, 'The address is at most 300 characters.'),
            'email' => $this->emailError($this->email($customerEntry), $currentId),
            'name' => $this->nameError(trim($customerEntry->value('name'))),
            'notes' => $this->lengthError($customerEntry->value('notes'), 2_000, 'The notes are at most 2,000 characters.'),
            'phone' => $this->phoneError(trim($customerEntry->value('phone'))),
        ];
        $refused = [];

        foreach ($errors as $field => $error) {
            if ($error === '') {
                continue;
            }

            $refused[$field] = $error;
        }

        return $refused;
    }

    /**
     * @param CustomerEntry $customerEntry An entry `errors()` found valid.
     *
     * @return CustomerDetails The details it describes: the texts trimmed, the email in small letters.
     */
    public function details(CustomerEntry $customerEntry): CustomerDetails
    {
        return new CustomerDetails(
            trim($customerEntry->value('name')),
            $this->email($customerEntry),
            trim($customerEntry->value('phone')),
            trim($customerEntry->value('address')),
            trim($customerEntry->value('notes')),
        );
    }

    /**
     * @param CustomerEntry $customerEntry What was posted.
     *
     * @return string Its email, trimmed and in small letters.
     */
    private function email(CustomerEntry $customerEntry): string
    {
        return strtolower(trim($customerEntry->value('email')));
    }

    /**
     * @param string $name A posted name, trimmed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function nameError(string $name): string
    {
        if ($name === '') {
            return "Enter the customer's name.";
        }

        return mb_strlen($name) > 120
            ? 'The name is at most 120 characters.'
            : '';
    }

    /**
     * @param string $email     A posted email, trimmed and in small letters.
     * @param int    $currentId The id of the customer being changed; 0 for a new one.
     *
     * @throws QueryException When the taken emails cannot be read.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function emailError(string $email, int $currentId): string
    {
        if ($email === '') {
            return 'Enter the email.';
        }

        if (strlen($email) > 254) {
            return 'The email is at most 254 characters.';
        }

        if (preg_match(self::EMAIL, $email) !== 1) {
            return 'Enter an email such as name@example.com.';
        }

        return $this->customerRecordRepository->taken($email, $currentId)
            ? self::EMAIL_TAKEN
            : '';
    }

    /**
     * @param string $phone A posted phone, trimmed.
     *
     * @return string Why it is refused; empty when it is empty or plausible.
     */
    private function phoneError(string $phone): string
    {
        return $phone === '' || preg_match(self::PHONE, $phone) === 1
            ? ''
            : 'A phone is 7 to 30 digits, spaces and + ( ) - . characters, with at least 7 digits.';
    }

    /**
     * @param string $text    A posted text.
     * @param int    $longest The most characters it may have.
     * @param string $message Why a longer one is refused.
     *
     * @return string The message when it is refused; empty when it is short enough.
     */
    private function lengthError(string $text, int $longest, string $message): string
    {
        return mb_strlen(trim($text)) > $longest
            ? $message
            : '';
    }
}

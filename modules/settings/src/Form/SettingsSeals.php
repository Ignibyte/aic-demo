<?php

declare(strict_types=1);

namespace App\Settings\Form;

use AIC\Component\Domain\HiddenInput;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function array_keys;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * What the settings seal with AIC's signed envelope (#22). A form's envelope seals the path it posts to and each value
 * the form showed, so a post is held to its record and a save can tell whether the record changed since: when the
 * stored values are no longer the shown ones, the save was made on a stale copy. A password link seals its row's id
 * with a purpose of its own, so no form's envelope opens as a link. Sealed values are signed, not hidden.
 */
final readonly class SettingsSeals
{
    /**
     * The sealed path's name.
     */
    private const string PATH = 'path';

    /**
     * The prefix of each shown value's name.
     */
    private const string SHOWN = 'shown.';

    /**
     * The sealed purpose's name.
     */
    private const string PURPOSE = 'purpose';

    /**
     * A password link's purpose.
     */
    private const string PASSWORD_LINK = 'password-link';

    /**
     * The sealed link id's name.
     */
    private const string LINK = 'link';

    /**
     * @param EnvelopeCodec $envelopeCodec Seals and opens envelopes with the app secret.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
    ) {
    }

    /**
     * @param string                $path  The path the form posts to.
     * @param array<string, string> $shown Each value the form showed, by field name.
     *
     * @throws LogicException When the values cannot be encoded, which strings always can.
     *
     * @return HiddenInput The `aic_envelope` field sealing them.
     */
    public function field(string $path, array $shown): HiddenInput
    {
        $values = [self::PATH => $path];

        foreach ($shown as $name => $value) {
            $values[self::SHOWN . $name] = $value;
        }

        try {
            return $this->envelopeCodec->field($values);
        } catch (JsonException $jsonException) {
            throw new LogicException('The form for "' . $path . '" cannot be sealed.', 0, $jsonException);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted settings form.
     * @param string                 $path          The path it was posted to.
     *
     * @return array<string, string>|null The values the form showed, by field name; null when the envelope is missing,
     *                                    altered or another form's.
     */
    public function shown(ServerRequestInterface $serverRequest, string $path): ?array
    {
        try {
            $values = $this->envelopeCodec->open($serverRequest);
        } catch (InvalidEnvelope) {
            return null;
        }

        if ($this->text($values, self::PATH) !== $path) {
            return null;
        }

        $shown = [];

        foreach (array_keys($values) as $name) {
            $field = $this->shownField($name);

            if ($field === '') {
                continue;
            }

            $shown[$field] = $this->text($values, $name);
        }

        return $shown;
    }

    /**
     * @param int $id A password link's id.
     *
     * @throws LogicException When the id cannot be encoded, which a number always can.
     *
     * @return string The link's token, the last segment of `/password/{token}`.
     */
    public function linkToken(int $id): string
    {
        try {
            return $this->envelopeCodec->seal([self::PURPOSE => self::PASSWORD_LINK, self::LINK => (string) $id]);
        } catch (JsonException $jsonException) {
            throw new LogicException('Password link ' . $id . ' cannot be sealed.', 0, $jsonException);
        }
    }

    /**
     * @param string $token The last segment of a `/password/{token}` request.
     *
     * @return int The link's id; 0 when the token is altered, malformed or seals no password link.
     */
    public function linkId(string $token): int
    {
        try {
            $values = $this->envelopeCodec->openToken($token);
        } catch (InvalidEnvelope) {
            return 0;
        }

        $id = $this->text($values, self::LINK);

        return $this->text($values, self::PURPOSE) === self::PASSWORD_LINK && preg_match('/^[1-9]\\d{0,17}$/', $id) === 1
            ? (int) $id
            : 0;
    }

    /**
     * Reads one value through a native `string` return, so the compiled site knows its type before it compares or
     * matches it (elephc types an element read straight from an array as mixed; no AIC ticket carries it yet).
     *
     * @param array<string, string> $values An envelope's values.
     * @param string                $name   One of their names.
     *
     * @return string Its value; empty when it has none.
     */
    private function text(array $values, string $name): string
    {
        return $values[$name] ?? '';
    }

    /**
     * @param string $name A sealed value's name.
     *
     * @return string The form field it was shown in, when it is a shown value; empty for the path or the purpose.
     */
    private function shownField(string $name): string
    {
        return str_starts_with($name, self::SHOWN)
            ? substr($name, strlen(self::SHOWN))
            : '';
    }
}

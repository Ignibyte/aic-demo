<?php

declare(strict_types=1);

namespace App\Staff\Form;

use AIC\Component\Domain\HiddenInput;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Tessera\Application\TesseraProps;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The signed envelope a record's edit or delete form carries: it seals the path of the record the form was issued
 * for, so a post is held to that record and no other. Vehicles and customers share it. A list's row carries the same
 * seal as its Tessera props (#28), so a row's save is held to its own record too.
 */
final readonly class StaffSeals
{
    /**
     * The sealed value's name.
     */
    private const string PATH = 'path';

    /**
     * Reads a row's props back from the Tessera request.
     */
    private TesseraProps $tesseraProps;

    /**
     * @param EnvelopeCodec $envelopeCodec Seals and opens envelopes with the app secret.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
    ) {
        $this->tesseraProps = new TesseraProps($envelopeCodec);
    }

    /**
     * @param string $path The record's path, such as `/app/inventory/{VIN}`.
     *
     * @throws LogicException When the path cannot be encoded, which a record's path always can.
     *
     * @return HiddenInput The `aic_envelope` field sealing it.
     */
    public function field(string $path): HiddenInput
    {
        try {
            return $this->envelopeCodec->field([self::PATH => $path]);
        } catch (JsonException $jsonException) {
            throw new LogicException('The record path "' . $path . '" cannot be sealed.', 0, $jsonException);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted edit or delete form.
     * @param string                 $path          The path of the record it was posted to.
     *
     * @return bool Whether its envelope is whole and seals that path; false when it is missing, altered or another's.
     */
    public function holds(ServerRequestInterface $serverRequest, string $path): bool
    {
        try {
            $values = $this->envelopeCodec->open($serverRequest);
        } catch (InvalidEnvelope) {
            return false;
        }

        return ($values[self::PATH] ?? '') === $path;
    }

    /**
     * @param string $path The record's path, such as `/app/inventory/{VIN}`.
     *
     * @throws LogicException When the path cannot be encoded, which a record's path always can.
     *
     * @return string The props token sealing it, for the record's row in a list (#28).
     */
    public function props(string $path): string
    {
        return $this->field($path)->value;
    }

    /**
     * @param ServerRequestInterface $serverRequest A Tessera request a row sent.
     * @param string                 $anchor        The row's anchor, without its `@`.
     * @param string                 $path          The path of the record the request was sent to.
     *
     * @return bool Whether the props Tessera sent back for that row are whole and seal that path; false when they are
     *              missing, altered or another record's.
     */
    public function rowHolds(ServerRequestInterface $serverRequest, string $anchor, string $path): bool
    {
        try {
            $props = $this->tesseraProps->forTarget($serverRequest, '@' . $anchor);
        } catch (InvalidEnvelope) {
            return false;
        }

        return ($props[self::PATH] ?? '') === $path;
    }
}

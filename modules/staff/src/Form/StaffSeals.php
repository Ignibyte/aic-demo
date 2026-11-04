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
 * seal as its Tessera props (#28), so a row's save is held to its own record too. A vehicle page's panel (#31) carries
 * its version as its props, which a refresh of it sends back and is read by its anchor.
 */
final readonly class StaffSeals
{
    /**
     * The sealed value's name.
     */
    private const string PATH = 'path';

    /**
     * A panel's sealed version's name.
     */
    private const string VERSION = 'version';

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

    /**
     * @param string $version What a panel's data is now.
     *
     * @throws LogicException When the version cannot be encoded, which a version of digits, dots and a time always can.
     *
     * @return string The props token sealing it, for the panel (#31).
     */
    public function version(string $version): string
    {
        try {
            return $this->envelopeCodec->seal([self::VERSION => $version]);
        } catch (JsonException $jsonException) {
            throw new LogicException('The version "' . $version . '" cannot be sealed.', 0, $jsonException);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a panel's route.
     * @param string                 $anchor        The panel's anchor, without its `@`.
     *
     * @return bool Whether the props sent back for it, if any, are whole; false when they were altered.
     */
    public function propsHold(ServerRequestInterface $serverRequest, string $anchor): bool
    {
        try {
            $this->tesseraProps->forTarget($serverRequest, '@' . $anchor);
        } catch (InvalidEnvelope) {
            return false;
        }

        return true;
    }

    /**
     * @param ServerRequestInterface $serverRequest A request to a panel's route, whose props `propsHold()` found whole.
     * @param string                 $anchor        The panel's anchor, without its `@`.
     * @param string                 $version       What the panel's data is now.
     *
     * @return bool Whether it is a refresh of that panel alone whose props carry that version; false for any other
     *              request, and for props that do not open.
     */
    public function unchanged(ServerRequestInterface $serverRequest, string $anchor, string $version): bool
    {
        try {
            return $this->tesseraProps->unchanged($serverRequest, '@' . $anchor, $version);
        } catch (InvalidEnvelope) {
            return false;
        }
    }
}

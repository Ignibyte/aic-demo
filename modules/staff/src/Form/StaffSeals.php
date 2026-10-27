<?php

declare(strict_types=1);

namespace App\Staff\Form;

use AIC\Component\Domain\HiddenInput;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The signed envelope a record's edit or delete form carries: it seals the path of the record the form was issued
 * for, so a post is held to that record and no other. Vehicles and customers share it.
 */
final readonly class StaffSeals
{
    /**
     * The sealed value's name.
     */
    private const string PATH = 'path';

    /**
     * @param EnvelopeCodec $envelopeCodec Seals and opens envelopes with the app secret.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
    ) {
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
}

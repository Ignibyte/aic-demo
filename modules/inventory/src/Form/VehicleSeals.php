<?php

declare(strict_types=1);

namespace App\Inventory\Form;

use AIC\Component\Domain\HiddenInput;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The signed envelope an edit or delete form carries: it seals the path of the vehicle the form was issued for, so a
 * post is held to that vehicle and no other.
 */
final readonly class VehicleSeals
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
     * @param string $path The vehicle's path, `/app/inventory/{VIN}`.
     *
     * @throws LogicException When the path cannot be encoded, which a path of VIN characters always can.
     *
     * @return HiddenInput The `aic_envelope` field sealing it.
     */
    public function field(string $path): HiddenInput
    {
        try {
            return $this->envelopeCodec->field([self::PATH => $path]);
        } catch (JsonException $jsonException) {
            throw new LogicException('The vehicle path "' . $path . '" cannot be sealed.', 0, $jsonException);
        }
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted edit or delete form.
     * @param string                 $path          The path of the vehicle it was posted to.
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

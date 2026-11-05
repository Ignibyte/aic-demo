<?php

declare(strict_types=1);

namespace App\Brochures\Service;

use AIC\Controller\Domain\FileDownload;
use AIC\Database\Contracts\QueryException;
use AIC\Queue\Application\PostgresQueue;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Storage\Application\PrivateFiles;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Each car for sale's one-page brochure (#44), kept in AIC's private files at `brochures/{vehicle id}.html`: queued
 * as storefront's `car-brochure` job whenever inventory changes the vehicle, written there by that job through AIC's
 * `PageFiles`, and served through AIC's `FileDownload`, which answers its ETag, conditions and byte ranges. AIC builds
 * `PageFiles` for the console alone, and this class is built for web pages too, so the job puts the page at `key()`
 * itself.
 *
 * @api
 */
final readonly class Brochures
{
    /**
     * The job that writes a brochure.
     */
    public const string JOB = 'car-brochure';

    /**
     * The brochure's media type: the page as the site would answer it.
     */
    private const string HTML = 'text/html; charset=utf-8';

    /**
     * @param PostgresQueue $postgresQueue Queues the job that writes a brochure.
     * @param PrivateFiles  $privateFiles  AIC's private files, where the brochures are kept.
     */
    public function __construct(
        private PostgresQueue $postgresQueue,
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param int $vehicleId The id of a vehicle that changed; 0, for a vehicle gone before its id was read, queues
     *                       nothing.
     *
     * @throws QueryException When the job cannot be stored.
     * @throws LogicException When its payload cannot be encoded, which a vehicle's id always can.
     */
    public function queue(int $vehicleId): void
    {
        if ($vehicleId < 1) {
            return;
        }

        try {
            $this->postgresQueue->dispatch(self::JOB, ['vehicle' => (string) $vehicleId], 0);
        } catch (JsonException $jsonException) {
            throw new LogicException('The car-brochure job of vehicle ' . $vehicleId . ' cannot be encoded.', 0, $jsonException);
        }
    }

    /**
     * @param int $vehicleId A vehicle's id.
     *
     * @return string Its brochure's key in the private files.
     */
    public function key(int $vehicleId): string
    {
        return 'brochures/' . $vehicleId . '.html';
    }

    /**
     * @param int $vehicleId A vehicle's id; 0 for none.
     *
     * @throws InvalidArgumentException When the key is not a storage key.
     *
     * @return bool Whether its brochure is written.
     */
    public function exists(int $vehicleId): bool
    {
        return $vehicleId > 0 && $this->privateFiles->exists($this->key($vehicleId));
    }

    /**
     * @param int $vehicleId The id of a vehicle that is not for sale.
     *
     * @throws InvalidArgumentException When the key is not a storage key.
     */
    public function forget(int $vehicleId): void
    {
        $this->privateFiles->delete($this->key($vehicleId));
    }

    /**
     * Sends the kept page as the site would answer it, `text/html`, inline or under a name, with what AIC's
     * `FileDownload` adds: a strong ETag, `Last-Modified`, `Accept-Ranges`, and 206, 304, 412 or 416 when the request asks.
     *
     * @param ServerRequestInterface $serverRequest The request, with its `Range` and conditions.
     * @param int                    $vehicleId     The car's id; 0 when the route names no car for sale.
     * @param string                 $downloadName  The name the browser saves it as; empty to open it inline.
     *
     * @throws RuntimeException         When there is no car or no brochure kept for it (`RouteNotFoundException`, which
     *                                  AIC answers 404).
     * @throws InvalidArgumentException When the key is not a storage key.
     *
     * @return FileDownload The brochure.
     */
    public function download(ServerRequestInterface $serverRequest, int $vehicleId, string $downloadName): FileDownload
    {
        $key = $this->key($vehicleId);

        if ($vehicleId < 1 || !$this->privateFiles->exists($key)) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return $this->privateFiles->download($key, self::HTML, $downloadName, $serverRequest);
    }
}

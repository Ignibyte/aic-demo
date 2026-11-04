<?php

declare(strict_types=1);

namespace App\Compare\Form;

use Psr\Http\Message\ServerRequestInterface;

use function count;
use function explode;
use function in_array;
use function urldecode;

/**
 * The slugs a comparison asks for (#33): every `car` value in the query string, in the order asked, each once, an empty
 * one left out. It reads the raw query string, since PHP's own parsing keeps only the last of repeated `car` values,
 * which is how the bar's link and its GET form send them.
 */
final readonly class CompareQuery
{
    /**
     * The most cars a comparison shows; one more is refused.
     */
    public const int MOST = 3;

    /**
     * The query parameter that names a car.
     */
    private const string NAME = 'car';

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return list<string> The asked slugs, as sent, in order and each once; not yet checked.
     */
    public function slugs(ServerRequestInterface $serverRequest): array
    {
        $slugs = [];

        foreach (explode('&', $serverRequest->getUri()->getQuery()) as $pair) {
            $slug = $this->slug($pair);

            if ($slug === '' || in_array($slug, $slugs, true)) {
                continue;
            }

            $slugs[] = $slug;
        }

        return $slugs;
    }

    /**
     * @param string $pair One `name=value` pair of the query string, as sent.
     *
     * @return string Its value, decoded, when its name is `car`; empty otherwise.
     */
    private function slug(string $pair): string
    {
        $parts = explode('=', $pair, 2);

        return count($parts) === 2 && urldecode($parts[0]) === self::NAME
            ? urldecode($parts[1])
            : '';
    }
}

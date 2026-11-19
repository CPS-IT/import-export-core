<?php

declare(strict_types=1);

/*
 * This file is part of the package cpsit/import-export-core.
 *
 * It is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License, either version 2 of the License, or (at
 * your option) any later version.
 *
 * For the full license information, please read the LICENSE file that was
 * distributed with this source code.
 */

namespace CPSIT\ImportExportCore\Resource;

use CPSIT\ImportExportCore\Exception\MissingResourceException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Loads resources from http and https URLs with a PSR-18 client. Requires the
 * packages psr/http-client and psr/http-factory and an implementation of
 * them.
 *
 * Loading URLs is not enabled by default: an application adds this loader
 * explicitly, e.g. to a {@see DelegatingResourceLoader}. A configuration can
 * then make the application request any URL the client reaches, so the client
 * should be restricted to the hosts that are needed.
 */
final class HttpResourceLoader implements ResourceLoaderInterface
{
    public const int ERROR_REQUEST_FAILED = 1795200301;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
    ) {
    }

    public function supports(string $location): bool
    {
        if (filter_var($location, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string)parse_url($location, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }

    public function open(string $location): StreamInterface
    {
        if (!$this->supports($location)) {
            throw $this->failed($location, 'not an http(s) URL');
        }
        try {
            $response = $this->client->sendRequest($this->requestFactory->createRequest('GET', $location));
        } catch (ClientExceptionInterface $exception) {
            throw $this->failed($location, $exception->getMessage(), $exception);
        }
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw $this->failed($location, 'status ' . $status);
        }

        return $response->getBody();
    }

    private function failed(string $location, string $reason, ?\Throwable $previous = null): MissingResourceException
    {
        return new MissingResourceException(
            \sprintf('Resource "%s" cannot be loaded: %s.', $location, $reason),
            self::ERROR_REQUEST_FAILED,
            $previous
        );
    }
}

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
 * then make the application request any URL the client reaches. Pass the
 * hosts that are needed as `$allowedHosts`: `example.org` allows this host,
 * `*.example.org` its subdomains. Requests to other hosts throw a
 * `MissingResourceException` before they are sent.
 *
 * Redirects (301, 302, 303, 307, 308) are followed by the loader, at most
 * `MAX_REDIRECTS`, with a GET request. The target of each redirect must be an
 * http(s) URL with an allowed host as well; the port may change. A redirect
 * from https to http is not followed. Default headers of the client are sent
 * with every request, also to the target of a redirect.
 *
 * The PSR-18 client must not follow redirects itself, as the loader cannot
 * check them then. `sendRequest()` of Guzzle does not follow redirects; the
 * `Psr18Client` of Symfony does by default, configure it with
 * `max_redirects: 0`.
 */
final class HttpResourceLoader implements ResourceLoaderInterface
{
    public const int ERROR_REQUEST_FAILED = 1795200301;
    public const int ERROR_HOST_NOT_ALLOWED = 1795200302;

    /**
     * Maximum number of redirects followed for one location
     */
    public const int MAX_REDIRECTS = 5;

    private const array REDIRECT_STATUS = [301, 302, 303, 307, 308];

    /**
     * @var list<string>|null
     */
    private readonly ?array $allowedHosts;

    /**
     * @param list<string>|null $allowedHosts hosts that may be requested,
     *                                        `*.example.org` for subdomains;
     *                                        null: all hosts
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        ?array $allowedHosts = null,
    ) {
        $this->allowedHosts = $allowedHosts === null
            ? null
            : array_map(static fn (string $host): string => strtolower(trim($host)), $allowedHosts);
    }

    public function isHostAllowed(string $location): bool
    {
        if ($this->allowedHosts === null) {
            return true;
        }
        $host = rtrim(strtolower((string)parse_url($location, PHP_URL_HOST)), '.');
        if ($host === '') {
            return false;
        }
        foreach ($this->allowedHosts as $allowedHost) {
            if ($host === $allowedHost
                || (str_starts_with($allowedHost, '*.') && str_ends_with($host, substr($allowedHost, 1)))
            ) {
                return true;
            }
        }

        return false;
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
        $url = $location;
        $previous = null;
        for ($redirects = 0; ; $redirects++) {
            $this->assertAllowed($url, $location);
            if (
                $previous !== null
                && strtolower((string)parse_url($previous, PHP_URL_SCHEME)) === 'https'
                && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'http'
            ) {
                throw $this->failed($location, 'a redirect leads from https to http');
            }
            try {
                $response = $this->client->sendRequest($this->requestFactory->createRequest('GET', $url));
            } catch (ClientExceptionInterface $exception) {
                throw $this->failed($location, $exception->getMessage(), $exception);
            }
            $status = $response->getStatusCode();
            $target = trim($response->getHeaderLine('Location'));
            if (!\in_array($status, self::REDIRECT_STATUS, true) || $target === '') {
                break;
            }
            $response->getBody()->close();
            if ($redirects >= self::MAX_REDIRECTS) {
                throw $this->failed($location, 'more than ' . self::MAX_REDIRECTS . ' redirects');
            }
            $previous = $url;
            $url = self::resolveRedirect($url, $target);
        }
        if ($status < 200 || $status >= 300) {
            throw $this->failed($location, 'status ' . $status);
        }

        return $response->getBody();
    }

    /**
     * @throws MissingResourceException if `$url` is not an http(s) URL or its host is not allowed
     */
    private function assertAllowed(string $url, string $location): void
    {
        if (!$this->supports($url)) {
            throw $this->failed($location, $url === $location ? 'not an http(s) URL' : 'a redirect leads to a URL that is not an http(s) URL');
        }
        if (!$this->isHostAllowed($url)) {
            throw new MissingResourceException(
                $url === $location
                    ? \sprintf('Resource "%s" cannot be loaded: the host is not allowed.', $location)
                    : \sprintf('Resource "%s" cannot be loaded: a redirect leads to a host that is not allowed.', $location),
                self::ERROR_HOST_NOT_ALLOWED
            );
        }
    }

    /**
     * Resolves the `Location` of a redirect against the URL of the request
     * (RFC 3986, section 5.2). The fragment is removed, it is not part of a
     * request. The result is checked like the original location.
     */
    private static function resolveRedirect(string $url, string $target): string
    {
        $base = self::parseReference($url);
        $reference = self::parseReference($target);
        if ($reference['scheme'] !== null) {
            $scheme = $reference['scheme'];
            $authority = $reference['authority'];
            $path = self::removeDotSegments($reference['path']);
            $query = $reference['query'];
        } else {
            $scheme = $base['scheme'];
            if ($reference['authority'] !== null) {
                $authority = $reference['authority'];
                $path = self::removeDotSegments($reference['path']);
                $query = $reference['query'];
            } else {
                $authority = $base['authority'];
                if ($reference['path'] === '') {
                    $path = $base['path'];
                    $query = $reference['query'] ?? $base['query'];
                } else {
                    if (str_starts_with($reference['path'], '/')) {
                        $path = self::removeDotSegments($reference['path']);
                    } elseif ($base['authority'] !== null && $base['path'] === '') {
                        $path = self::removeDotSegments('/' . $reference['path']);
                    } else {
                        $directory = substr($base['path'], 0, (int)strrpos($base['path'], '/') + 1);
                        $path = self::removeDotSegments($directory . $reference['path']);
                    }
                    $query = $reference['query'];
                }
            }
        }

        return ($scheme !== null ? $scheme . ':' : '')
            . ($authority !== null ? '//' . $authority : '')
            . $path
            . ($query !== null ? '?' . $query : '');
    }

    /**
     * Splits a URI reference into its components (RFC 3986, appendix B)
     *
     * @return array{scheme: string|null, authority: string|null, path: string, query: string|null}
     */
    private static function parseReference(string $reference): array
    {
        preg_match('#^(?:([^:/?\#]+):)?(?://([^/?\#]*))?([^?\#]*)(?:\?([^\#]*))?#', $reference, $matches, PREG_UNMATCHED_AS_NULL);

        return [
            'scheme' => $matches[1] ?? null,
            'authority' => $matches[2] ?? null,
            'path' => $matches[3] ?? '',
            'query' => $matches[4] ?? null,
        ];
    }

    /**
     * RFC 3986, section 5.2.4
     */
    private static function removeDotSegments(string $path): string
    {
        $output = [];
        while ($path !== '') {
            if (str_starts_with($path, '../')) {
                $path = substr($path, 3);
            } elseif (str_starts_with($path, './')) {
                $path = substr($path, 2);
            } elseif (str_starts_with($path, '/./')) {
                $path = substr($path, 2);
            } elseif ($path === '/.') {
                $path = '/';
            } elseif (str_starts_with($path, '/../')) {
                $path = substr($path, 3);
                array_pop($output);
            } elseif ($path === '/..') {
                $path = '/';
                array_pop($output);
            } elseif ($path === '.' || $path === '..') {
                $path = '';
            } else {
                $position = strpos($path, '/', 1);
                $segment = $position === false ? $path : substr($path, 0, $position);
                $output[] = $segment;
                $path = substr($path, \strlen($segment));
            }
        }

        return implode('', $output);
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

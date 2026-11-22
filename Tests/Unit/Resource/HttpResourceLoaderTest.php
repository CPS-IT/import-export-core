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

namespace CPSIT\ImportExportCore\Tests\Unit\Resource;

use CPSIT\ImportExportCore\Exception\MissingResourceException;
use CPSIT\ImportExportCore\Resource\HttpResourceLoader;
use CPSIT\ImportExportCore\Stream\TemporaryStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpResourceLoaderTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function locationProvider(): array
    {
        return [
            'https' => ['https://example.org/a.csv', true],
            'http' => ['http://example.org/a.csv', true],
            'upper case scheme' => ['HTTPS://example.org/a.csv', true],
            'ftp' => ['ftp://example.org/a.csv', false],
            'file' => ['file:///etc/passwd', false],
            'path' => ['data/a.csv', false],
        ];
    }

    #[DataProvider('locationProvider')]
    public function testSupports(string $location, bool $expected): void
    {
        $subject = new HttpResourceLoader(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class)
        );

        self::assertSame($expected, $subject->supports($location));
    }

    public function testReturnsBodyOfResponse(): void
    {
        $body = new TemporaryStream('a,b');
        $request = $this->createStub(RequestInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())->method('createRequest')
            ->with('GET', 'https://example.org/a.csv')
            ->willReturn($request);
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->with($request)
            ->willReturn($this->response(200, $body));

        self::assertSame($body, (new HttpResourceLoader($client, $requestFactory))->open('https://example.org/a.csv'));
    }

    public function testThrowsForErrorStatus(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn($this->response(404, new TemporaryStream()));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_REQUEST_FAILED);

        (new HttpResourceLoader($client, $this->requestFactory()))->open('https://example.org/a.csv');
    }

    public function testThrowsForClientException(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(
            new class('timeout') extends \RuntimeException implements ClientExceptionInterface {}
        );

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_REQUEST_FAILED);

        (new HttpResourceLoader($client, $this->requestFactory()))->open('https://example.org/a.csv');
    }

    public function testThrowsForUnsupportedLocation(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::never())->method('sendRequest');

        $this->expectException(MissingResourceException::class);

        (new HttpResourceLoader($client, $this->requestFactory()))->open('file:///etc/passwd');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function hostProvider(): array
    {
        return [
            'allowed host' => ['https://example.org/a.csv', true],
            'upper case' => ['https://EXAMPLE.org/a.csv', true],
            'trailing dot' => ['https://example.org./a.csv', true],
            'subdomain of wildcard' => ['https://data.example.com/a.csv', true],
            'other host' => ['https://example.net/a.csv', false],
            'subdomain without wildcard' => ['https://data.example.org/a.csv', false],
            'wildcard does not match the domain itself' => ['https://example.com/a.csv', false],
            'suffix of another domain' => ['https://evilexample.com/a.csv', false],
            'user info' => ['https://example.org@example.net/a.csv', false],
            'internal address' => ['http://127.0.0.1/a.csv', false],
        ];
    }

    #[DataProvider('hostProvider')]
    public function testAllowsConfiguredHostsOnly(string $location, bool $expected): void
    {
        $subject = new HttpResourceLoader(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            ['example.org', '*.example.com']
        );

        self::assertSame($expected, $subject->isHostAllowed($location));
    }

    public function testAllowsAllHostsWithoutList(): void
    {
        $subject = new HttpResourceLoader($this->createStub(ClientInterface::class), $this->createStub(RequestFactoryInterface::class));

        self::assertTrue($subject->isHostAllowed('http://127.0.0.1/a.csv'));
    }

    public function testDoesNotSendRequestToOtherHost(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::never())->method('sendRequest');

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_HOST_NOT_ALLOWED);

        (new HttpResourceLoader($client, $this->requestFactory(), []))->open('https://example.org/a.csv');
    }

    public function testDoesNotFollowRedirectToOtherHost(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')
            ->willReturn($this->response(302, new TemporaryStream(), 'http://127.0.0.1/a.csv'));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_HOST_NOT_ALLOWED);

        (new HttpResourceLoader($client, $this->requestFactory(), ['example.org']))->open('https://example.org/a.csv');
    }

    public function testDoesNotFollowRedirectToOtherScheme(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')
            ->willReturn($this->response(301, new TemporaryStream(), 'file:///etc/passwd'));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_REQUEST_FAILED);

        (new HttpResourceLoader($client, $this->requestFactory()))->open('https://example.org/a.csv');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function redirectProvider(): array
    {
        return [
            'absolute URL' => ['https://data.example.com/b.csv', 'https://data.example.com/b.csv'],
            'scheme-relative URL' => ['//data.example.com/b.csv', 'https://data.example.com/b.csv'],
            'absolute path' => ['/b.csv', 'https://example.org:8443/b.csv'],
            'relative path' => ['b.csv', 'https://example.org:8443/files/b.csv'],
            'query' => ['?page=2', 'https://example.org:8443/files/a.csv?page=2'],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testFollowsRedirectsToAllowedHosts(string $target, string $expected): void
    {
        $body = new TemporaryStream('a,b');
        $urls = [];
        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturnCallback(
            function (string $method, string $url) use (&$urls): RequestInterface {
                self::assertSame('GET', $method);
                $urls[] = $url;

                return $this->createStub(RequestInterface::class);
            }
        );
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturnOnConsecutiveCalls(
            $this->response(307, new TemporaryStream(), $target),
            $this->response(200, $body)
        );
        $subject = new HttpResourceLoader($client, $requestFactory, ['example.org', '*.example.com']);

        self::assertSame($body, $subject->open('https://example.org:8443/files/a.csv'));
        self::assertSame(['https://example.org:8443/files/a.csv', $expected], $urls);
    }

    public function testThrowsForRedirectWithoutLocation(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willReturn($this->response(302, new TemporaryStream()));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_REQUEST_FAILED);

        (new HttpResourceLoader($client, $this->requestFactory()))->open('https://example.org/a.csv');
    }

    public function testFollowsALimitedNumberOfRedirects(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::exactly(HttpResourceLoader::MAX_REDIRECTS + 1))->method('sendRequest')
            ->willReturn($this->response(302, new TemporaryStream(), '/a.csv'));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_REQUEST_FAILED);

        (new HttpResourceLoader($client, $this->requestFactory()))->open('https://example.org/a.csv');
    }

    private function requestFactory(): RequestFactoryInterface
    {
        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($this->createStub(RequestInterface::class));

        return $requestFactory;
    }

    private function response(int $status, TemporaryStream $body, string $location = ''): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getHeaderLine')->willReturnMap([['Location', $location]]);
        $response->method('getBody')->willReturn($body);

        return $response;
    }
}

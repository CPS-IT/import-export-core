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
use Psr\Http\Message\StreamInterface;

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
        $this->expectExceptionMessage('a redirect leads to a URL that is not an http(s) URL');

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
            'dot segments' => ['./b/../../c/./d.csv', 'https://example.org:8443/c/d.csv'],
            'dot segments above the root' => ['/../../b.csv', 'https://example.org:8443/b.csv'],
            'fragment' => ['b.csv#part', 'https://example.org:8443/files/b.csv'],
            'other port' => ['https://example.org:9443/b.csv', 'https://example.org:9443/b.csv'],
            'empty path with query and fragment' => ['#part', 'https://example.org:8443/files/a.csv'],
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

    /**
     * @return array<string, array{int}>
     */
    public static function redirectStatusProvider(): array
    {
        return [
            '301' => [301],
            '302' => [302],
            '303' => [303],
            '307' => [307],
            '308' => [308],
        ];
    }

    #[DataProvider('redirectStatusProvider')]
    public function testFollowsRedirectStatus(int $status): void
    {
        $body = new TemporaryStream('a,b');
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::exactly(2))->method('sendRequest')->willReturnOnConsecutiveCalls(
            $this->response($status, new TemporaryStream(), '/b.csv'),
            $this->response(200, $body)
        );

        self::assertSame($body, (new HttpResourceLoader($client, $this->requestFactory(), ['example.org']))->open('https://example.org/a.csv'));
    }

    public function testDoesNotFollowSecondRedirectToOtherHost(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::exactly(2))->method('sendRequest')->willReturnOnConsecutiveCalls(
            $this->response(302, new TemporaryStream(), 'https://data.example.org/b.csv'),
            $this->response(302, new TemporaryStream(), 'https://example.net/c.csv')
        );

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_HOST_NOT_ALLOWED);

        (new HttpResourceLoader($client, $this->requestFactory(), ['example.org', 'data.example.org']))->open('https://example.org/a.csv');
    }

    public function testDoesNotFollowRedirectToUserInfoWithOtherHost(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')
            ->willReturn($this->response(302, new TemporaryStream(), 'http://example.org@127.0.0.1/'));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_HOST_NOT_ALLOWED);

        (new HttpResourceLoader($client, $this->requestFactory(), ['example.org']))->open('http://example.org/a.csv');
    }

    public function testDoesNotFollowRedirectFromHttpsToHttp(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')
            ->willReturn($this->response(302, new TemporaryStream(), 'http://example.org/a.csv'));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_REQUEST_FAILED);
        $this->expectExceptionMessage('a redirect leads from https to http');

        (new HttpResourceLoader($client, $this->requestFactory(), ['example.org']))->open('https://example.org/a.csv');
    }

    public function testFollowsRedirectFromHttpToHttps(): void
    {
        $body = new TemporaryStream('a,b');
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturnOnConsecutiveCalls(
            $this->response(301, new TemporaryStream(), 'https://example.org/a.csv'),
            $this->response(200, $body)
        );

        self::assertSame($body, (new HttpResourceLoader($client, $this->requestFactory(), ['example.org']))->open('http://example.org/a.csv'));
    }

    public function testClosesTheBodyOfARedirect(): void
    {
        $redirectBody = $this->createMock(StreamInterface::class);
        $redirectBody->expects(self::once())->method('close');
        $body = new TemporaryStream('a,b');
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturnOnConsecutiveCalls(
            $this->response(302, $redirectBody, '/b.csv'),
            $this->response(200, $body)
        );

        self::assertSame($body, (new HttpResourceLoader($client, $this->requestFactory()))->open('https://example.org/a.csv'));
    }

    public function testFollowsMaximumNumberOfRedirects(): void
    {
        $body = new TemporaryStream('a,b');
        $responses = array_map(
            fn (): ResponseInterface => $this->response(302, new TemporaryStream(), '/a.csv'),
            range(1, HttpResourceLoader::MAX_REDIRECTS)
        );
        $responses[] = $this->response(200, $body);
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::exactly(HttpResourceLoader::MAX_REDIRECTS + 1))->method('sendRequest')
            ->willReturnOnConsecutiveCalls(...$responses);

        self::assertSame($body, (new HttpResourceLoader($client, $this->requestFactory()))->open('https://example.org/a.csv'));
    }

    public function testThrowsForRedirectWithoutLocation(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willReturn($this->response(302, new TemporaryStream()));

        $this->expectException(MissingResourceException::class);
        $this->expectExceptionCode(HttpResourceLoader::ERROR_REQUEST_FAILED);
        $this->expectExceptionMessage('status 302');

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

    private function response(int $status, StreamInterface $body, string $location = ''): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getHeaderLine')->willReturnMap([['Location', $location]]);
        $response->method('getBody')->willReturn($body);

        return $response;
    }
}

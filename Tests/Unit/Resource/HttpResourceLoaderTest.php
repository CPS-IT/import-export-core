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

    private function requestFactory(): RequestFactoryInterface
    {
        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($this->createStub(RequestInterface::class));

        return $requestFactory;
    }

    private function response(int $status, TemporaryStream $body): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);

        return $response;
    }
}

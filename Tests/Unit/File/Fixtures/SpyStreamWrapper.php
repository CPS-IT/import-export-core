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

namespace CPSIT\ImportExportCore\Tests\Unit\File\Fixtures;

/**
 * Stream wrapper `spy://` that records the paths opened through it and
 * serves a small DTD or entity text.
 */
final class SpyStreamWrapper
{
    public const string PROTOCOL = 'spy';

    /**
     * @var list<string>
     */
    public static array $opened = [];

    /**
     * @var resource|null
     */
    public $context;

    private string $content = '';

    private int $position = 0;

    public static function register(): void
    {
        self::$opened = [];
        if (!\in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }
    }

    public static function unregister(): void
    {
        if (\in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PROTOCOL);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$opened[] = $path;
        $this->content = str_ends_with($path, '.dtd') ? '<!ELEMENT a ANY>' : 'secret';

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->content, $this->position, $count);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen($this->content);
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => \strlen($this->content)];
    }

    /**
     * @return array<string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        return ['size' => 0, 'mode' => 0100644];
    }
}

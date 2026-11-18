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

namespace CPSIT\ImportExportCore\Build\Documentation;

/**
 * Reads the summary and the configuration section of a class docblock, see
 * Documentation/Decisions/0001-documentation.md.
 */
final class DocblockParser
{
    private const CONFIGURATION_MARKER = 'Configuration:';

    /**
     * The configuration is null if the docblock has no configuration section.
     *
     * @return array{summary: string, configuration: ?string}
     */
    public function parse(string $docComment): array
    {
        $lines = $this->stripCommentSyntax($docComment);
        $summary = [];
        $configuration = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, '@')) {
                break;
            }
            if ($configuration === null && str_starts_with($line, self::CONFIGURATION_MARKER)) {
                $rest = trim(substr($line, \strlen(self::CONFIGURATION_MARKER)));
                $configuration = $rest === '' ? [] : [$rest];
                continue;
            }
            if ($configuration === null) {
                $summary[] = $line;
            } else {
                $configuration[] = $line;
            }
        }

        return [
            'summary' => $this->join($summary),
            'configuration' => $configuration === null ? null : $this->join($configuration),
        ];
    }

    /**
     * @return list<string>
     */
    private function stripCommentSyntax(string $docComment): array
    {
        $text = preg_replace(['#^\s*/\*\*#', '#\*/\s*$#'], '', $docComment) ?? '';
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $lines[] = rtrim(preg_replace('#^\s*\* ?#', '', $line) ?? '');
        }

        return $lines;
    }

    /**
     * Joins the lines, removes blank lines at start and end and replaces
     * inline tags like {@see Foo} by `Foo`.
     *
     * @param list<string> $lines
     */
    private function join(array $lines): string
    {
        $text = trim(implode("\n", $lines));

        return preg_replace('/\{@(?:see|link) ([^}\s]+)}/', '`$1`', $text) ?? $text;
    }
}

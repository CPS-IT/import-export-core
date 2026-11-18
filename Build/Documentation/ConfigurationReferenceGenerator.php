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

use CPSIT\ImportExportCore\Persistence\DataSourceInterface;
use CPSIT\ImportExportCore\Persistence\DataTargetInterface;
use CPSIT\ImportExportCore\Pipeline\ConverterInterface;
use CPSIT\ImportExportCore\Pipeline\FilterInterface;
use CPSIT\ImportExportCore\Pipeline\FinisherInterface;
use CPSIT\ImportExportCore\Pipeline\InitializerInterface;
use CPSIT\ImportExportCore\Pipeline\PostProcessorInterface;
use CPSIT\ImportExportCore\Pipeline\PreProcessorInterface;

/**
 * Generates the configuration reference of the components (Markdown) from
 * their class docblocks, see Documentation/Decisions/0001-documentation.md.
 */
final class ConfigurationReferenceGenerator
{
    /**
     * Component types in the order of processing, with their key in the task
     * configuration.
     */
    private const TYPES = [
        InitializerInterface::class => ['Initializers', 'initializers'],
        DataSourceInterface::class => ['Data sources', 'source'],
        PreProcessorInterface::class => ['Pre-processors', 'preProcessors'],
        FilterInterface::class => ['Filters', 'filters'],
        ConverterInterface::class => ['Converters', 'converters'],
        PostProcessorInterface::class => ['Post-processors', 'postProcessors'],
        DataTargetInterface::class => ['Data targets', 'target'],
        FinisherInterface::class => ['Finishers', 'finishers'],
    ];

    /**
     * Namespaces whose classes are not configured by users.
     */
    private const EXCLUDED_NAMESPACES = [
        'CPSIT\\ImportExportCore\\Pipeline\\Legacy\\',
    ];

    public function __construct(
        private readonly DocblockParser $parser = new DocblockParser(),
    ) {
    }

    /**
     * Finds the components among the PHP classes below a PSR-4 directory.
     *
     * @return list<class-string>
     */
    public function findComponents(string $directory, string $namespace): array
    {
        $components = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = substr($file->getPathname(), \strlen(rtrim($directory, '/')) + 1, -4);
            $className = rtrim($namespace, '\\') . '\\' . str_replace('/', '\\', $relativePath);
            if (class_exists($className) && $this->isComponent($className)) {
                $components[] = $className;
            }
        }
        sort($components);

        return $components;
    }

    /**
     * @param class-string $className
     */
    public function isComponent(string $className): bool
    {
        foreach (self::EXCLUDED_NAMESPACES as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return false;
            }
        }
        if (!(new \ReflectionClass($className))->isInstantiable()) {
            return false;
        }

        return $this->getType($className) !== null;
    }

    /**
     * @param list<class-string> $classNames
     * @throws \UnexpectedValueException if a docblock does not follow the convention
     */
    public function generate(array $classNames): string
    {
        $sections = [];
        $errors = [];
        foreach ($classNames as $className) {
            $type = $this->getType($className);
            if ($type === null) {
                continue;
            }
            $docComment = (new \ReflectionClass($className))->getDocComment();
            $documentation = $this->parser->parse($docComment === false ? '' : $docComment);
            if ($documentation['configuration'] === null) {
                $errors[] = $className . ': no configuration section';
                continue;
            }
            foreach ($this->findInvalidLines($documentation['configuration']) as $line) {
                $errors[] = $className . ': invalid line "' . $line . '"';
            }
            $sections[$type][$className] = $documentation;
        }
        if ($errors !== []) {
            throw new \UnexpectedValueException(
                'Invalid component docblocks:' . \PHP_EOL . implode(\PHP_EOL, $errors),
                1795017601
            );
        }

        return $this->render($sections);
    }

    /**
     * A configuration section is `none.` or a list with one item per option:
     * `- `name` (type, required|default ...): description`. Continuation lines
     * and nested items are indented.
     *
     * @return list<string>
     */
    private function findInvalidLines(string $configuration): array
    {
        if ($configuration === 'none.') {
            return [];
        }
        $invalid = [];
        foreach (explode("\n", $configuration) as $line) {
            if ($line === '' || str_starts_with($line, '  ')) {
                continue;
            }
            if (preg_match('/^- `[^`]+` \(.+\)/', $line) !== 1) {
                $invalid[] = $line;
            }
        }

        return $invalid;
    }

    /**
     * @return ?class-string
     */
    private function getType(string $className): ?string
    {
        foreach (array_keys(self::TYPES) as $interface) {
            if (is_subclass_of($className, $interface)) {
                return $interface;
            }
        }

        return null;
    }

    /**
     * @param array<class-string, array<class-string, array{summary: string, configuration: ?string}>> $sections
     */
    private function render(array $sections): string
    {
        $lines = [
            '# Configuration of components',
            '',
            '<!-- Generated by `composer docs:reference` from the class docblocks. Do not edit. -->',
            '',
            'Components are configured in a task by `class` (class name or registered',
            'short name) and `config` (the options listed here):',
            '',
            '```yaml',
            'preProcessors:',
            '  10:',
            '    class: CPSIT\ImportExportCore\Processor\SetValuesPreProcessor',
            '    config:',
            '      values:',
            '        pid: 12',
            '```',
            '',
            'Initializers, filters, processors, converters and finishers also accept the',
            'option `disable`: `true`, or a condition with `expression` or',
            '`if.result.hasMessage`. Data sources are never disabled, data targets only if',
            'they implement `DisableableInterface`. See',
            '[Configuration of tasks and sets](README.md#disabling-components).',
        ];
        foreach (self::TYPES as $interface => [$title, $key]) {
            if (!isset($sections[$interface])) {
                continue;
            }
            $lines[] = '';
            $lines[] = '## ' . $title;
            $lines[] = '';
            $lines[] = 'Key in the task: `' . $key . '`';
            foreach ($sections[$interface] as $className => $documentation) {
                $lines[] = '';
                $lines[] = '### ' . substr($className, (int)strrpos($className, '\\') + 1);
                $lines[] = '';
                $lines[] = '`' . $className . '`';
                if ($documentation['summary'] !== '') {
                    $lines[] = '';
                    $lines[] = $documentation['summary'];
                }
                $lines[] = '';
                $lines[] = 'Configuration:';
                $lines[] = '';
                $lines[] = (string)$documentation['configuration'];
            }
        }

        return implode("\n", $lines) . "\n";
    }
}

# YAML Configuration Loader Implementation Plan

## Overview

This document outlines the implementation plan for adding YAML configuration support to the import-export extension system. The goal is to provide an alternative to TypoScript configuration that is more readable and easier to maintain, especially for complex import/export tasks.

## Current Status

Basic skeleton classes have been created but implementation is incomplete:

- `ConfigurationLoaderInterface`: Defines the common interface for all configuration loaders
- `YamlConfigurationLoader`: Skeleton implementation for YAML configuration loading
- `YamlConfigurationParser`: Skeleton service for parsing YAML files
- `TransferConfigurationInterface`: Interface for accessing transfer configuration (tasks and sets)

## Implementation Plan

### 1. Core Components (import-export-core)

#### 1.1 Complete the YamlConfigurationParser Service

```php
// Classes/Service/YamlConfigurationParser.php
class YamlConfigurationParser
{
    /**
     * Parse a YAML file and return the configuration array
     *
     * @param string $filePath Path to YAML file
     * @return array Parsed configuration
     * @throws FileNotFoundException If file does not exist
     * @throws ParseException If YAML parsing fails
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException('Configuration file not found: ' . $filePath);
        }

        try {
            return Yaml::parseFile($filePath);
        } catch (\Exception $e) {
            throw new ParseException('Error parsing YAML file: ' . $e->getMessage(), 1624543212, $e);
        }
    }

    /**
     * Parse YAML string and return the configuration array
     *
     * @param string $yamlContent YAML content
     * @return array Parsed configuration
     * @throws ParseException If YAML parsing fails
     */
    public function parseString(string $yamlContent): array
    {
        try {
            return Yaml::parse($yamlContent);
        } catch (\Exception $e) {
            throw new ParseException('Error parsing YAML content: ' . $e->getMessage(), 1624543213, $e);
        }
    }
}
```

#### 1.2 Complete the YamlConfigurationLoader Implementation

```php
// Classes/Configuration/YamlConfigurationLoader.php
class YamlConfigurationLoader implements ConfigurationLoaderInterface
{
    private YamlConfigurationParser $parser;

    public function __construct(YamlConfigurationParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Load configuration from YAML file
     *
     * @param string $path Path to YAML file
     * @return array Configuration array in TypoScript-compatible format
     */
    public function load(string $path): array
    {
        $configuration = $this->parser->parseFile($path);
        return $this->convertToTypoScriptFormat($configuration);
    }

    /**
     * Converts YAML structure to TypoScript-compatible array format
     *
     * @param array $configuration YAML-parsed configuration
     * @return array TypoScript-compatible configuration array
     */
    protected function convertToTypoScriptFormat(array $configuration): array
    {
        $result = [];

        // Handle import tasks
        if (isset($configuration['import']['tasks'])) {
            $result['module']['tx_t3importexport']['settings']['import']['tasks'] =
                $configuration['import']['tasks'];
        }

        // Handle import sets
        if (isset($configuration['import']['sets'])) {
            $result['module']['tx_t3importexport']['settings']['import']['sets'] =
                $configuration['import']['sets'];
        }

        // Handle export tasks
        if (isset($configuration['export']['tasks'])) {
            $result['module']['tx_t3importexport']['settings']['export']['tasks'] =
                $configuration['export']['tasks'];
        }

        // Handle export sets
        if (isset($configuration['export']['sets'])) {
            $result['module']['tx_t3importexport']['settings']['export']['sets'] =
                $configuration['export']['sets'];
        }

        return $result;
    }
}
```

#### 1.3 Create a Configuration Handler to Handle Multiple Configuration Sources

```php
// Classes/Configuration/ConfigurationHandler.php
class ConfigurationHandler implements TransferConfigurationInterface
{
    private array $configuration = [];

    /**
     * Add configuration from a loader
     *
     * @param ConfigurationLoaderInterface $loader
     * @param string $path Path to configuration file
     * @return self
     */
    public function addConfiguration(ConfigurationLoaderInterface $loader, string $path): self
    {
        $newConfig = $loader->load($path);
        $this->configuration = array_merge_recursive($this->configuration, $newConfig);
        return $this;
    }

    /**
     * Get import/export tasks
     *
     * @return array
     */
    public function getTasks(): array
    {
        $tasks = [];

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['import']['tasks'])) {
            $tasks['import'] = $this->configuration['module']['tx_t3importexport']['settings']['import']['tasks'];
        }

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['export']['tasks'])) {
            $tasks['export'] = $this->configuration['module']['tx_t3importexport']['settings']['export']['tasks'];
        }

        return $tasks;
    }

    /**
     * Get import/export sets
     *
     * @return array
     */
    public function getSets(): array
    {
        $sets = [];

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['import']['sets'])) {
            $sets['import'] = $this->configuration['module']['tx_t3importexport']['settings']['import']['sets'];
        }

        if (isset($this->configuration['module']['tx_t3importexport']['settings']['export']['sets'])) {
            $sets['export'] = $this->configuration['module']['tx_t3importexport']['settings']['export']['sets'];
        }

        return $sets;
    }

    /**
     * Get the full configuration array
     *
     * @return array
     */
    public function getFullConfiguration(): array
    {
        return $this->configuration;
    }
}
```

#### 1.4 Create Exceptions for Configuration Loading

```php
// Classes/Exception/FileNotFoundException.php
class FileNotFoundException extends \Exception {}

// Classes/Exception/ParseException.php
class ParseException extends \Exception {}
```

#### 1.5 Add Unit Tests

```php
// Tests/Unit/Configuration/YamlConfigurationLoaderTest.php
// Tests/Unit/Service/YamlConfigurationParserTest.php
// Tests/Unit/Configuration/ConfigurationHandlerTest.php
```

### 2. Integration in t3import_export Extension

#### 2.1 Create a YAML Configuration Provider

```php
// Classes/Configuration/YamlConfigurationProvider.php
class YamlConfigurationProvider implements \TYPO3\CMS\Core\SingletonInterface
{
    private ConfigurationHandler $configurationHandler;
    private YamlConfigurationLoader $yamlLoader;

    public function __construct(
        ConfigurationHandler $configurationHandler,
        YamlConfigurationLoader $yamlLoader
    ) {
        $this->configurationHandler = $configurationHandler;
        $this->yamlLoader = $yamlLoader;
    }

    /**
     * Load YAML configuration files from a directory
     *
     * @param string $directory Directory containing YAML files
     * @param string $extension File extension (default: yaml)
     * @return array Loaded configuration
     */
    public function loadFromDirectory(string $directory, string $extension = 'yaml'): array
    {
        $files = glob($directory . '/*.' . $extension);

        foreach ($files as $file) {
            $this->configurationHandler->addConfiguration($this->yamlLoader, $file);
        }

        return $this->configurationHandler->getFullConfiguration();
    }
}
```

#### 2.2 Register the YAML Configuration in TYPO3

Update the DI configuration to register the YAML configuration provider and its dependencies:

```yaml
# Configuration/Services.yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  CPSIT\ImportExportCore\Service\YamlConfigurationParser:
    public: true

  CPSIT\ImportExportCore\Configuration\YamlConfigurationLoader:
    public: true
    arguments:
      $parser: '@CPSIT\ImportExportCore\Service\YamlConfigurationParser'

  CPSIT\ImportExportCore\Configuration\ConfigurationHandler:
    public: true

  CPSIT\T3importExport\Configuration\YamlConfigurationProvider:
    public: true
    arguments:
      $configurationHandler: '@CPSIT\ImportExportCore\Configuration\ConfigurationHandler'
      $yamlLoader: '@CPSIT\ImportExportCore\Configuration\YamlConfigurationLoader'
```

#### 2.3 Update Extension Configuration to Include YAML Files

Modify the extension configuration to allow specifying YAML configuration directories:

```php
// ext_localconf.php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3import_export']['yamlConfigurationDirectories'] = [
    'EXT:t3import_export/Configuration/ImportExport',
];
```

#### 2.4 Load YAML Configurations During Extension Boot

```php
// Classes/Extension.php (or equivalent bootstrap class)
public function boot()
{
    // Load YAML configurations if enabled
    if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3import_export']['yamlConfigurationDirectories'])) {
        $yamlConfigProvider = GeneralUtility::makeInstance(YamlConfigurationProvider::class);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['t3import_export']['yamlConfigurationDirectories'] as $directory) {
            $resolvedPath = GeneralUtility::getFileAbsFileName($directory);
            if (is_dir($resolvedPath)) {
                $yamlConfigProvider->loadFromDirectory($resolvedPath);
            }
        }
    }
}
```

### 3. Command Line Integration

#### 3.1 Update Import/Export Commands to Support YAML Configuration

Modify the existing import/export commands to allow specifying YAML configuration files directly:

```php
// Classes/Command/ImportSetCommand.php and ExportSetCommand.php
// Add option to specify YAML configuration file
final public const array OPTIONS = [
    // ... existing options
    YamlConfigFileOption::class,
];

// In execute method
if ($input->getOption(YamlConfigFileOption::NAME)) {
    $yamlFile = $input->getOption(YamlConfigFileOption::NAME);
    $yamlLoader = GeneralUtility::makeInstance(YamlConfigurationLoader::class);
    $configHandler = GeneralUtility::makeInstance(ConfigurationHandler::class);
    $configManager->addConfiguration($yamlLoader, $yamlFile);
    // Use configuration from YAML file
}
```

#### 3.2 Create Option for YAML Configuration File

```php
// Classes/Command/Option/YamlConfigFileOption.php
class YamlConfigFileOption implements InputOptionInterface
{
    use InputOptionTrait;

    final public const string NAME = 'yaml-config';
    final public const string HELP = 'Path to YAML configuration file';
    final public const int MODE = InputOption::VALUE_REQUIRED;
    final public const string DESCRIPTION = 'YAML configuration file';
    final public const string SHORTCUT = 'y';
    final public const null DEFAULT = null;
}
```

### 4. Documentation

#### 4.1 Update Documentation to Include YAML Configuration

Add documentation for the YAML configuration format, including:
- Basic structure
- Examples for common use cases
- Migration guide from TypoScript to YAML
- Command line usage with YAML files

#### 4.2 Add Example YAML Configuration Files

Create example YAML configuration files in:
- `Resources/Public/Examples/Yaml/`

## Benefits

1. **Improved Readability**: YAML configurations are easier to read and maintain compared to TypoScript
2. **Better IDE Support**: Most IDEs have better support for YAML syntax highlighting and validation
3. **Simplified Structure**: The YAML format is more intuitive for complex nested configurations
4. **Flexible Configuration**: Support for both TypoScript and YAML allows gradual migration

## Implementation Timeline

1. **Phase 1**: Core components (2 days)
   - Complete the YAML parser and loader
   - Implement configuration manager
   - Write unit tests

2. **Phase 2**: Integration (2 days)
   - Create configuration provider
   - Update DI configuration
   - Implement extension boot process

3. **Phase 3**: CLI Integration (1 day)
   - Update CLI commands
   - Add command line options

4. **Phase 4**: Documentation and Examples (1 day)
   - Update documentation
   - Create example configurations

## Potential Challenges

1. **Configuration Merging**: Ensuring that configurations from different sources (TypoScript and YAML) are properly merged
2. **Path Resolution**: Handling file paths within YAML configurations, especially for extensions
3. **Backward Compatibility**: Maintaining compatibility with existing TypoScript configurations
4. **Performance**: Ensuring that adding YAML parsing doesn't impact performance significantly

## Conclusion

The YAML configuration loader will enhance the t3import_export extension by providing a more readable and maintainable configuration format. The implementation leverages the existing architecture while adding the flexibility of YAML configuration files.

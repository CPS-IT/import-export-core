<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore\Persistence;

use CPSIT\ImportExportCore\Component\ComponentInterface;

/**
 * Interface DataTargetInterface
 */
interface DataTargetInterface extends ComponentInterface
{
    /**
     * Persist both new and updated records.
     *
     * @param array|object $object Record to persist. Either an array or an instance of \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject
     * @param array $configuration Configuration array.
     * @return mixed
     */
    public function persist($object, ?array $configuration = null);

    /**
     * Persists all record or objects
     *
     * @param array|null $result
     * @param array|null $configuration
     * @return mixed
     */
    public function persistAll($result = null, ?array $configuration = null);
}

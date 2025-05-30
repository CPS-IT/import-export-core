<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore;

/**
 * Interface IdentifiableInterface
 */
interface IdentifiableInterface
{
    /**
     * Sets the identifier
     *
     * @param string $identifier
     * @return mixed
     */
    public function setIdentifier(string $identifier);

    /**
     * Gets the identifier
     *
     * @return string|null
     */
    public function getIdentifier(): ?string;
}

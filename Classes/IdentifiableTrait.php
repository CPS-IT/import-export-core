<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore;

/**
 * Trait IdentifiableTrait
 */
trait IdentifiableTrait
{
    /**
     * Unique identifier
     */
    protected ?string $identifier = null;

    /**
     * Sets the identifier
     *
     * @param string $identifier
     * @return void
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * Gets the identifier
     *
     * @return string|null
     */
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }
}

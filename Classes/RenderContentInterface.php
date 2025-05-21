<?php

declare(strict_types=1);

namespace CPSIT\ImportExportCore;

/**
 * Interface RenderContentInterface
 *
 * Provides methods for rendering content
 */
interface RenderContentInterface
{
    /**
     * @return mixed Rendered Content
     */
    public function renderContent(array $record, array $configuration): mixed;
}

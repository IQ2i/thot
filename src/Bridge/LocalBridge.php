<?php

namespace App\Bridge;

use App\Entity\Local;
use App\Entity\Source;

readonly class LocalBridge implements BridgeInterface
{
    public function supports(Source $source): bool
    {
        return $source instanceof Local;
    }

    /**
     * @param Local $source
     */
    public function importNewDocuments(Source $source, bool $syncAll): void
    {
        // Local documents are manually created, no import needed
    }

    /**
     * @param Local $source
     */
    public function updateDocuments(Source $source, bool $syncAll): void
    {
        // Local documents are manually updated, no sync needed
    }
}

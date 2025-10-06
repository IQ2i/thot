<?php

namespace App\Bridge;

use App\Entity\Source;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('iq2i_thot.bridge')]
interface BridgeInterface
{
    public function supports(Source $source): bool;

    public function importNewDocuments(Source $source, bool $syncAll): void;

    public function updateDocuments(Source $source, bool $syncAll): void;
}

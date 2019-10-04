<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Mapping;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;

interface MagentoMappingServiceInterface extends MappingServiceInterface
{
    public function getMagentoCountryUuid(string $iso, string $connectionId, Context $context): ?string;
}
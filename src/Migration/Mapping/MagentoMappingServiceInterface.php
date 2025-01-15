<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;

#[Package('fundamentals@after-sales')]
interface MagentoMappingServiceInterface extends MappingServiceInterface
{
    /**
     * @param array<string, mixed>|null $additionalData
     */
    public function createListItemMapping(
        string $connectionId,
        string $entityName,
        string $oldIdentifier,
        Context $context,
        ?array $additionalData = null,
        ?string $newUuid = null,
    ): void;

    public function getUuidList(string $connectionId, string $entityName, string $identifier, Context $context): array;
}

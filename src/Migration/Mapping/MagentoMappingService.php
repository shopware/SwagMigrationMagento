<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use SwagMigrationAssistant\Migration\Mapping\MappingService;
use SwagMigrationAssistant\Migration\Mapping\SwagMigrationMappingCollection;

#[Package('fundamentals@after-sales')]
class MagentoMappingService extends MappingService implements MagentoMappingServiceInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<TaxCollection> $taxRepo
     * @param EntityRepository<SwagMigrationMappingCollection> $migrationMappingRepo
     */
    public function __construct(
        protected EntityRepository $migrationMappingRepo,
        protected EntityWriterInterface $entityWriter,
        protected EntityDefinition $mappingDefinition,
        protected Connection $connection,
        protected LoggerInterface $logger,
        protected readonly EntityRepository $taxRepo,
    ) {
        parent::__construct(
            $migrationMappingRepo,
            $entityWriter,
            $mappingDefinition,
            $connection,
            $logger
        );
    }

    public function createListItemMapping(
        string $connectionId,
        string $entityName,
        string $oldIdentifier,
        Context $context,
        ?array $additionalData = null,
        ?string $newUuid = null,
    ): void {
        $uuid = Uuid::randomHex();
        if ($newUuid !== null) {
            $uuid = $newUuid;

            if ($this->isUuidDuplicate($connectionId, $entityName, $oldIdentifier, $newUuid, $context)) {
                return;
            }
        }

        $this->saveListMapping(
            [
                'id' => Uuid::randomHex(),
                'connectionId' => $connectionId,
                'entity' => $entityName,
                'oldIdentifier' => $oldIdentifier,
                'entityId' => $uuid,
                'entityValue' => null,
                'checksum' => null,
                'additionalData' => $additionalData,
            ]
        );
    }

    /**
     * @return list<string>
     */
    public function getUuidList(string $connectionId, string $entityName, string $identifier, Context $context): array
    {
        $cacheKey = $entityName . $identifier;
        if (isset($this->mappings[$cacheKey])) {
            return $this->mappings[$cacheKey];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('connectionId', $connectionId));
        $criteria->addFilter(new EqualsFilter('entity', $entityName));
        $criteria->addFilter(new EqualsFilter('oldIdentifier', $identifier));

        $result = $this->migrationMappingRepo->search($criteria, $context)->getEntities()->getElements();
        $uuidList = [];

        foreach ($result as $entity) {
            $entityId = $entity->getEntityId();

            if ($entityId === null) {
                continue;
            }

            $uuidList[] = $entityId;
        }

        $this->mappings[$cacheKey] = $uuidList;

        return $uuidList;
    }

    private function isUuidDuplicate(string $connectionId, string $entityName, string $id, string $uuid, Context $context): bool
    {
        foreach ($this->writeArray as $item) {
            if (
                $item['connectionId'] === $connectionId
                && $item['entity'] === $entityName
                && $item['oldIdentifier'] === $id
                && $item['entityId'] === $uuid
            ) {
                return true;
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('connectionId', $connectionId));
        $criteria->addFilter(new EqualsFilter('entity', $entityName));
        $criteria->addFilter(new EqualsFilter('oldIdentifier', $id));
        $criteria->addFilter(new EqualsFilter('entityId', $uuid));

        $result = $this->migrationMappingRepo->searchIds($criteria, $context);

        if ($result->getTotal() > 0) {
            return true;
        }

        return false;
    }
}

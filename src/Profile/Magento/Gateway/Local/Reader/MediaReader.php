<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

class MediaReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::MEDIA;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $paths = $this->fetchIdentifiers($this->tablePrefix . 'catalog_product_entity_media_gallery', 'value', $migrationContext->getOffset(), $migrationContext->getLimit(), true);
        $fetchedMedia = $this->fetchMedia($paths);
        $fetchedMedia = $this->utf8ize($fetchedMedia);

        return $this->cleanupResultSet($fetchedMedia);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}catalog_product_entity_media_gallery
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::MEDIA, $total);
    }

    private function fetchMedia(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'catalog_product_entity_media_gallery', 'media');
        $query->addSelect('DISTINCT media.value as path');

        $query->leftJoin(
            'media',
            $this->tablePrefix . 'catalog_product_entity_media_gallery_value',
            'media_details',
            'media.value_id = media_details.value_id AND media_details.store_id = 0'
        );
        $query->addSelect('media_details.label');

        $query->where('media.value IN (:id)');
        $query->setParameter('id', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}

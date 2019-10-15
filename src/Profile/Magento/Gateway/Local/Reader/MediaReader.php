<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class MediaReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::MEDIA;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $paths = $this->fetchIdentifiers('catalog_product_entity_media_gallery', 'value', $migrationContext->getOffset(), $migrationContext->getLimit(), true);
        $fetchedMedia = $this->fetchMedia($paths);
        $fetchedMedia = $this->utf8ize($fetchedMedia);

        return $this->cleanupResultSet($fetchedMedia);
    }

    private function fetchMedia(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('catalog_product_entity_media_gallery', 'media');
        $query->addSelect('DISTINCT media.value as path');

        $query->leftJoin(
            'media',
            'catalog_product_entity_media_gallery_value',
            'media_details',
            'media.value_id = media_details.value_id AND media_details.store_id = 0'
        );
        $query->addSelect('media_details.label');

        $query->where('media.value IN (:id)');
        $query->setParameter('id', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}

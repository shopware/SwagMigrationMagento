<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class MediaReader extends AbstractReader
{
    /**
     * @psalm-suppress PossiblyInvalidArgument
     */
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
SELECT count(DISTINCT value)
FROM {$this->tablePrefix}catalog_product_entity_media_gallery
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::MEDIA, $total);
    }

    protected function fetchMedia(array $ids): array
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

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }
}

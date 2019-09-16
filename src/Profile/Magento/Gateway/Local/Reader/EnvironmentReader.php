<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use SwagMigrationAssistant\Migration\MigrationContextInterface;

class EnvironmentReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $locale = $this->getDefaultShopLocale();

        $resultSet = [
            'defaultShopLanguage' => $locale,
            'host' => $this->getHost(),
            'additionalData' => $this->getAdditionalData(),
            'defaultCurrency' => $this->getDefaultCurrency(),
        ];

        return $resultSet;
    }

    protected function getHost(): string
    {
        return '';
    }

    protected function getDefaultShopLocale()
    {
        $query = $this->connection->createQueryBuilder();

        return $query->select('value')
            ->from('core_config_data')
            ->where('scope = "default"')
            ->andWhere('path = "general/locale/code"')
            ->execute()
            ->fetch(\PDO::FETCH_COLUMN);
    }

    protected function getDefaultCurrency()
    {
        $query = $this->connection->createQueryBuilder();

        return $query->select('value')
            ->from('core_config_data')
            ->where('scope = "default"')
            ->andWhere('path = "currency/options/base"')
            ->execute()
            ->fetch(\PDO::FETCH_COLUMN);
    }

    protected function getAdditionalData(): array
    {
        return [];
//        $query = $this->connection->createQueryBuilder();

//        $query->from('s_core_shops', 'shop');
//        $query->addSelect('shop.id as identifier');
//        $this->addTableSelection($query, 's_core_shops', 'shop');
//
//        $query->leftJoin('shop', 's_core_locales', 'locale', 'shop.locale_id = locale.id');
//        $this->addTableSelection($query, 's_core_locales', 'locale');
//
//        $query->orderBy('shop.main_id');
//
//        $fetchedShops = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
//        $shops = $this->mapData($fetchedShops, [], ['shop']);
//
//        foreach ($shops as $key => &$shop) {
//            if (!empty($shop['main_id'])) {
//                $shops[$shop['main_id']]['children'][] = $shop;
//                unset($shops[$key]);
//            }
//        }
//
//        return array_values($shops);
    }
}

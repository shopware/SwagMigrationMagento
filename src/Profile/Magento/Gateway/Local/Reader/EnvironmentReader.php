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
            ->from($this->tablePrefix . 'core_config_data')
            ->where('scope = "default"')
            ->andWhere('path = "general/locale/code"')
            ->execute()
            ->fetch(\PDO::FETCH_COLUMN);
    }

    protected function getDefaultCurrency()
    {
        $query = $this->connection->createQueryBuilder();

        return $query->select('value')
            ->from($this->tablePrefix . 'core_config_data')
            ->where('scope = "default"')
            ->andWhere('path = "currency/options/base"')
            ->execute()
            ->fetch(\PDO::FETCH_COLUMN);
    }

    protected function getAdditionalData(): array
    {
        return [];
    }
}

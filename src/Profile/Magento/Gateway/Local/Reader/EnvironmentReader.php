<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactoryInterface;
use SwagMigrationAssistant\Migration\Gateway\Reader\EnvironmentReaderInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class EnvironmentReader implements EnvironmentReaderInterface
{
    /**
     * @var ConnectionFactoryInterface
     */
    protected $connectionFactory;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $tablePrefix;

    public function __construct(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
        $this->tablePrefix = '';
    }

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

    protected function setConnection(MigrationContextInterface $migrationContext): void
    {
        $this->connection = $this->connectionFactory->createDatabaseConnection($migrationContext);

        $credentials = $migrationContext->getConnection()->getCredentialFields();
        if (isset($credentials['tablePrefix'])) {
            $this->tablePrefix = $credentials['tablePrefix'];
        }
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

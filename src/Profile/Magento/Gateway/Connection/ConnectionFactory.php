<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Connection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionException;
use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Exception\MigrationMagentoException;
use SwagMigrationAssistant\Exception\MigrationException;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('fundamentals@after-sales')]
class ConnectionFactory implements ConnectionFactoryInterface
{
    public function createDatabaseConnection(MigrationContextInterface $migrationContext): Connection
    {
        $connection = $migrationContext->getConnection();
        $credentials = $connection->getCredentialFields();

        if ($credentials === null) {
            throw MigrationMagentoException::databaseConnectionError();
        }

        $connectionParams = [
            'dbname' => (string) ($credentials['dbName'] ?? ''),
            'user' => (string) ($credentials['dbUser'] ?? ''),
            'password' => (string) ($credentials['dbPassword'] ?? ''),
            'host' => (string) ($credentials['dbHost'] ?? ''),
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
        ];

        if (isset($credentials['dbPort'])) {
            $connectionParams['port'] = (int) $credentials['dbPort'];
        }

        try {
            $connection = DriverManager::getConnection($connectionParams);
        } catch (\Throwable) {
            throw MigrationMagentoException::databaseConnectionError();
        }

        $this->ensureConnectionAttributes($connection);

        if (!isset($credentials['tablePrefix']) || $credentials['tablePrefix'] === '') {
            return $connection;
        }

        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$credentials['tablePrefix'] . 'customer_entity'])) {
            throw MigrationMagentoException::incorrectTablePrefix((string) $credentials['tablePrefix']);
        }

        return $connection;
    }

    private function ensureConnectionAttributes(Connection $connection): void
    {
        try {
            $nativeConnection = $connection->getNativeConnection();
            // we can assume that the underlying connection always uses the 'pdo_mysql' driver,
            // as specified in $connectionParams passed to DriverManager::getConnection above
            if (!$nativeConnection instanceof \PDO) {
                throw MigrationException::databaseConnectionAttributesWrong();
            }

            $successfullySet = $nativeConnection->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
            if (!$successfullySet) {
                throw MigrationException::databaseConnectionAttributesWrong();
            }
        } catch (ConnectionException $exception) {
            // $connection->getNativeConnection() tries to connect to the DB
            // we want to ignore connection errors at this point
        }
    }
}

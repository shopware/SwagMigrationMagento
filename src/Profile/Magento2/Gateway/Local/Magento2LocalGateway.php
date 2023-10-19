<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Gateway\Local;

use Doctrine\DBAL\Driver\ResultStatement;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactoryInterface;
use Swag\MigrationMagento\Profile\Magento\Gateway\MagentoGatewayInterface;
use Swag\MigrationMagento\Profile\Magento\Gateway\TableReaderInterface;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\Reader\EnvironmentReaderInterface;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\RequestStatusStruct;

abstract class Magento2LocalGateway implements MagentoGatewayInterface
{
    public const GATEWAY_NAME = 'local';

    private ReaderRegistryInterface $readerRegistry;

    private ConnectionFactoryInterface $connectionFactory;

    private EnvironmentReaderInterface $localEnvironmentReader;

    /**
     * @var EntityRepository<EntityCollection<CurrencyEntity>>
     */
    private EntityRepository $currencyRepository;

    private TableReaderInterface $localTableReader;

    /**
     * @param EntityRepository<EntityCollection<CurrencyEntity>> $currencyRepository
     */
    public function __construct(
        ReaderRegistryInterface $readerRegistry,
        EnvironmentReaderInterface $localEnvironmentReader,
        TableReaderInterface $localTableReader,
        ConnectionFactoryInterface $connectionFactory,
        EntityRepository $currencyRepository
    ) {
        $this->readerRegistry = $readerRegistry;
        $this->localEnvironmentReader = $localEnvironmentReader;
        $this->localTableReader = $localTableReader;
        $this->connectionFactory = $connectionFactory;
        $this->currencyRepository = $currencyRepository;
    }

    public function getName(): string
    {
        return self::GATEWAY_NAME;
    }

    public function getSnippetName(): string
    {
        return 'swag-migration.wizard.pages.connectionCreate.gateways.magentoLocal';
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $reader = $this->readerRegistry->getReader($migrationContext);

        return $reader->read($migrationContext);
    }

    public function readEnvironmentInformation(MigrationContextInterface $migrationContext, Context $context): EnvironmentInformation
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        $profile = $migrationContext->getProfile();
        if ($connection === null) {
            return new EnvironmentInformation(
                $profile->getSourceSystemName(),
                $profile->getVersion(),
                '-',
                [],
                [],
                new RequestStatusStruct('SWAG_MIGRATION__DATABASE_CONNECTION_ERROR', 'No database connection')
            );
        }

        try {
            $connection->connect();
        } catch (\Exception $e) {
            return new EnvironmentInformation(
                $profile->getSourceSystemName(),
                $profile->getVersion(),
                '-',
                [],
                [],
                new RequestStatusStruct('SWAG_MIGRATION__DATABASE_CONNECTION_ERROR', 'No database connection')
            );
        }
        $connection->close();

        $environmentData = $this->localEnvironmentReader->read($migrationContext);

        if (!$environmentData['isMagento2']) {
            return new EnvironmentInformation(
                $profile->getSourceSystemName(),
                $profile->getVersion(),
                '-',
                [],
                [],
                new RequestStatusStruct('SWAG_MIGRATION_MAGENTO__NO_MAGENTO2_DATABASE', 'No Magento 2 database')
            );
        }

        /** @var CurrencyEntity $targetSystemCurrency */
        $targetSystemCurrency = $this->currencyRepository->search(new Criteria([Defaults::CURRENCY]), $context)->get(Defaults::CURRENCY);
        if (!isset($environmentData['defaultCurrency'])) {
            $environmentData['defaultCurrency'] = $targetSystemCurrency->getIsoCode();
        }

        $totals = $this->readTotals($migrationContext, $context);

        return new EnvironmentInformation(
            $profile->getSourceSystemName(),
            $profile->getVersion(),
            $environmentData['host'],
            $totals,
            $environmentData['additionalData'],
            new RequestStatusStruct(),
            false,
            [],
            $targetSystemCurrency->getIsoCode(),
            $environmentData['defaultCurrency']
        );
    }

    public function readTotals(MigrationContextInterface $migrationContext, Context $context): array
    {
        $readers = $this->readerRegistry->getReaderForTotal($migrationContext);

        $totals = [];
        foreach ($readers as $reader) {
            $total = $reader->readTotal($migrationContext);
            if ($total === null) {
                continue;
            }

            $totals[$total->getEntityName()] = $total;
        }

        return $totals;
    }

    public function readTable(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array
    {
        $tablePrefix = $this->getTablePrefixFromCredentials($migrationContext);
        $tableName = $tablePrefix . $tableName;

        return $this->localTableReader->read($migrationContext, $tableName, $filter);
    }

    public function readPayments(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        if ($connection === null) {
            return [];
        }

        $tablePrefix = $this->getTablePrefixFromCredentials($migrationContext);

        $sql = <<<SQL
SELECT DISTINCT payment.*
FROM (
      SELECT
             REPLACE(REPLACE(config.path, '/title', ''), 'payment/', '') AS identifier,
             REPLACE(REPLACE(config.path, '/title', ''), 'payment/', '') AS payment_id,
             config.value
      FROM {$tablePrefix}core_config_data config
      WHERE path LIKE 'payment/%/title' AND scope = 'default'
      ) AS payment,
      (
      SELECT
             REPLACE(REPLACE(config.path, '/active', ''), 'payment/', '') AS payment_id
      FROM {$tablePrefix}core_config_data config
      WHERE path LIKE 'payment/%/active' AND scope = 'default' AND value = true
      ) AS payment_active
WHERE payment.payment_id = payment_active.payment_id;
SQL;

        $rows = $connection->executeQuery($sql)->fetchAllAssociative();
        $configPayments = FetchModeHelper::groupUnique($rows);

        $sql = <<<SQL
SELECT DISTINCT salesOrder.method AS identifier, salesOrder.method AS payment_id, salesOrder.method AS value
FROM {$tablePrefix}sales_order_payment AS salesOrder;
SQL;

        $rows = $connection->executeQuery($sql)->fetchAllAssociative();
        $orderPayments = FetchModeHelper::groupUnique($rows);

        $result = [];
        foreach ($configPayments as $key => $payment) {
            if (isset($orderPayments[$key])) {
                unset($orderPayments[$key]);
            }

            if (isset($result[$key])) {
                continue;
            }

            $result[] = $payment;
        }

        foreach ($orderPayments as $key => $payment) {
            if (isset($result[$key])) {
                continue;
            }

            $result[] = $payment;
        }

        return $result;
    }

    public function readCustomerGroups(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        if ($connection === null) {
            return [];
        }

        $tablePrefix = $this->getTablePrefixFromCredentials($migrationContext);

        $sql = <<<SQL
SELECT *
FROM {$tablePrefix}customer_group;
SQL;

        return $connection->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * @psalm-suppress DocblockTypeContradiction
     */
    public function readCarriers(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        if ($connection === null) {
            return [];
        }

        $tablePrefix = $this->getTablePrefixFromCredentials($migrationContext);

        $sql = <<<SQL
SELECT carrier.*
FROM (
          SELECT
            REPLACE(REPLACE(config.path, '/title', ''), 'carriers/', '') AS carrier_id,
            config.*
          FROM {$tablePrefix}core_config_data config
          WHERE path LIKE 'carriers/%/title' AND scope = 'default'
      ) AS carrier,
      (
          SELECT
            REPLACE(REPLACE(config.path, '/active', ''), 'carriers/', '') AS carrier_id
          FROM {$tablePrefix}core_config_data config
          WHERE path LIKE 'carriers/%/active' AND scope = 'default' AND value = true
      ) AS carrier_active
WHERE carrier.carrier_id = carrier_active.carrier_id;
SQL;

        return $connection->executeQuery($sql)->fetchAllAssociative();
    }

    public function readGenders(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);

        if ($connection === null) {
            return [];
        }

        $tablePrefix = $this->getTablePrefixFromCredentials($migrationContext);
        $query = $connection->createQueryBuilder();

        $query->select('attrOption.option_id');
        $query->addSelect('attrOptionValue.value');
        $query->from($tablePrefix . 'eav_attribute', 'attr');

        $query->innerJoin('attr', $tablePrefix . 'eav_attribute_option', 'attrOption', 'attrOption.attribute_id = attr.attribute_id');
        $query->innerJoin('attrOption', $tablePrefix . 'eav_attribute_option_value', 'attrOptionValue', 'attrOption.option_id = attrOptionValue.option_id');

        $query->where('attr.attribute_code = \'gender\' AND is_user_defined = false AND store_id = 0');

        return $query->executeQuery()->fetchAllAssociative();
    }

    public function readStores(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        if ($connection === null) {
            return [];
        }

        $tablePrefix = $this->getTablePrefixFromCredentials($migrationContext);
        $query = $connection->createQueryBuilder();

        $query->select('store_id');
        $query->addSelect('name');
        $query->from($tablePrefix . 'store');
        $query->where('store_id != 0');

        return $query->executeQuery()->fetchAllAssociative();
    }

    protected function getTablePrefixFromCredentials(MigrationContextInterface $migrationContext): string
    {
        $tablePrefix = '';
        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return $tablePrefix;
        }

        $credentials = $connection->getCredentialFields();
        if (isset($credentials['tablePrefix'])) {
            $tablePrefix = $credentials['tablePrefix'];
        }

        return $tablePrefix;
    }
}

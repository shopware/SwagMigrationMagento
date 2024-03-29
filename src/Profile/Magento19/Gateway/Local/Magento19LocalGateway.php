<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento19\Gateway\Local;

use Doctrine\DBAL\Driver\ResultStatement;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactoryInterface;
use Swag\MigrationMagento\Profile\Magento\Gateway\MagentoGatewayInterface;
use Swag\MigrationMagento\Profile\Magento\Gateway\TableReaderInterface;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\Reader\EnvironmentReaderInterface;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\RequestStatusStruct;

#[Package('services-settings')]
class Magento19LocalGateway implements MagentoGatewayInterface
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

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
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
                new RequestStatusStruct('500', 'No database connection')
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
                new RequestStatusStruct('500', 'No database connection')
            );
        }
        $connection->close();
        $environmentData = $this->localEnvironmentReader->read($migrationContext);

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
SELECT
    REPLACE(REPLACE(config.path, '/title', ''), 'payment/', '') AS payment_id,
    config.*
FROM {$tablePrefix}core_config_data config
WHERE path LIKE 'payment/%/title'
AND scope = 'default' AND (value = true OR REPLACE(REPLACE(config.path, '/title', ''), 'payment/', '') IN (SELECT DISTINCT(method) FROM {$tablePrefix}sales_flat_order_payment));
SQL;

        return $connection->executeQuery($sql)->fetchAllAssociative();
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

    public function readCarriers(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        if ($connection === null) {
            return [];
        }

        $tablePrefix = $this->getTablePrefixFromCredentials($migrationContext);
        $sql = <<<SQL
SELECT
    DISTINCT carrier.*
FROM
      (
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
          WHERE path LIKE 'carriers/%/active' AND value = 1
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
        $query->from($tablePrefix . 'core_store');
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

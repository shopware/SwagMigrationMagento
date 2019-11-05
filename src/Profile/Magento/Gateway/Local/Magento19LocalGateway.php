<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactoryInterface;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\TableCountReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\MagentoGatewayInterface;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Profile\ReaderInterface;
use SwagMigrationAssistant\Migration\RequestStatusStruct;
use SwagMigrationAssistant\Profile\Shopware\Gateway\TableReaderInterface;

class Magento19LocalGateway implements MagentoGatewayInterface
{
    public const GATEWAY_NAME = 'local';

    /**
     * @var ReaderRegistry
     */
    private $readerRegistry;

    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    /**
     * @var ReaderInterface
     */
    private $localEnvironmentReader;

    /**
     * @var TableCountReader
     */
    private $localTableCountReader;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var TableReaderInterface
     */
    private $localTableReader;

    public function __construct(
        ReaderRegistry $readerRegistry,
        ReaderInterface $localEnvironmentReader,
        TableReaderInterface $localTableReader,
        TableCountReader $localTableCountReader,
        ConnectionFactoryInterface $connectionFactory,
        EntityRepositoryInterface $currencyRepository
    ) {
        $this->readerRegistry = $readerRegistry;
        $this->localEnvironmentReader = $localEnvironmentReader;
        $this->localTableReader = $localTableReader;
        $this->localTableCountReader = $localTableCountReader;
        $this->connectionFactory = $connectionFactory;
        $this->currencyRepository = $currencyRepository;
    }

    public function getName(): string
    {
        return self::GATEWAY_NAME;
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

        try {
            $connection->connect();
        } catch (\Exception $e) {
            $error = new \Exception();

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
        return $this->localTableCountReader->readTotals($migrationContext, $context);
    }

    public function readTable(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array
    {
        return $this->localTableReader->read($migrationContext, $tableName, $filter);
    }

    public function readPayments(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        $sql = "
        SELECT payment.* FROM
                      (
                      SELECT
                             REPLACE(REPLACE(config.path, '/title', ''), 'payment/', '') AS payment_id,
                             config.*
                      FROM core_config_data config
                      WHERE path LIKE 'payment/%/title'
                        AND scope = 'default'
                      ) AS payment
        WHERE payment.payment_id IN (SELECT DISTINCT(method) FROM sales_flat_order_payment)
        ";

        return $connection->executeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function readCarriers(MigrationContextInterface $migrationContext): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        $sql = "
        SELECT carrier.* FROM
              (
                  SELECT
                    REPLACE(REPLACE(config.path, '/title', ''), 'carriers/', '') AS carrier_id,
                    config.*
                  FROM core_config_data config
                  WHERE path LIKE 'carriers/%/title'
                        AND scope = 'default'
              ) AS carrier,
              
              (
                  SELECT
                    REPLACE(REPLACE(config.path, '/active', ''), 'carriers/', '') AS carrier_id
                  FROM core_config_data config
                  WHERE path LIKE 'carriers/%/active'
                        AND scope = 'default'
                        AND value = true
              ) AS carrier_active
        WHERE carrier.carrier_id = carrier_active.carrier_id
        ";

        return $connection->executeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}

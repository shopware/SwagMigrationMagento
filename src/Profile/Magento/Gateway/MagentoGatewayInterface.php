<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway;

use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

interface MagentoGatewayInterface extends GatewayInterface
{
    public function readTable(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array;

    public function readPayments(MigrationContextInterface $migrationContext): array;
}

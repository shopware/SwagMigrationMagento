<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway;

use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

interface MagentoGatewayInterface extends GatewayInterface
{
    public function readTable(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array;

    public function readPayments(MigrationContextInterface $migrationContext): array;

    public function readGenders(MigrationContextInterface $migrationContext): array;

    public function readCustomerGroups(MigrationContextInterface $migrationContext): array;
}

<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Connection;

use Doctrine\DBAL\Connection;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

interface ConnectionFactoryInterface
{
    public function createDatabaseConnection(MigrationContextInterface $migrationContext): Connection;
}

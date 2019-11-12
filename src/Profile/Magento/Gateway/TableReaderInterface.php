<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway;

use SwagMigrationAssistant\Migration\MigrationContextInterface;

interface TableReaderInterface
{
    /**
     * Reads data from source table via the given gateway based on implementation
     */
    public function read(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array;
}

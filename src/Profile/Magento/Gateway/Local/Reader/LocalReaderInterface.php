<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Profile\ReaderInterface;

interface LocalReaderInterface extends ReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool;
}

<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet;

use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class MagentoDataSet extends DataSet
{
    protected function getTablePrefixFromCredentials(?MigrationContextInterface $migrationContext = null): string
    {
        $tablePrefix = '';

        if ($migrationContext === null) {
            return $tablePrefix;
        }
        $credentials = $migrationContext->getConnection()->getCredentialFields();
        if (isset($credentials['tablePrefix'])) {
            $tablePrefix = $credentials['tablePrefix'];
        }

        return $tablePrefix;
    }
}

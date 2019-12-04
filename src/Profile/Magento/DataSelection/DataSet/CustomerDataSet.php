<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingInformationStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingQueryStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CustomerDataSet extends DataSet
{
    public static function getEntity(): string
    {
        return DefaultEntities::CUSTOMER;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }
}

<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingInformationStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingQueryStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CustomerGroupDataSet extends MagentoDataSet
{
    public static function getEntity(): string
    {
        return DefaultEntities::CUSTOMER_GROUP;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }

    public function getCountingInformation(?MigrationContextInterface $migrationContext = null): ?CountingInformationStruct
    {
        $countingInformation = new CountingInformationStruct(self::getEntity());
        $countingInformation->addQueryStruct(new CountingQueryStruct($this->getTablePrefixFromCredentials($migrationContext) . 'customer_group'));

        return $countingInformation;
    }
}

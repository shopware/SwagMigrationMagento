<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingService;
use Swag\MigrationMagento\Migration\Writer\CountryWriter;
use Swag\MigrationMagento\Migration\Writer\CurrencyWriter;
use Swag\MigrationMagento\Migration\Writer\ManufacturerWriter;
use Swag\MigrationMagento\Migration\Writer\NotAssociatedWriter;
use Swag\MigrationMagento\Migration\Writer\ProductChildMultiSelectPropertyRelationWriter;
use Swag\MigrationMagento\Migration\Writer\ProductChildMultiSelectTextPropertyRelationWriter;
use Swag\MigrationMagento\Migration\Writer\ProductChildPropertyRelationWriter;
use Swag\MigrationMagento\Migration\Writer\ProductMultiSelectPropertyRelationWriter;
use Swag\MigrationMagento\Migration\Writer\ProductMultiSelectTextPropertyRelationWriter;
use Swag\MigrationMagento\Migration\Writer\ProductOptionRelationWriter;
use Swag\MigrationMagento\Migration\Writer\PropertyGroupWriter;
use Swag\MigrationMagento\Profile\Magento\Converter\CategoryConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CountryConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CustomerConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\LanguageConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\MagentoConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\ManufacturerConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\MediaConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\NotAssociatedMediaConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\OrderConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\ProductConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\ProductCustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\ProductMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\ProductOptionRelationConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\ProductPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\PropertyGroupConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\SeoUrlConverter;
use Swag\MigrationMagento\Profile\Magento\DataSelection\BasicSettingsDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\CustomerAndOrderDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CountryDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CrossSellingDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CurrencyDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CustomerDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CustomerGroupDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\LanguageDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ManufacturerDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\NewsletterRecipientDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\NotAssociatedMediaDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\OrderDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductChildMultiSelectPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductChildMultiSelectTextPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductChildPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductCustomFieldDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductMultiSelectPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductMultiSelectTextPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductOptionRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductReviewDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\PropertyGroupDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\SalesChannelDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\SeoUrlDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\MediaDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\NewsletterRecipientDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\ProductDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\ProductReviewDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\SeoUrlDataSelection;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactory;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\AbstractReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CategoryReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CountryReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CrossSellingReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CurrencyReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CustomerReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\EnvironmentReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\LanguageReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\LocalTableReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ManufacturerReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\MediaReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\NewsletterRecipientReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\NotAssociatedMediaReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\OrderReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductChildMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductChildMultiSelectTextPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductChildPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductCustomFieldReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductMultiSelectTextPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\PropertyGroupReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\SalesChannelReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\SeoUrlReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\OrderDeliveryStateReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\OrderStateReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\SalutationReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\TaxReader;
use Swag\MigrationMagento\Profile\Magento19\PasswordEncoder\MagentoEncoder;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Argon2Id13Encoder;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Md5Encoder;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Sha256Encoder;
use SwagMigrationAssistant\Migration\Converter\Converter;
use SwagMigrationAssistant\Migration\Logging\LoggingService;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryStateLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CurrencyLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\DefaultCmsPageLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LocaleLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LowestRootCategoryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\MediaDefaultFolderLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\StateMachineStateLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\TaxLookup;
use SwagMigrationAssistant\Migration\Mapping\MappingService;
use SwagMigrationAssistant\Migration\Media\MediaFileService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConnectionFactory::class);

    $services->set(EnvironmentReader::class)
        ->args([service(ConnectionFactory::class)]);

    $services->set(LocalTableReader::class)
        ->args([service(ConnectionFactory::class)]);

    $services->set(MagentoMappingService::class)
        ->parent(MappingService::class)
        ->args([service('tax.repository')]);

    $services->set(CustomerAndOrderDataSelection::class)
        ->tag('shopware.migration.data_selection');

    $services->set(BasicSettingsDataSelection::class)
        ->tag('shopware.migration.data_selection');

    $services->set(MediaDataSelection::class)
        ->tag('shopware.migration.data_selection');

    $services->set(ProductDataSelection::class)
        ->tag('shopware.migration.data_selection');

    $services->set(ProductReviewDataSelection::class)
        ->tag('shopware.migration.data_selection');

    $services->set(SeoUrlDataSelection::class)
        ->tag('shopware.migration.data_selection');

    $services->set(NewsletterRecipientDataSelection::class)
        ->tag('shopware.migration.data_selection');

    $services->set(CustomerDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(SalesChannelDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(CategoryDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(OrderDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(CountryDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(CurrencyDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(MediaDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(NotAssociatedMediaDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ManufacturerDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(CustomerGroupDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(PropertyGroupDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(LanguageDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductCustomFieldDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductReviewDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(SeoUrlDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(NewsletterRecipientDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(CrossSellingDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductPropertyRelationDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductChildPropertyRelationDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductMultiSelectPropertyRelationDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductMultiSelectTextPropertyRelationDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductChildMultiSelectPropertyRelationDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductChildMultiSelectTextPropertyRelationDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(ProductOptionRelationDataSet::class)
        ->tag('shopware.migration.data_set');

    $services->set(\Swag\MigrationMagento\Profile\Magento\Premapping\CustomerGroupReader::class)
        ->abstract();

    $services->set(SalutationReader::class)
        ->abstract();

    $services->set(AdminStoreReader::class)
        ->abstract();

    $services->set(PaymentMethodReader::class)
        ->abstract();

    $services->set(ShippingMethodReader::class)
        ->abstract();

    $services->set(OrderStateReader::class)
        ->abstract();

    $services->set(TaxReader::class)
        ->abstract();

    $services->set(NewsletterRecipientStatusReader::class)
        ->abstract();

    $services->set(OrderDeliveryStateReader::class)
        ->abstract();

    $services->set(AbstractReader::class)
        ->abstract();

    $services->set(CategoryReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(CountryReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(CurrencyReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(CustomerGroupReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(CustomerReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(LanguageReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ManufacturerReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(MediaReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(NewsletterRecipientReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(NotAssociatedMediaReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(OrderReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductCustomFieldReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductReviewReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(PropertyGroupReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(SalesChannelReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(SeoUrlReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(CrossSellingReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductPropertyRelationReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductChildPropertyRelationReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductMultiSelectPropertyRelationReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductMultiSelectTextPropertyRelationReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductChildMultiSelectPropertyRelationReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductChildMultiSelectTextPropertyRelationReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(ProductOptionRelationReader::class)
        ->abstract()
        ->parent(AbstractReader::class);

    $services->set(MagentoConverter::class)
        ->abstract()
        ->parent(Converter::class)
        ->args([
            service(MagentoMappingService::class),
            '1' => service(LoggingService::class),
        ]);

    $services->set(CustomerConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(NumberRangeValueGeneratorInterface::class),
            service(CountryLookup::class),
            service(CountryStateLookup::class),
        ]);

    $services->set(SalesChannelConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(CurrencyLookup::class),
            service(LanguageLookup::class),
            service(CountryLookup::class),
        ]);

    $services->set(CategoryConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(MediaFileService::class),
            service(MediaDefaultFolderLookup::class),
            service(LowestRootCategoryLookup::class),
            service(DefaultCmsPageLookup::class),
            service(LanguageLookup::class),
        ]);

    $services->set(OrderConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(TaxCalculator::class),
            service(NumberRangeValueGeneratorInterface::class),
            service(CountryLookup::class),
            service(CurrencyLookup::class),
            service(CountryStateLookup::class),
            service(StateMachineStateLookup::class),
            service(LanguageLookup::class),
        ]);

    $services->set(CountryConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(LanguageLookup::class),
            service(CountryLookup::class),
        ]);

    $services->set(CurrencyConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(CurrencyLookup::class),
            service(LanguageLookup::class),
        ]);

    $services->set(MediaConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(MediaFileService::class),
            service(MediaDefaultFolderLookup::class),
        ]);

    $services->set(NotAssociatedMediaConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([service(MediaFileService::class)]);

    $services->set(ManufacturerConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([service(LanguageLookup::class)]);

    $services->set(CustomerGroupConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(ProductConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(MediaFileService::class),
            service(MediaDefaultFolderLookup::class),
            service(LanguageLookup::class),
            service(TaxLookup::class),
        ]);

    $services->set(PropertyGroupConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([service(LanguageLookup::class)]);

    $services->set(LanguageConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class)
        ->args([
            service(LanguageLookup::class),
            service(LocaleLookup::class),
        ]);

    $services->set(CustomFieldConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(ProductCustomFieldConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(ProductReviewConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(SeoUrlConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(NewsletterRecipientConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(CrossSellingConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(ProductPropertyRelationConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(ProductMultiSelectPropertyRelationConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(ProductOptionRelationConverter::class)
        ->abstract()
        ->parent(MagentoConverter::class);

    $services->set(CountryWriter::class)
        ->args([
            service(EntityWriter::class),
            service(CountryDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(CurrencyWriter::class)
        ->args([
            service(EntityWriter::class),
            service(CurrencyDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(ManufacturerWriter::class)
        ->args([
            service(EntityWriter::class),
            service(ProductManufacturerDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(NotAssociatedWriter::class)
        ->args([
            service(EntityWriter::class),
            service(MediaDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(PropertyGroupWriter::class)
        ->args([
            service(EntityWriter::class),
            service(PropertyGroupDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(ProductChildPropertyRelationWriter::class)
        ->args([
            service(EntityWriter::class),
            service(ProductDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(ProductMultiSelectPropertyRelationWriter::class)
        ->args([
            service(EntityWriter::class),
            service(ProductDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(ProductMultiSelectTextPropertyRelationWriter::class)
        ->args([
            service(EntityWriter::class),
            service(ProductDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(ProductChildMultiSelectPropertyRelationWriter::class)
        ->args([
            service(EntityWriter::class),
            service(ProductDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(ProductChildMultiSelectTextPropertyRelationWriter::class)
        ->args([
            service(EntityWriter::class),
            service(ProductDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(ProductOptionRelationWriter::class)
        ->args([
            service(EntityWriter::class),
            service(ProductDefinition::class),
        ])
        ->tag('shopware.migration.writer');

    $services->set(MagentoEncoder::class)
        ->tag('shopware.legacy_encoder');

    $services->set(Magento2Md5Encoder::class)
        ->tag('shopware.legacy_encoder');

    $services->set(Magento2Sha256Encoder::class)
        ->tag('shopware.legacy_encoder');

    $services->set(Magento2Argon2Id13Encoder::class)
        ->tag('shopware.legacy_encoder');
};

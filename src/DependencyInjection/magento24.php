<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\File\FileSaver;
use Swag\MigrationMagento\Profile\Magento\Converter\CategoryConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CountryConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CustomerConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento\Converter\LanguageConverter;
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
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactory;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CategoryReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CountryReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CrossSellingReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CurrencyReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CustomerReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\EnvironmentReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\LanguageReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\LocalTableReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ManufacturerReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\MediaReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\NewsletterRecipientReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\NotAssociatedMediaReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\OrderReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductCustomFieldReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\PropertyGroupReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\SalesChannelReader;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\SeoUrlReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\OrderDeliveryStateReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\TaxReader;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24CategoryConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24CountryConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24CustomerConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24LanguageConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ManufacturerConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24MediaConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24NotAssociatedMediaConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24OrderConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductChildMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductChildPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductCustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductOptionRelationConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24PropertyGroupConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24SeoUrlConverter;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Magento24LocalGateway;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24CategoryReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24CrossSellingReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24CustomerReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ManufacturerReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24MediaReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24NewsletterRecipientReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24NotAssociatedMediaReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24OrderReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductChildMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductChildPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductCustomFieldReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24PropertyGroupReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24SalesChannelReader;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24SeoUrlReader;
use Swag\MigrationMagento\Profile\Magento24\Magento24Profile;
use Swag\MigrationMagento\Profile\Magento24\Media\Magento24LocalMediaProcessor;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24CountryReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24CurrencyReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24LanguageReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24OrderDeliveryStateReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24OrderStateReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24SalutationReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24TaxReader;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\Logging\LoggingService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Magento24Profile::class)
        ->tag('shopware.migration.profile');

    $services->set(Magento24LocalGateway::class)
        ->args([
            service(ReaderRegistry::class),
            service(EnvironmentReader::class),
            service(LocalTableReader::class),
            service(ConnectionFactory::class),
            service('currency.repository'),
        ])
        ->tag('shopware.migration.gateway');

    $services->set(\Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24LanguageReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24CustomerGroupReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24CategoryReader::class)
        ->parent(CategoryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24CountryReader::class)
        ->parent(CountryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Reader\Magento24CurrencyReader::class)
        ->parent(CurrencyReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24SalesChannelReader::class)
        ->parent(SalesChannelReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24CustomerReader::class)
        ->parent(CustomerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24MediaReader::class)
        ->parent(MediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24NotAssociatedMediaReader::class)
        ->parent(NotAssociatedMediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ManufacturerReader::class)
        ->parent(ManufacturerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24NewsletterRecipientReader::class)
        ->parent(NewsletterRecipientReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24OrderReader::class)
        ->parent(OrderReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductReader::class)
        ->parent(ProductReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductCustomFieldReader::class)
        ->parent(ProductCustomFieldReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductReviewReader::class)
        ->parent(ProductReviewReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24PropertyGroupReader::class)
        ->parent(PropertyGroupReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24SeoUrlReader::class)
        ->parent(SeoUrlReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24CrossSellingReader::class)
        ->parent(CrossSellingReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductChildPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductChildMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24ProductOptionRelationReader::class)
        ->parent(ProductOptionRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento24LanguageConverter::class)
        ->parent(LanguageConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24CategoryConverter::class)
        ->parent(CategoryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ManufacturerConverter::class)
        ->parent(ManufacturerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24CountryConverter::class)
        ->parent(CountryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24CurrencyConverter::class)
        ->parent(CurrencyConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24CustomerGroupConverter::class)
        ->parent(CustomerGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24SalesChannelConverter::class)
        ->parent(SalesChannelConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24CustomerConverter::class)
        ->parent(CustomerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductCustomFieldConverter::class)
        ->parent(ProductCustomFieldConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24OrderConverter::class)
        ->parent(OrderConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24MediaConverter::class)
        ->parent(MediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24NewsletterRecipientConverter::class)
        ->parent(NewsletterRecipientConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24NotAssociatedMediaConverter::class)
        ->parent(NotAssociatedMediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24PropertyGroupConverter::class)
        ->parent(PropertyGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductConverter::class)
        ->parent(ProductConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductReviewConverter::class)
        ->parent(ProductReviewConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24SeoUrlConverter::class)
        ->parent(SeoUrlConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24CrossSellingConverter::class)
        ->parent(CrossSellingConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductOptionRelationConverter::class)
        ->parent(ProductOptionRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductChildPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24ProductChildMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento24CustomerGroupReader::class)
        ->parent(CustomerGroupReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24PaymentMethodReader::class)
        ->parent(PaymentMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('payment_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24ShippingMethodReader::class)
        ->parent(ShippingMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('shipping_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24CountryReader::class)
        ->args([service('country.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24CurrencyReader::class)
        ->args([service('currency.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24LanguageReader::class)
        ->args([service('language.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24SalutationReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('salutation.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24AdminStoreReader::class)
        ->parent(AdminStoreReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24OrderStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service(GatewayRegistry::class),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24NewsletterRecipientStatusReader::class)
        ->parent(NewsletterRecipientStatusReader::class)
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24TaxReader::class)
        ->parent(TaxReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('tax.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24OrderDeliveryStateReader::class)
        ->parent(OrderDeliveryStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento24LocalMediaProcessor::class)
        ->args([
            service('swag_migration_media_file.repository'),
            service('media.repository'),
            service(FileSaver::class),
            service(LoggingService::class),
            service(Connection::class),
        ])
        ->tag('shopware.migration.media_file_processor');
};

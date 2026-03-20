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
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\TaxReader;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21CategoryConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21CountryConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21CustomerConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21LanguageConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ManufacturerConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21MediaConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21NotAssociatedMediaConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21OrderConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductChildMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductChildPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductCustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductOptionRelationConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21PropertyGroupConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21SeoUrlConverter;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Magento21LocalGateway;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21CategoryReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21CrossSellingReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21CustomerReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ManufacturerReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21MediaReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21NewsletterRecipientReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21NotAssociatedMediaReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21OrderReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductChildMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductChildPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductCustomFieldReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21PropertyGroupReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21SalesChannelReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21SeoUrlReader;
use Swag\MigrationMagento\Profile\Magento21\Magento21Profile;
use Swag\MigrationMagento\Profile\Magento21\Media\Magento21LocalMediaProcessor;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21CountryReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21CurrencyReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21LanguageReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21OrderStateReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21SalutationReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento21\Premapping\Magento21TaxReader;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\Logging\LoggingService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Magento21Profile::class)
        ->tag('shopware.migration.profile');

    $services->set(Magento21LocalGateway::class)
        ->args([
            service(ReaderRegistry::class),
            service(EnvironmentReader::class),
            service(LocalTableReader::class),
            service(ConnectionFactory::class),
            service('currency.repository'),
        ])
        ->tag('shopware.migration.gateway');

    $services->set(\Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21LanguageReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21CustomerGroupReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21CategoryReader::class)
        ->parent(CategoryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21CountryReader::class)
        ->parent(CountryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader\Magento21CurrencyReader::class)
        ->parent(CurrencyReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21SalesChannelReader::class)
        ->parent(SalesChannelReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21CustomerReader::class)
        ->parent(CustomerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21MediaReader::class)
        ->parent(MediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21NotAssociatedMediaReader::class)
        ->parent(NotAssociatedMediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ManufacturerReader::class)
        ->parent(ManufacturerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21NewsletterRecipientReader::class)
        ->parent(NewsletterRecipientReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21OrderReader::class)
        ->parent(OrderReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductReader::class)
        ->parent(ProductReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductCustomFieldReader::class)
        ->parent(ProductCustomFieldReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductReviewReader::class)
        ->parent(ProductReviewReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21PropertyGroupReader::class)
        ->parent(PropertyGroupReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21SeoUrlReader::class)
        ->parent(SeoUrlReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21CrossSellingReader::class)
        ->parent(CrossSellingReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductChildPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductChildMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21ProductOptionRelationReader::class)
        ->parent(ProductOptionRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento21LanguageConverter::class)
        ->parent(LanguageConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21CategoryConverter::class)
        ->parent(CategoryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ManufacturerConverter::class)
        ->parent(ManufacturerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21CountryConverter::class)
        ->parent(CountryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21CurrencyConverter::class)
        ->parent(CurrencyConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21CustomerGroupConverter::class)
        ->parent(CustomerGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21SalesChannelConverter::class)
        ->parent(SalesChannelConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21CustomerConverter::class)
        ->parent(CustomerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductCustomFieldConverter::class)
        ->parent(ProductCustomFieldConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21OrderConverter::class)
        ->parent(OrderConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21MediaConverter::class)
        ->parent(MediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21NewsletterRecipientConverter::class)
        ->parent(NewsletterRecipientConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21NotAssociatedMediaConverter::class)
        ->parent(NotAssociatedMediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21PropertyGroupConverter::class)
        ->parent(PropertyGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductConverter::class)
        ->parent(ProductConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductReviewConverter::class)
        ->parent(ProductReviewConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21SeoUrlConverter::class)
        ->parent(SeoUrlConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21CrossSellingConverter::class)
        ->parent(CrossSellingConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductChildPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductChildMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21ProductOptionRelationConverter::class)
        ->parent(ProductOptionRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento21CustomerGroupReader::class)
        ->parent(CustomerGroupReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21PaymentMethodReader::class)
        ->parent(PaymentMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('payment_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21ShippingMethodReader::class)
        ->parent(ShippingMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('shipping_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21CountryReader::class)
        ->args([service('country.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21CurrencyReader::class)
        ->args([service('currency.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21LanguageReader::class)
        ->args([service('language.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21SalutationReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('salutation.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21AdminStoreReader::class)
        ->parent(AdminStoreReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21OrderStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service(GatewayRegistry::class),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21NewsletterRecipientStatusReader::class)
        ->parent(NewsletterRecipientStatusReader::class)
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21TaxReader::class)
        ->parent(TaxReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('tax.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento21LocalMediaProcessor::class)
        ->args([
            service('swag_migration_media_file.repository'),
            service('media.repository'),
            service(FileSaver::class),
            service(LoggingService::class),
            service(Connection::class),
        ])
        ->tag('shopware.migration.media_file_processor');
};

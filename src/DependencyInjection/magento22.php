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
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22CategoryConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22CountryConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22CustomerConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22LanguageConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ManufacturerConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22MediaConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22NotAssociatedMediaConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22OrderConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductChildMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductChildPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductCustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductOptionRelationConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22PropertyGroupConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22SeoUrlConverter;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Magento22LocalGateway;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22CategoryReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22CrossSellingReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22CustomerReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ManufacturerReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22MediaReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22NewsletterRecipientReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22NotAssociatedMediaReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22OrderReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductChildMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductChildPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductCustomFieldReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22PropertyGroupReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22SalesChannelReader;
use Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22SeoUrlReader;
use Swag\MigrationMagento\Profile\Magento22\Magento22Profile;
use Swag\MigrationMagento\Profile\Magento22\Media\Magento22LocalMediaProcessor;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22CountryReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22CurrencyReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22LanguageReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22OrderStateReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22SalutationReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento22\Premapping\Magento22TaxReader;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\Logging\LoggingService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Magento22Profile::class)
        ->tag('shopware.migration.profile');

    $services->set(Magento22LocalGateway::class)
        ->args([
            service(ReaderRegistry::class),
            service(EnvironmentReader::class),
            service(LocalTableReader::class),
            service(ConnectionFactory::class),
            service('currency.repository'),
        ])
        ->tag('shopware.migration.gateway');

    $services->set(\Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22LanguageReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22CustomerGroupReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22CategoryReader::class)
        ->parent(CategoryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22CountryReader::class)
        ->parent(CountryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento22\Gateway\Local\Reader\Magento22CurrencyReader::class)
        ->parent(CurrencyReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22SalesChannelReader::class)
        ->parent(SalesChannelReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22CustomerReader::class)
        ->parent(CustomerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22MediaReader::class)
        ->parent(MediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22NotAssociatedMediaReader::class)
        ->parent(NotAssociatedMediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ManufacturerReader::class)
        ->parent(ManufacturerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22NewsletterRecipientReader::class)
        ->parent(NewsletterRecipientReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22OrderReader::class)
        ->parent(OrderReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductReader::class)
        ->parent(ProductReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductCustomFieldReader::class)
        ->parent(ProductCustomFieldReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductReviewReader::class)
        ->parent(ProductReviewReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22PropertyGroupReader::class)
        ->parent(PropertyGroupReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22SeoUrlReader::class)
        ->parent(SeoUrlReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22CrossSellingReader::class)
        ->parent(CrossSellingReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductChildPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductChildMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22ProductOptionRelationReader::class)
        ->parent(ProductOptionRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento22LanguageConverter::class)
        ->parent(LanguageConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22CategoryConverter::class)
        ->parent(CategoryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ManufacturerConverter::class)
        ->parent(ManufacturerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22CountryConverter::class)
        ->parent(CountryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22CurrencyConverter::class)
        ->parent(CurrencyConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22CustomerGroupConverter::class)
        ->parent(CustomerGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22SalesChannelConverter::class)
        ->parent(SalesChannelConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22CustomerConverter::class)
        ->parent(CustomerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductCustomFieldConverter::class)
        ->parent(ProductCustomFieldConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22OrderConverter::class)
        ->parent(OrderConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22MediaConverter::class)
        ->parent(MediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22NewsletterRecipientConverter::class)
        ->parent(NewsletterRecipientConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22NotAssociatedMediaConverter::class)
        ->parent(NotAssociatedMediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22PropertyGroupConverter::class)
        ->parent(PropertyGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductConverter::class)
        ->parent(ProductConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductReviewConverter::class)
        ->parent(ProductReviewConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22SeoUrlConverter::class)
        ->parent(SeoUrlConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22CrossSellingConverter::class)
        ->parent(CrossSellingConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductChildPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductChildMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22ProductOptionRelationConverter::class)
        ->parent(ProductOptionRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento22CustomerGroupReader::class)
        ->parent(CustomerGroupReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22PaymentMethodReader::class)
        ->parent(PaymentMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('payment_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22ShippingMethodReader::class)
        ->parent(ShippingMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('shipping_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22CountryReader::class)
        ->args([service('country.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22CurrencyReader::class)
        ->args([service('currency.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22LanguageReader::class)
        ->args([service('language.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22SalutationReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('salutation.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22AdminStoreReader::class)
        ->parent(AdminStoreReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22OrderStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service(GatewayRegistry::class),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22NewsletterRecipientStatusReader::class)
        ->parent(NewsletterRecipientStatusReader::class)
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22TaxReader::class)
        ->parent(TaxReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('tax.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento22LocalMediaProcessor::class)
        ->args([
            service('swag_migration_media_file.repository'),
            service('media.repository'),
            service(FileSaver::class),
            service(LoggingService::class),
            service(Connection::class),
        ])
        ->tag('shopware.migration.media_file_processor');
};

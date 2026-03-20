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
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20CategoryConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20CountryConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20CustomerConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20LanguageConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ManufacturerConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20MediaConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20NotAssociatedMediaConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20OrderConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductChildMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductChildPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductCustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductOptionRelationConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20PropertyGroupConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20SeoUrlConverter;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Magento20LocalGateway;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20CategoryReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20CrossSellingReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20CustomerReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ManufacturerReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20MediaReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20NewsletterRecipientReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20NotAssociatedMediaReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20OrderReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductChildMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductChildPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductCustomFieldReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20PropertyGroupReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20SalesChannelReader;
use Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20SeoUrlReader;
use Swag\MigrationMagento\Profile\Magento20\Magento20Profile;
use Swag\MigrationMagento\Profile\Magento20\Media\Magento20LocalMediaProcessor;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20CountryReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20CurrencyReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20LanguageReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20OrderDeliveryStateReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20OrderStateReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20SalutationReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento20\Premapping\Magento20TaxReader;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\Logging\LoggingService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Magento20Profile::class)
        ->tag('shopware.migration.profile');

    $services->set(Magento20LocalGateway::class)
        ->args([
            service(ReaderRegistry::class),
            service(EnvironmentReader::class),
            service(LocalTableReader::class),
            service(ConnectionFactory::class),
            service('currency.repository'),
        ])
        ->tag('shopware.migration.gateway');

    $services->set(\Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20LanguageReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20CustomerGroupReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20CategoryReader::class)
        ->parent(CategoryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20CountryReader::class)
        ->parent(CountryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento20\Gateway\Local\Reader\Magento20CurrencyReader::class)
        ->parent(CurrencyReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20SalesChannelReader::class)
        ->parent(SalesChannelReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20CustomerReader::class)
        ->parent(CustomerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20MediaReader::class)
        ->parent(MediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20NotAssociatedMediaReader::class)
        ->parent(NotAssociatedMediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ManufacturerReader::class)
        ->parent(ManufacturerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20NewsletterRecipientReader::class)
        ->parent(NewsletterRecipientReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20OrderReader::class)
        ->parent(OrderReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductReader::class)
        ->parent(ProductReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductCustomFieldReader::class)
        ->parent(ProductCustomFieldReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductReviewReader::class)
        ->parent(ProductReviewReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20PropertyGroupReader::class)
        ->parent(PropertyGroupReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20SeoUrlReader::class)
        ->parent(SeoUrlReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20CrossSellingReader::class)
        ->parent(CrossSellingReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductChildPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductChildMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20ProductOptionRelationReader::class)
        ->parent(ProductOptionRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento20LanguageConverter::class)
        ->parent(LanguageConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20CategoryConverter::class)
        ->parent(CategoryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ManufacturerConverter::class)
        ->parent(ManufacturerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20CountryConverter::class)
        ->parent(CountryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20CurrencyConverter::class)
        ->parent(CurrencyConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20CustomerGroupConverter::class)
        ->parent(CustomerGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20SalesChannelConverter::class)
        ->parent(SalesChannelConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20CustomerConverter::class)
        ->parent(CustomerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductCustomFieldConverter::class)
        ->parent(ProductCustomFieldConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20OrderConverter::class)
        ->parent(OrderConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20MediaConverter::class)
        ->parent(MediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20NewsletterRecipientConverter::class)
        ->parent(NewsletterRecipientConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20NotAssociatedMediaConverter::class)
        ->parent(NotAssociatedMediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20PropertyGroupConverter::class)
        ->parent(PropertyGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductConverter::class)
        ->parent(ProductConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductReviewConverter::class)
        ->parent(ProductReviewConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20SeoUrlConverter::class)
        ->parent(SeoUrlConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20CrossSellingConverter::class)
        ->parent(CrossSellingConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductChildPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductChildMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20ProductOptionRelationConverter::class)
        ->parent(ProductOptionRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento20CustomerGroupReader::class)
        ->parent(CustomerGroupReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20PaymentMethodReader::class)
        ->parent(PaymentMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('payment_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20ShippingMethodReader::class)
        ->parent(ShippingMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('shipping_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20CountryReader::class)
        ->args([service('country.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20CurrencyReader::class)
        ->args([service('currency.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20LanguageReader::class)
        ->args([service('language.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20SalutationReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('salutation.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20AdminStoreReader::class)
        ->parent(AdminStoreReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20OrderStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service(GatewayRegistry::class),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20NewsletterRecipientStatusReader::class)
        ->parent(NewsletterRecipientStatusReader::class)
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20OrderDeliveryStateReader::class)
        ->parent(OrderDeliveryStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20TaxReader::class)
        ->parent(TaxReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('tax.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento20LocalMediaProcessor::class)
        ->args([
            service('swag_migration_media_file.repository'),
            service('media.repository'),
            service(FileSaver::class),
            service(LoggingService::class),
            service(Connection::class),
        ])
        ->tag('shopware.migration.media_file_processor');
};

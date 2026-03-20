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
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CategoryConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CountryConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CustomerConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23LanguageConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ManufacturerConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23MediaConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23NotAssociatedMediaConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23OrderConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductChildMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductChildPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductCustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductMultiSelectPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductOptionRelationConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductPropertyRelationConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23PropertyGroupConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23SeoUrlConverter;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Magento23LocalGateway;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23CategoryReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23CrossSellingReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23CustomerReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ManufacturerReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23MediaReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23NewsletterRecipientReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23NotAssociatedMediaReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23OrderReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductChildMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductChildPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductCustomFieldReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductMultiSelectPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductPropertyRelationReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23PropertyGroupReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23SalesChannelReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23SeoUrlReader;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Profile\Magento23\Media\Magento23LocalMediaProcessor;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23CountryReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23CurrencyReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23LanguageReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23OrderDeliveryStateReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23OrderStateReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23SalutationReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23TaxReader;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\Logging\LoggingService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(Magento23Profile::class)
        ->tag('shopware.migration.profile');

    $services->set(Magento23LocalGateway::class)
        ->args([
            service(ReaderRegistry::class),
            service(EnvironmentReader::class),
            service(LocalTableReader::class),
            service(ConnectionFactory::class),
            service('currency.repository'),
        ])
        ->tag('shopware.migration.gateway');

    $services->set(\Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23LanguageReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23CustomerGroupReader::class)
        ->parent(LanguageReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23CategoryReader::class)
        ->parent(CategoryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23CountryReader::class)
        ->parent(CountryReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(\Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader\Magento23CurrencyReader::class)
        ->parent(CurrencyReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23SalesChannelReader::class)
        ->parent(SalesChannelReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23CustomerReader::class)
        ->parent(CustomerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23MediaReader::class)
        ->parent(MediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23NotAssociatedMediaReader::class)
        ->parent(NotAssociatedMediaReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ManufacturerReader::class)
        ->parent(ManufacturerReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23NewsletterRecipientReader::class)
        ->parent(NewsletterRecipientReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23OrderReader::class)
        ->parent(OrderReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductReader::class)
        ->parent(ProductReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductCustomFieldReader::class)
        ->parent(ProductCustomFieldReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductReviewReader::class)
        ->parent(ProductReviewReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23PropertyGroupReader::class)
        ->parent(PropertyGroupReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23SeoUrlReader::class)
        ->parent(SeoUrlReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23CrossSellingReader::class)
        ->parent(CrossSellingReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductChildPropertyRelationReader::class)
        ->parent(ProductPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductChildMultiSelectPropertyRelationReader::class)
        ->parent(ProductMultiSelectPropertyRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23ProductOptionRelationReader::class)
        ->parent(ProductOptionRelationReader::class)
        ->args([service(ConnectionFactory::class)])
        ->tag('shopware.migration.reader');

    $services->set(Magento23LanguageConverter::class)
        ->parent(LanguageConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23CategoryConverter::class)
        ->parent(CategoryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ManufacturerConverter::class)
        ->parent(ManufacturerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23CountryConverter::class)
        ->parent(CountryConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23CurrencyConverter::class)
        ->parent(CurrencyConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23CustomerGroupConverter::class)
        ->parent(CustomerGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23SalesChannelConverter::class)
        ->parent(SalesChannelConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23CustomerConverter::class)
        ->parent(CustomerConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductCustomFieldConverter::class)
        ->parent(ProductCustomFieldConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23OrderConverter::class)
        ->parent(OrderConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23MediaConverter::class)
        ->parent(MediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23NewsletterRecipientConverter::class)
        ->parent(NewsletterRecipientConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23NotAssociatedMediaConverter::class)
        ->parent(NotAssociatedMediaConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23PropertyGroupConverter::class)
        ->parent(PropertyGroupConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductConverter::class)
        ->parent(ProductConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductReviewConverter::class)
        ->parent(ProductReviewConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23SeoUrlConverter::class)
        ->parent(SeoUrlConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23CrossSellingConverter::class)
        ->parent(CrossSellingConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductOptionRelationConverter::class)
        ->parent(ProductOptionRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductChildPropertyRelationConverter::class)
        ->parent(ProductPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23ProductChildMultiSelectPropertyRelationConverter::class)
        ->parent(ProductMultiSelectPropertyRelationConverter::class)
        ->tag('shopware.migration.converter');

    $services->set(Magento23CustomerGroupReader::class)
        ->parent(CustomerGroupReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23PaymentMethodReader::class)
        ->parent(PaymentMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('payment_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23ShippingMethodReader::class)
        ->parent(ShippingMethodReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('shipping_method.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23CountryReader::class)
        ->args([service('country.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23CurrencyReader::class)
        ->args([service('currency.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23LanguageReader::class)
        ->args([service('language.repository')])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23SalutationReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('salutation.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23AdminStoreReader::class)
        ->parent(AdminStoreReader::class)
        ->args([service(GatewayRegistry::class)])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23OrderStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service(GatewayRegistry::class),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23NewsletterRecipientStatusReader::class)
        ->parent(NewsletterRecipientStatusReader::class)
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23TaxReader::class)
        ->parent(TaxReader::class)
        ->args([
            service(GatewayRegistry::class),
            service('tax.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23OrderDeliveryStateReader::class)
        ->parent(OrderDeliveryStateReader::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
        ])
        ->tag('shopware.migration.pre_mapping_reader');

    $services->set(Magento23LocalMediaProcessor::class)
        ->args([
            service('swag_migration_media_file.repository'),
            service('media.repository'),
            service(FileSaver::class),
            service(LoggingService::class),
            service(Connection::class),
        ])
        ->tag('shopware.migration.media_file_processor');
};

# SwagMigrationMagento

A migration profile for [Shopware 6](https://github.com/shopware/platform) [Migration Assistant](https://github.com/shopware/SwagMigrationAssistant)

> [!IMPORTANT]  
> this plugin needs the current master and is not compatible with the EA2 version of Shopware 6 or the Migration Assistant. Please use current master branches of Shopware Platform and Migration Assistant

# Features

This plugin adds magento 1.9.x and 2.x profiles to the Migration Assistant for Shopware 6. You can use this profile together with the
[SwagMigrationAssistant](https://github.com/shopware/SwagMigrationAssistant) to migrate the data of your Magento 1.9.x or 2.x shop to Shopware 6.

Currently you can migrate the following data:

- Languages
- Customer groups
- Categories
- Countries
- Currencies
- Shop structure (store views etc.)
- Customers
- Orders
- Media
- Manufacturer / supplier
- Property groups
- Products + variants
- Product reviews
- Seo urls

This plugin is still in development.

## Installation

1. Clone the repository into the `custom/plugins` directory of your Shopware 6 installation:

   ```sh
   git clone https://github.com/shopware/SwagMigrationMagento.git custom/plugins/SwagMigrationMagento
   ```

2. Run the following commands from the Shopware root directory:
   ```sh
   bin/console plugin:refresh
   bin/console plugin:install --activate SwagMigrationMagento
   ```

## Documentation

If you need further information. You can have a look at our [user documentation](https://docs.shopware.com/en/migration-en/Migrationprocess?category=migration-en) and our [developer documentation](https://docs.shopware.com/en/shopware-platform-dev-en/internals/plugins/shopware-migration-assistant).

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

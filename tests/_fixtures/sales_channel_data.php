<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    0 => [
        'group_id' => '1',
        'website_id' => '1',
        'name' => 'Madison Island',
        'root_category_id' => '2',
        'default_store_id' => '1',
        'storeViews' => [
            0 => [
                'store_id' => '1',
                'code' => 'default',
                'website_id' => '1',
                'group_id' => '1',
                'name' => 'English',
                'sort_order' => '0',
                'is_active' => '1',
            ],
            1 => [
                'store_id' => '2',
                'code' => 'french',
                'website_id' => '1',
                'group_id' => '1',
                'name' => 'French',
                'sort_order' => '0',
                'is_active' => '1',
            ],
            2 => [
                'store_id' => '3',
                'code' => 'german',
                'website_id' => '1',
                'group_id' => '1',
                'name' => 'German',
                'sort_order' => '0',
                'is_active' => '1',
            ],
        ],
        'currencies' => [
            0 => 'DKK',
            1 => 'EUR',
        ],
        'countries' => [
            0 => 'DE',
            1 => 'GB',
            2 => 'NL',
            3 => 'FR',
            4 => 'US',
        ],
        'locales' => [
            0 => 'de-DE',
            1 => 'en-GB',
            2 => 'fr-FR',
        ],
        'defaultCurrency' => 'EUR',
        'defaultCountry' => 'US',
        'defaultLocale' => 'de-DE',
        'carriers' => [
            0 => [
                'carrier_id' => 'dhlint',
                'config_id' => '732',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'carriers/dhlint/title',
                'value' => 'DHL',
            ],
            1 => [
                'carrier_id' => 'fedex',
                'config_id' => '667',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'carriers/fedex/title',
                'value' => 'Federal Express',
            ],
            2 => [
                'carrier_id' => 'freeshipping',
                'config_id' => '597',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'carriers/freeshipping/title',
                'value' => 'Free Shipping',
            ],
            3 => [
                'carrier_id' => 'ups',
                'config_id' => '615',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'carriers/ups/title',
                'value' => 'United Parcel Service',
            ],
            4 => [
                'carrier_id' => 'usps',
                'config_id' => '643',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'carriers/usps/title',
                'value' => 'United States Postal Service',
            ],
        ],
        'payments' => [
            0 => [
                'payment_id' => 'cashondelivery',
                'config_id' => '443',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'payment/cashondelivery/title',
                'value' => 'Cash On Delivery',
            ],
            1 => [
                'payment_id' => 'free',
                'config_id' => '450',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'payment/free/title',
                'value' => 'No Payment Information Required',
            ],
            2 => [
                'payment_id' => 'paypal_standard',
                'config_id' => '349',
                'scope' => 'default',
                'scope_id' => '0',
                'path' => 'payment/paypal_standard/title',
                'value' => 'PayPal Website Payments Standard',
            ],
        ],
    ],
];

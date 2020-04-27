<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

class CurrencyRegistry extends AbstractMappingRegistry
{
    /**
     * @var array
     */
    protected static $mapping = [
        'CHF' => [
            'symbol' => 'CHF',
            'name' => 'Swiss franc',
            'translations' => [
                'de-DE' => 'Schweizer Franken',
                'en-GB' => 'Swiss franc',
                'en-US' => 'Swiss franc',
            ],
        ],
        'DKK' => [
            'symbol' => 'kr',
            'name' => 'Danish krone',
            'translations' => [
                'de-DE' => 'Dänische Krone',
                'en-GB' => 'Danish krone',
                'en-US' => 'Danish krone',
            ],
        ],
        'EUR' => [
            'symbol' => '€',
            'name' => 'Euro',
            'translations' => [
                'de-DE' => 'Euro',
                'en-GB' => 'Euro',
                'en-US' => 'Euro',
            ],
        ],
        'GBP' => [
            'symbol' => '£',
            'name' => 'Pound sterling',
            'translations' => [
                'de-DE' => 'Pfund Sterling',
                'en-GB' => 'Pound sterling',
                'en-US' => 'Pound sterling',
            ],
        ],
        'JPY' => [
            'symbol' => '¥',
            'name' => 'Japanese yen',
            'translations' => [
                'de-DE' => 'Japanische Yen',
                'en-GB' => 'Japanese yen',
                'en-US' => 'Japanese yen',
            ],
        ],
        'NOK' => [
            'symbol' => 'kr',
            'name' => 'Norwegian krone',
            'translations' => [
                'de-DE' => 'Norwegische Krone',
                'en-GB' => 'Norwegian krone',
                'en-US' => 'Norwegian krone',
            ],
        ],
        'PLN' => [
            'symbol' => 'zł',
            'name' => 'Polish zloty',
            'translations' => [
                'de-DE' => 'Polnische Zloty',
                'en-GB' => 'Polish zloty',
                'en-US' => 'Polish zloty',
            ],
        ],
        'RUB' => [
            'symbol' => '₽',
            'name' => 'Russian ruble',
            'translations' => [
                'de-DE' => 'Russischer Rubel',
                'en-GB' => 'Russian ruble',
                'en-US' => 'Russian ruble',
            ],
        ],
        'SEK' => [
            'symbol' => 'kr',
            'name' => 'Swedish krona',
            'translations' => [
                'de-DE' => 'Schwedische Krone',
                'en-GB' => 'Swedish krona',
                'en-US' => 'Swedish krona',
            ],
        ],
        'USD' => [
            'symbol' => '$',
            'name' => 'US dollar',
            'translations' => [
                'de-DE' => 'US-Dollar',
                'en-GB' => 'US dollar',
                'en-US' => 'US dollar',
            ],
        ],
    ];
}

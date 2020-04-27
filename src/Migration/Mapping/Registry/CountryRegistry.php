<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

class CountryRegistry extends AbstractMappingRegistry
{
    /**
     * @var array
     */
    protected static $mapping = [
        'AT' => [
            'iso3' => 'AUT',
            'name' => 'Austria',
            'translations' => [
                'de-DE' => 'Österreich',
                'en-GB' => 'Austria',
                'en-US' => 'Austria',
            ],
        ],
        'BE' => [
            'iso3' => 'BEL',
            'name' => 'Belgium',
            'translations' => [
                'de-DE' => 'Belgien',
                'en-GB' => 'Belgium',
                'en-US' => 'Belgium',
            ],
        ],
        'CH' => [
            'iso3' => 'CHE',
            'name' => 'Switzerland',
            'translations' => [
                'de-DE' => 'Schweiz',
                'en-GB' => 'Switzerland',
                'en-US' => 'Switzerland',
            ],
        ],
        'CZ' => [
            'iso3' => 'CZE',
            'name' => 'Czechia',
            'translations' => [
                'de-DE' => 'Tschechien',
                'en-GB' => 'Czechia',
                'en-US' => 'Czechia',
            ],
        ],
        'DE' => [
            'iso3' => 'DEU',
            'name' => 'Germany',
            'translations' => [
                'de-DE' => 'Deutschland',
                'en-GB' => 'Germany',
                'en-US' => 'Germany',
            ],
        ],
        'DK' => [
            'iso3' => 'DNK',
            'name' => 'Denmark',
            'translations' => [
                'de-DE' => 'Dänemark',
                'en-GB' => 'Denmark',
                'en-US' => 'Denmark',
            ],
        ],
        'FI' => [
            'iso3' => 'FIN',
            'name' => 'Finland',
            'translations' => [
                'de-DE' => 'Finnland',
                'en-GB' => 'Finland',
                'en-US' => 'Finland',
            ],
        ],
        'FR' => [
            'iso3' => 'FRA',
            'name' => 'France',
            'translations' => [
                'de-DE' => 'Frankreich',
                'en-GB' => 'France',
                'en-US' => 'France',
            ],
        ],
        'GB' => [
            'iso3' => 'GBR',
            'name' => 'Great Britain',
            'translations' => [
                'de-DE' => 'Großbritannien',
                'en-GB' => 'Great Britain',
                'en-US' => 'Great Britain',
            ],
        ],
        'IT' => [
            'iso3' => 'ITA',
            'name' => 'Italy',
            'translations' => [
                'de-DE' => 'Italien',
                'en-GB' => 'Italy',
                'en-US' => 'Italy',
            ],
        ],
        'NL' => [
            'iso3' => 'NLD',
            'name' => 'Netherlands',
            'translations' => [
                'de-DE' => 'Niederlande',
                'en-GB' => 'Netherlands',
                'en-US' => 'Netherlands',
            ],
        ],
        'NO' => [
            'iso3' => 'NOR',
            'name' => 'Norway',
            'translations' => [
                'de-DE' => 'Norwegen',
                'en-GB' => 'Norway',
                'en-US' => 'Norway',
            ],
        ],
        'PL' => [
            'iso3' => 'POL',
            'name' => 'Poland',
            'translations' => [
                'de-DE' => 'Polen',
                'en-GB' => 'Poland',
                'en-US' => 'Poland',
            ],
        ],
        'PT' => [
            'iso3' => 'PRT',
            'name' => 'Portugal',
            'translations' => [
                'de-DE' => 'Portugal',
                'en-GB' => 'Portugal',
                'en-US' => 'Portugal',
            ],
        ],
        'RU' => [
            'iso3' => 'RUS',
            'name' => 'Russian',
            'translations' => [
                'de-DE' => 'Russland',
                'en-GB' => 'Russian',
                'en-US' => 'Russian',
            ],
        ],
        'SE' => [
            'iso3' => 'SWE',
            'name' => 'Sweden',
            'translations' => [
                'de-DE' => 'Schweden',
                'en-GB' => 'Sweden',
                'en-US' => 'Sweden',
            ],
        ],
        'SP' => [
            'iso3' => 'ESP',
            'name' => 'Spain',
            'translations' => [
                'de-DE' => 'Spanien',
                'en-GB' => 'Spain',
                'en-US' => 'Spain',
            ],
        ],
    ];
}

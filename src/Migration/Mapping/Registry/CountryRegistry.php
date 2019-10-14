<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

class CountryRegistry extends AbstractMappingRegistry
{
    protected static $mapping = [
        'DE' => [
            'iso3' => 'DEU',
            'name' => 'Germany',
            'translations' => [
                'de-DE' => 'Deutschland',
                'en-GB' => 'Germany',
                'en-US' => 'Germany',
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
        'NL' => [
            'iso3' => 'NLD',
            'name' => 'Netherlands',
            'translations' => [
                'de-DE' => 'Niederlande',
                'en-GB' => 'Netherlands',
                'en-US' => 'Netherlands',
            ],
        ],
        'IN' => [
            'iso3' => 'IND',
            'name' => 'India',
            'translations' => [
                'de-DE' => 'Indien',
                'en-GB' => 'India',
                'en-US' => 'India',
            ],
        ],
        'JO' => [
            'iso3' => 'JOR',
            'name' => 'Jordan',
            'translations' => [
                'de-DE' => 'Jordanien',
                'en-GB' => 'Jordan',
                'en-US' => 'Jordan',
            ],
        ],
    ];
}

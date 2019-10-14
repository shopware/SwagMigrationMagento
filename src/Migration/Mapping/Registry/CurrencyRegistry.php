<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

class CurrencyRegistry extends AbstractMappingRegistry
{
    protected static $mapping = [
        'EUR' => [
            'symbol' => '€',
            'name' => 'Euro',
            'translations' => [
                'de-DE' => 'Euro',
                'en-GB' => 'Euro',
                'en-US' => 'Euro',
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
        'DKK' => [
            'symbol' => 'kr',
            'name' => 'Danish krone',
            'translations' => [
                'de-DE' => 'Dänische Krone',
                'en-GB' => 'Danish krone',
                'en-US' => 'Danish krone',
            ],
        ],
    ];
}

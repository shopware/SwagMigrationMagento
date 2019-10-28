<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

class LanguageRegistry extends AbstractMappingRegistry
{
    protected static $mapping = [
        'de-DE' => [
            'name' => 'German',
        ],
        'en-GB' => [
            'name' => 'English',
        ],
        'fr-FR' => [
            'name' => 'France',
        ],
    ];
}

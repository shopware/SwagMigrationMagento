<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

class LanguageRegistry extends AbstractMappingRegistry
{
    /**
     * @var array
     */
    protected static $mapping = [
        'cs-CZ' => [
            'name' => 'Czech',
        ],
        'da-DK' => [
            'name' => 'Danish',
        ],
        'de-DE' => [
            'name' => 'German',
        ],
        'en-GB' => [
            'name' => 'English',
        ],
        'en-IE' => [
            'name' => 'English',
        ],
        'en-US' => [
            'name' => 'English',
        ],
        'fr-FR' => [
            'name' => 'France',
        ],
        'fi-FI' => [
            'name' => 'Finnish',
        ],
        'nl-NL' => [
            'name' => 'Dutch',
        ],
        'pl-PL' => [
            'name' => 'Polish',
        ],
    ];
}

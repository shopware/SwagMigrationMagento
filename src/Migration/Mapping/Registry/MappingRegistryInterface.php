<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping\Registry;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
interface MappingRegistryInterface
{
    public static function get(string $identifier): ?array;
}

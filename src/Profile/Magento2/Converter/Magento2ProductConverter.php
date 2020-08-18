<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\Converter\ProductConverter;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class Magento2ProductConverter extends ProductConverter
{
    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        if (isset($data['type_id']) && $data['type_id'] === 'configurable' && !isset($data['price'])) {
            $data['price'] = 0;
        }

        return parent::convert($data, $context, $migrationContext);
    }
}

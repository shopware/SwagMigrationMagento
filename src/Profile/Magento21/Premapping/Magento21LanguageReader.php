<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento21\Premapping;

use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2LanguageReader;
use Swag\MigrationMagento\Profile\Magento21\Magento21Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento21LanguageReader extends Magento2LanguageReader
{
    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento21Profile;
    }
}
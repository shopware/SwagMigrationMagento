<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento19\Premapping;

use Swag\MigrationMagento\Profile\Magento\Premapping\CustomerGroupReader;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento19CustomerGroupReader extends CustomerGroupReader
{
    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }
}

<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento21\Premapping;

use Swag\MigrationMagento\Profile\Magento\DataSelection\ProductDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\ProductReviewDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\SeoUrlDataSelection;
use Swag\MigrationMagento\Profile\Magento\Premapping\TaxReader;
use Swag\MigrationMagento\Profile\Magento21\Magento21Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento21TaxReader extends TaxReader
{
    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento21Profile
            && (\in_array(ProductDataSelection::IDENTIFIER, $entityGroupNames, true)
            || \in_array(ProductReviewDataSelection::IDENTIFIER, $entityGroupNames, true)
            || \in_array(SeoUrlDataSelection::IDENTIFIER, $entityGroupNames, true));
    }
}

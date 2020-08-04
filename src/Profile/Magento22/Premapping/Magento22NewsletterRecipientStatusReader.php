<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento22\Premapping;

use Swag\MigrationMagento\Profile\Magento\DataSelection\NewsletterRecipientDataSelection;
use Swag\MigrationMagento\Profile\Magento\Premapping\NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento22\Magento22Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento22NewsletterRecipientStatusReader extends NewsletterRecipientStatusReader
{
    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento22Profile
            && \in_array(NewsletterRecipientDataSelection::IDENTIFIER, $entityGroupNames, true);
    }
}

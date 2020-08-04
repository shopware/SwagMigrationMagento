<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento19\Premapping;

use Swag\MigrationMagento\Profile\Magento\DataSelection\NewsletterRecipientDataSelection;
use Swag\MigrationMagento\Profile\Magento\Premapping\NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento19NewsletterRecipientStatusReader extends NewsletterRecipientStatusReader
{
    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && \in_array(NewsletterRecipientDataSelection::IDENTIFIER, $entityGroupNames, true);
    }
}

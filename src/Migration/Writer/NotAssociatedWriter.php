<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Writer;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Writer\AbstractWriter;

class NotAssociatedWriter extends AbstractWriter
{
    public function supports(): string
    {
        return DefaultEntities::NOT_ASSOCIATED_MEDIA;
    }
}

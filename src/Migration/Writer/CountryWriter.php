<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Writer;

use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Writer\AbstractWriter;

class CountryWriter extends AbstractWriter
{
    public function supports(): string
    {
        return DefaultEntities::COUNTRY;
    }
}

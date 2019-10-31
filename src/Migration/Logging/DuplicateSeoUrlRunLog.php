<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Logging;

use SwagMigrationAssistant\Migration\Logging\Log\BaseRunLogEntry;

class DuplicateSeoUrlRunLog extends BaseRunLogEntry
{
    public function getLevel(): string
    {
        return self::LOG_LEVEL_INFO;
    }

    public function getCode(): string
    {
        return 'SWAG_MIGRATION__MAGENTO_DUPLICATE_SEO_URL';
    }

    public function getTitle(): string
    {
        return 'Duplicate seo url';
    }

    public function getParameters(): array
    {
        return [
            'sourceId' => $this->getSourceId(),
        ];
    }

    public function getDescription(): string
    {
        $args = $this->getParameters();

        return sprintf(
            'The seo url with source id "%s" could not be converted because it is duplicated.',
            $args['sourceId']
        );
    }
}

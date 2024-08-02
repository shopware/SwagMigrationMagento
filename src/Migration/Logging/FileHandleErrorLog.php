<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Logging;

use Shopware\Core\Framework\Log\Package;
use SwagMigrationAssistant\Migration\Logging\Log\BaseRunLogEntry;

#[Package('services-settings')]
class FileHandleErrorLog extends BaseRunLogEntry
{
    public function __construct(
        string $runId,
        string $entity,
        ?string $sourceId = null
    ) {
        parent::__construct($runId, $entity, $sourceId);
    }

    public function getLevel(): string
    {
        return self::LOG_LEVEL_ERROR;
    }

    public function getCode(): string
    {
        return 'SWAG_MIGRATION_MAGENTO__COULD_NOT_OPEN_FILE';
    }

    public function getTitle(): string
    {
        return 'An exception occurred';
    }

    public function getParameters(): array
    {
        return [
            'entity' => $this->getEntity(),
            'sourceId' => $this->getSourceId(),
            'exceptionCode' => $this->getCode(),
            'description' => 'Could not open file to read or write',
        ];
    }

    public function getDescription(): string
    {
        return $this->getParameters()['description'];
    }
}

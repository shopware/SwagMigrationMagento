<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local;

use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\LocalReaderInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ReaderRegistry
{
    /**
     * @var LocalReaderInterface[]
     */
    private $readers;

    public function __construct(iterable $readers = [])
    {
        $this->readers = $readers;
    }

    public function getReader(MigrationContextInterface $migrationContext): LocalReaderInterface
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($migrationContext)) {
                return $reader;
            }
        }

        throw new \Exception($migrationContext->getDataSet()::getEntity());
    }
}

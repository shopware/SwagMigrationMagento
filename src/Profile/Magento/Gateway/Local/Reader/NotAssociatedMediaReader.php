<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class NotAssociatedMediaReader extends AbstractReader implements LocalReaderInterface
{
    /**
     * @var string
     */
    private $sourcePath;

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::NOT_ASSOCIATED_MEDIA;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $installationRoot = $migrationContext->getConnection()->getCredentialFields()['installationRoot'];
        $this->sourcePath = $installationRoot . '/media/wysiwyg/';
        $files = $this->dirToArray($this->sourcePath);

        return $files;
    }

    private function dirToArray($dir)
    {
        $result = [];

        $cdir = scandir($dir, 1);
        foreach ($cdir as $key => $value) {
            if (!in_array($value, ['.', '..'], true)) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                    $result = array_merge($this->dirToArray($dir . $value . DIRECTORY_SEPARATOR), $result);
                } else {
                    $result[]['path'] = str_replace($this->sourcePath, '', $dir) . $value;
                }
            }
        }

        return $result;
    }
}

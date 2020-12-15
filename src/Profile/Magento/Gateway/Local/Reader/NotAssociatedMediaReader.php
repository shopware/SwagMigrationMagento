<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Exception\MediaPathNotReachableException;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class NotAssociatedMediaReader extends AbstractReader
{
    /**
     * @var string
     */
    protected $sourcePath;

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $installationRoot = $this->getInstallationRoot($migrationContext);
        $this->sourcePath = $installationRoot . '/media/wysiwyg/';

        if ($installationRoot === '' || \is_dir($this->sourcePath) === false) {
            throw new MediaPathNotReachableException($this->sourcePath);
        }

        $files = [];
        $this->dirToArray($this->sourcePath, $files);

        return $files;
    }

    protected function dirToArray(string $dir, array &$result): void
    {
        $cdir = \scandir($dir, 1);
        foreach ($cdir as $value) {
            if (!\in_array($value, ['.', '..'], true)) {
                if (\is_dir($dir . \DIRECTORY_SEPARATOR . $value)) {
                    $this->dirToArray($dir . $value . \DIRECTORY_SEPARATOR, $result);
                } else {
                    $result[]['path'] = \str_replace($this->sourcePath, '', $dir) . $value;
                }
            }
        }
    }

    private function getInstallationRoot(MigrationContextInterface $migrationContext): string
    {
        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return '';
        }

        $credentials = $connection->getCredentialFields();
        if (!isset($credentials['installationRoot']) || $credentials['installationRoot'] === '') {
            return '';
        }
        $installRoot = $credentials['installationRoot'];
        $installRoot = \ltrim($installRoot, '/');
        $installRoot = \rtrim($installRoot, '/');
        $installRoot = '/' . $installRoot;

        return $installRoot;
    }
}

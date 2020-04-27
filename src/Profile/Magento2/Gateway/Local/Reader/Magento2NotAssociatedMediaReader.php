<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\NotAssociatedMediaReader;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class Magento2NotAssociatedMediaReader extends NotAssociatedMediaReader
{
    public const NOT_ASSOCIATED_MEDIA_PATH = '/pub/media/wysiwyg/';

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return [];
        }

        $credentialFields = $connection->getCredentialFields();
        if ($credentialFields === null) {
            return [];
        }

        $installationRoot = $credentialFields['installationRoot'];
        $this->sourcePath = $installationRoot . self::NOT_ASSOCIATED_MEDIA_PATH;

        $files = [];
        $this->dirToArray($this->sourcePath, $files);

        return $files;
    }
}

<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Media;

use GuzzleHttp\Client;
use Swag\MigrationMagento\Profile\Magento\Media\LocalMediaProcessor;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class Magento2LocalMediaProcessor extends LocalMediaProcessor
{
    public const PUBLIC_PATH = '/pub';

    protected function doMediaDownloadRequests(array $media, array &$mappedWorkload, Client $client, string $shopUrl): array
    {
        $promises = [];
        foreach ($media as $mediaFile) {
            $uuid = mb_strtolower($mediaFile->getMediaId());
            $additionalData = [];
            $additionalData['uri'] = $shopUrl . self::PUBLIC_PATH . $mediaFile->getUri();

            $additionalData['file_size'] = $mediaFile->getFileSize();
            $additionalData['file_name'] = $mediaFile->getFileName();
            $mappedWorkload[$uuid]->setAdditionalData($additionalData);

            $promise = $this->doNormalDownloadRequest($mappedWorkload[$uuid], $client);

            if ($promise !== null) {
                $promises[$uuid] = $promise;
            }
        }

        return $promises;
    }

    protected function getInstallationRoot(MigrationContextInterface $migrationContext): string
    {
        return parent::getInstallationRoot($migrationContext) . self::PUBLIC_PATH;
    }
}

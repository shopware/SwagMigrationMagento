<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Media;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Swag\MigrationMagento\Profile\Magento\Media\LocalMediaProcessor;
use SwagMigrationAssistant\Migration\Media\MediaProcessWorkloadStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

/**
 * @internal
 */
class TestLocalMediaProcessor extends LocalMediaProcessor
{
    public bool $rejectDownloads = false;

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return true;
    }

    public function request(MediaProcessWorkloadStruct $workload, Client $client): PromiseInterface
    {
        $promise = $this->doNormalDownloadRequest($workload, $client);
        if ($promise === null) {
            throw new \RuntimeException('Expected a download promise.');
        }

        return $promise;
    }

    protected function doMediaDownloadRequests(array $media, array &$mappedWorkload, Client $client, string $shopUrl): array
    {
        if (!$this->rejectDownloads) {
            return parent::doMediaDownloadRequests($media, $mappedWorkload, $client, $shopUrl);
        }

        $promises = [];
        foreach ($media as $mediaFile) {
            $uuid = \mb_strtolower($mediaFile['media_id']);
            $mappedWorkload[$uuid]->setAdditionalData([
                'uri' => $shopUrl . $mediaFile['uri'],
                'file_size' => $mediaFile['file_size'],
                'file_name' => $mediaFile['file_name'],
            ]);
            $promises[$uuid] = Promise\Create::rejectionFor(new \RuntimeException('unreachable'));
        }

        return $promises;
    }
}

<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Media;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Exception\MediaPathNotReachableException;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use SwagMigrationAssistant\Exception\NoFileSystemPermissionsException;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\CannotGetFileRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\ExceptionRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileProcessorInterface;
use SwagMigrationAssistant\Migration\Media\MediaProcessWorkloadStruct;
use SwagMigrationAssistant\Migration\MessageQueue\Handler\ProcessMediaHandler;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Media\BaseMediaService;

class LocalMediaProcessor extends BaseMediaService implements MediaFileProcessorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $mediaFileRepo;

    /**
     * @var EntityRepositoryInterface
     */
    protected $mediaRepo;

    /**
     * @var FileSaver
     */
    protected $fileSaver;

    /**
     * @var LoggingServiceInterface
     */
    protected $loggingService;

    /**
     * @var MigrationContextInterface
     */
    protected $migrationContext;

    public function __construct(
        EntityRepositoryInterface $migrationMediaFileRepo,
        EntityRepositoryInterface $mediaRepo,
        FileSaver $fileSaver,
        LoggingServiceInterface $loggingService,
        Connection $dbalConnection
    ) {
        $this->mediaFileRepo = $migrationMediaFileRepo;
        $this->mediaRepo = $mediaRepo;
        $this->fileSaver = $fileSaver;
        $this->loggingService = $loggingService;
        parent::__construct($dbalConnection);
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === MediaDataSet::getEntity();
    }

    public function process(
        MigrationContextInterface $migrationContext,
        Context $context,
        array $workload,
        int $fileChunkByteSize
    ): array {
        $mappedWorkload = [];
        $this->migrationContext = $migrationContext;
        $runId = $migrationContext->getRunUuid();

        foreach ($workload as $work) {
            $mappedWorkload[$work->getMediaId()] = $work;
        }

        if (!\is_dir('_temp') && !\mkdir('_temp') && !\is_dir('_temp')) {
            $exception = new NoFileSystemPermissionsException();
            $this->loggingService->addLogEntry(new ExceptionRunLog(
                $runId,
                DefaultEntities::MEDIA,
                $exception
            ));
            $this->loggingService->saveLogging($context);

            return $workload;
        }

        $media = $this->getMediaFiles(\array_keys($mappedWorkload), $migrationContext->getRunUuid());
        $mappedWorkload = $this->getMediaPathMapping($media, $mappedWorkload);

        $installationRoot = $this->getInstallationRoot($migrationContext);

        if ($installationRoot === '' || \is_dir($installationRoot) === false) {
            $shopUrl = $this->getShopUrl($migrationContext);

            if ($shopUrl === '') {
                $exception = new MediaPathNotReachableException($installationRoot);
                $this->loggingService->addLogEntry(new ExceptionRunLog(
                    $runId,
                    DefaultEntities::MEDIA,
                    $exception
                ));
                $this->loggingService->saveLogging($context);

                return $workload;
            }

            return $this->downloadMediaFiles(
                $media,
                $shopUrl,
                $mappedWorkload,
                $workload,
                $migrationContext,
                $context
            );
        }

        return $this->copyMediaFiles($media, $mappedWorkload, $migrationContext, $context);
    }

    /**
     * Start all the download requests for the media in parallel (async) and return the promise array.
     *
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     */
    protected function doMediaDownloadRequests(array $media, array &$mappedWorkload, Client $client, string $shopUrl): array
    {
        $promises = [];
        foreach ($media as $mediaFile) {
            $uuid = \mb_strtolower($mediaFile['media_id']);
            $additionalData = [];
            $additionalData['uri'] = $shopUrl . $mediaFile['uri'];

            $additionalData['file_size'] = $mediaFile['file_size'];
            $additionalData['file_name'] = $mediaFile['file_name'];
            $mappedWorkload[$uuid]->setAdditionalData($additionalData);

            $promise = $this->doNormalDownloadRequest($mappedWorkload[$uuid], $client);

            if ($promise !== null) {
                $promises[$uuid] = $promise;
            }
        }

        return $promises;
    }

    protected function doNormalDownloadRequest(MediaProcessWorkloadStruct $workload, Client $client): ?Promise\PromiseInterface
    {
        $additionalData = $workload->getAdditionalData();

        try {
            $promise = $client->getAsync(
                $additionalData['uri'],
                [
                    'query' => ['alt' => 'media'],
                ]
            );

            $workload->setCurrentOffset((int) $additionalData['file_size']);
            $workload->setState(MediaProcessWorkloadStruct::FINISH_STATE);
        } catch (\Exception $exception) {
            $promise = null;
            $workload->setErrorCount($workload->getErrorCount() + 1);
        }

        return $promise;
    }

    protected function getInstallationRoot(MigrationContextInterface $migrationContext): string
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

    protected function getDataSetEntity(MigrationContextInterface $migrationContext): ?string
    {
        $dataSet = $migrationContext->getDataSet();
        if ($dataSet === null) {
            return null;
        }

        return $dataSet::getEntity();
    }

    /**
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     *
     * @return MediaProcessWorkloadStruct[]
     */
    private function getMediaPathMapping(array $media, array $mappedWorkload): array
    {
        foreach ($media as $mediaFile) {
            $mappedWorkload[$mediaFile['media_id']]->setAdditionalData(['path' => $mediaFile['uri']]);
        }

        return $mappedWorkload;
    }

    /**
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     *
     * @return MediaProcessWorkloadStruct[]
     */
    private function copyMediaFiles(
        array $media,
        array $mappedWorkload,
        MigrationContextInterface $migrationContext,
        Context $context
    ): array {
        $processedMedia = [];
        $failureUuids = [];

        foreach ($media as $mediaFile) {
            $rowId = $mediaFile['id'];
            $mediaId = $mediaFile['media_id'];
            $sourcePath = $this->getInstallationRoot($migrationContext) . $mappedWorkload[$mediaId]->getAdditionalData()['path'];

            $fileExtension = \pathinfo($sourcePath, \PATHINFO_EXTENSION);
            $filePath = \sprintf('_temp/%s.%s', $rowId, $fileExtension);

            if (\copy($sourcePath, $filePath)) {
                try {
                    $fileSize = \filesize($filePath);
                    $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::FINISH_STATE);
                    $this->persistFileToMedia($filePath, $mediaId, $mediaFile['file_name'], $fileSize, $fileExtension, $context);
                } catch (\Exception $e) {
                    $failureUuids[] = $mediaId;
                    $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::ERROR_STATE);
                    $this->loggingService->addLogEntry(new ExceptionRunLog(
                        $mappedWorkload[$mediaId]->getRunId(),
                        DefaultEntities::MEDIA,
                        $e,
                        $mediaId
                    ));
                }

                \unlink($filePath);
            } else {
                $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::ERROR_STATE);
                $this->loggingService->addLogEntry(new CannotGetFileRunLog(
                    $mappedWorkload[$mediaId]->getRunId(),
                    DefaultEntities::MEDIA,
                    $mediaId,
                    $sourcePath
                ));
                $failureUuids[$rowId] = $mediaId;
            }
            $processedMedia[] = $mediaId;
        }
        $this->setProcessedFlag($migrationContext->getRunUuid(), $context, $processedMedia, $failureUuids);
        $this->loggingService->saveLogging($context);

        return \array_values($mappedWorkload);
    }

    private function persistFileToMedia(
        string $filePath,
        string $mediaId,
        string $fileName,
        int $fileSize,
        string $fileExtension,
        Context $context
    ): void {
        $mimeType = \mime_content_type($filePath);
        $mediaFile = new MediaFile($filePath, $mimeType, $fileExtension, $fileSize);
        $fileName = \preg_replace('/[^a-z0-9_-]+/', '-', \mb_strtolower($fileName));

        try {
            $this->fileSaver->persistFileToMedia($mediaFile, $fileName, $mediaId, $context);
        } catch (DuplicatedMediaFileNameException $e) {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $fileName . \mb_substr(Uuid::randomHex(), 0, 5),
                $mediaId,
                $context
            );
        } catch (IllegalFileNameException | EmptyMediaFilenameException $e) {
            $this->fileSaver->persistFileToMedia($mediaFile, Uuid::randomHex(), $mediaId, $context);
        }
    }

    private function setProcessedFlag(string $runId, Context $context, array $finishedUuids, array $failureUuids): void
    {
        $mediaFiles = $this->getMediaFiles($finishedUuids, $runId);
        $updateableMediaEntities = [];
        foreach ($mediaFiles as $mediaFile) {
            if (!\in_array($mediaFile['media_id'], $failureUuids, true)) {
                $updateableMediaEntities[] = [
                    'id' => $mediaFile['id'],
                    'processed' => true,
                ];
            }
        }

        if (empty($updateableMediaEntities)) {
            return;
        }

        $this->mediaFileRepo->update($updateableMediaEntities, $context);
    }

    private function getShopUrl(MigrationContextInterface $migrationContext): string
    {
        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return '';
        }

        $credentials = $connection->getCredentialFields();
        if (!isset($credentials['shopUrl'])) {
            return '';
        }

        return \rtrim($credentials['shopUrl'], '/');
    }

    private function downloadMediaFiles(
        array $media,
        string $shopUrl,
        array $mappedWorkload,
        array $workload,
        MigrationContextInterface $migrationContext,
        Context $context
    ): array {
        //Do download requests and store the promises
        $client = new Client([
            'verify' => false,
        ]);
        $promises = $this->doMediaDownloadRequests($media, $mappedWorkload, $client, $shopUrl);

        // Wait for the requests to complete, even if some of them fail
        /** @var array $results */
        $results = Promise\settle($promises)->wait();

        //handle responses
        $failureUuids = [];
        $finishedUuids = [];
        foreach ($results as $uuid => $result) {
            $state = $result['state'];
            $additionalData = $mappedWorkload[$uuid]->getAdditionalData();

            $oldWorkloadSearchResult = \array_filter(
                $workload,
                function (MediaProcessWorkloadStruct $work) use ($uuid) {
                    return $work->getMediaId() === $uuid;
                }
            );

            /** @var MediaProcessWorkloadStruct $oldWorkload */
            $oldWorkload = \array_pop($oldWorkloadSearchResult);

            if ($state !== 'fulfilled') {
                $mappedWorkload[$uuid] = $oldWorkload;
                $mappedWorkload[$uuid]->setAdditionalData($additionalData);
                $mappedWorkload[$uuid]->setErrorCount($mappedWorkload[$uuid]->getErrorCount() + 1);

                if ($mappedWorkload[$uuid]->getErrorCount() > ProcessMediaHandler::MEDIA_ERROR_THRESHOLD) {
                    $failureUuids[] = $uuid;
                    $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);
                    $this->loggingService->addLogEntry(new CannotGetFileRunLog(
                        $mappedWorkload[$uuid]->getRunId(),
                        DefaultEntities::MEDIA,
                        $mappedWorkload[$uuid]->getMediaId(),
                        $mappedWorkload[$uuid]->getAdditionalData()['uri']
                    ));
                }

                continue;
            }

            $response = $result['value'];
            $fileExtension = \pathinfo($additionalData['uri'], \PATHINFO_EXTENSION);
            $filePath = \sprintf('_temp/%s.%s', $uuid, $fileExtension);
            $streamContext = \stream_context_create([
                'http' => [
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ],
            ]);
            $fileHandle = \fopen($filePath, 'ab', false, $streamContext);
            \fwrite($fileHandle, $response->getBody()->getContents());
            $fileSize = (int) \filesize($filePath);
            \fclose($fileHandle);

            if ($mappedWorkload[$uuid]->getState() === MediaProcessWorkloadStruct::FINISH_STATE) {
                try {
                    $this->persistFileToMedia(
                        $filePath,
                        $uuid,
                        $additionalData['file_name'],
                        $fileSize,
                        $fileExtension,
                        $context
                    );
                    \unlink($filePath);
                    $finishedUuids[] = $uuid;
                } catch (\Exception $e) {
                    $failureUuids[] = $uuid;
                    $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);
                    $this->loggingService->addLogEntry(new ExceptionRunLog(
                        $mappedWorkload[$uuid]->getRunId(),
                        DefaultEntities::MEDIA,
                        $e,
                        $uuid
                    ));
                }
            }

            if ($oldWorkload->getErrorCount() === $mappedWorkload[$uuid]->getErrorCount()) {
                $mappedWorkload[$uuid]->setErrorCount(0);
            }
        }

        $this->setProcessedFlag($migrationContext->getRunUuid(), $context, $finishedUuids, $failureUuids);
        $this->loggingService->saveLogging($context);

        return \array_values($mappedWorkload);
    }
}

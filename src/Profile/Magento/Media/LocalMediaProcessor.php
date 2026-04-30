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
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Exception\MigrationMagentoException;
use SwagMigrationAssistant\Migration\Logging\Log\Builder\MigrationLogBuilder;
use SwagMigrationAssistant\Migration\Logging\Log\MediaFileMissingLog;
use SwagMigrationAssistant\Migration\Logging\Log\MediaMimeTypeUnknownLog;
use SwagMigrationAssistant\Migration\Logging\Log\MediaTemporaryFileFailedLog;
use SwagMigrationAssistant\Migration\Logging\Log\RunExceptionLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileProcessorInterface;
use SwagMigrationAssistant\Migration\Media\MediaProcessWorkloadStruct;
use SwagMigrationAssistant\Migration\Media\Processor\BaseMediaService;
use SwagMigrationAssistant\Migration\Media\SwagMigrationMediaFileCollection;
use SwagMigrationAssistant\Migration\MigrationConfiguration;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('fundamentals@after-sales')]
abstract class LocalMediaProcessor extends BaseMediaService implements MediaFileProcessorInterface
{
    /**
     * @var EntityRepository<MediaCollection>
     */
    protected EntityRepository $mediaRepo;

    protected FileSaver $fileSaver;

    protected LoggingServiceInterface $loggingService;

    protected MigrationContextInterface $migrationContext;

    /**
     * @internal
     *
     * @param EntityRepository<SwagMigrationMediaFileCollection> $migrationMediaFileRepo
     * @param EntityRepository<MediaCollection> $mediaRepo
     */
    public function __construct(
        EntityRepository $migrationMediaFileRepo,
        EntityRepository $mediaRepo,
        FileSaver $fileSaver,
        LoggingServiceInterface $loggingService,
        Connection $dbalConnection,
        private readonly MigrationConfiguration $migrationConfig,
    ) {
        $this->mediaRepo = $mediaRepo;
        $this->fileSaver = $fileSaver;
        $this->loggingService = $loggingService;
        parent::__construct($dbalConnection, $migrationMediaFileRepo);
    }

    public function process(
        MigrationContextInterface $migrationContext,
        Context $context,
        array $workload,
    ): array {
        $mappedWorkload = [];
        $this->migrationContext = $migrationContext;

        foreach ($workload as $work) {
            $mappedWorkload[$work->getMediaId()] = $work;
        }

        $media = $this->getMediaFiles(\array_keys($mappedWorkload), $migrationContext->getRunUuid());
        $mappedWorkload = $this->getMediaPathMapping($media, $mappedWorkload);

        $installationRoot = $this->getInstallationRoot($migrationContext);

        if ($installationRoot === '' || \is_dir($installationRoot) === false) {
            $shopUrl = $this->getShopUrl($migrationContext);

            if ($shopUrl === '') {
                $this->loggingService->log(
                    MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                        ->withEntityName(MediaDefinition::ENTITY_NAME)
                        ->withSourceData([
                            'installationRoot' => $installationRoot,
                            'media' => $media,
                        ])
                        ->withConvertedData(['shopUrl' => $shopUrl])
                        ->build(MediaFileMissingLog::class)
                );

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
     * @param array<array<string, mixed>> $media
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     *
     * @return array<Promise\PromiseInterface>
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

        $credentials = $connection->getCredentialFields();
        if (!isset($credentials['installationRoot']) || $credentials['installationRoot'] === '') {
            return '';
        }
        $installRoot = (string) $credentials['installationRoot'];
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
     * @param array<array<string, mixed>> $media
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
     * @param array<array<string, mixed>> $media
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     *
     * @return MediaProcessWorkloadStruct[]
     */
    private function copyMediaFiles(
        array $media,
        array $mappedWorkload,
        MigrationContextInterface $migrationContext,
        Context $context,
    ): array {
        $this->migrationContext = $migrationContext;

        $processedMedia = [];
        $failureUuids = [];

        foreach ($media as $mediaFile) {
            $mediaId = $mediaFile['media_id'];
            $sourcePath = $this->getInstallationRoot($migrationContext) . $mappedWorkload[$mediaId]->getAdditionalData()['path'];

            $fileExtension = \pathinfo($sourcePath, \PATHINFO_EXTENSION);
            $filePath = \tempnam(\sys_get_temp_dir(), 'SwagMigrationMagento-');

            if ($filePath === false) {
                $failureUuids[] = (string) $mediaId;
                $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

                $this->loggingService->log(
                    MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                        ->withEntityName(MediaDefinition::ENTITY_NAME)
                        ->withSourceData($mediaFile)
                        ->withConvertedData(
                            [
                                'media_id' => $mediaId,
                                'source_path' => $sourcePath,
                                'media' => $media,
                            ]
                        )
                        ->withEntityId($mediaId)
                        ->build(MediaTemporaryFileFailedLog::class)
                );

                continue;
            }

            if (\copy($sourcePath, $filePath)) {
                try {
                    $fileSize = \filesize($filePath);
                    if ($fileSize === false) {
                        throw MigrationMagentoException::mediaFileSizeError($filePath);
                    }

                    $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::FINISH_STATE);
                    $this->persistFileToMedia(
                        $filePath,
                        $mediaId,
                        $mediaFile['file_name'],
                        $fileSize,
                        $fileExtension,
                        $mappedWorkload,
                        $failureUuids,
                        $context
                    );
                } catch (\Exception $e) {
                    $failureUuids[] = $mediaId;
                    $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

                    $this->loggingService->log(
                        MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                            ->withEntityName(MediaDefinition::ENTITY_NAME)
                            ->withException($e)
                            ->withConvertedData(
                                [
                                    'file_path' => $filePath,
                                    'source_path' => $sourcePath,
                                    'media' => $media,
                                ]
                            )
                            ->withEntityId($mediaId)
                            ->build(RunExceptionLog::class)
                    );
                }

                \unlink($filePath);
            } else {
                $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

                $this->loggingService->log(
                    MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                        ->withEntityName(MediaDefinition::ENTITY_NAME)
                        ->withSourceData($mediaFile)
                        ->withConvertedData(
                            [
                                'file_path' => $filePath,
                                'source_path' => $sourcePath,
                                'media' => $media,
                            ]
                        )
                        ->withEntityId($mediaId)
                        ->build(MediaFileMissingLog::class)
                );

                $failureUuids[] = $mediaId;
            }

            $processedMedia[] = $mediaId;
        }

        $this->setProcessedFlag($migrationContext->getRunUuid(), $context, $processedMedia, $failureUuids);

        return \array_values($mappedWorkload);
    }

    /**
     * @param array<MediaProcessWorkloadStruct> $mappedWorkload
     * @param list<string> $failedMedia
     */
    private function persistFileToMedia(
        string $filePath,
        string $mediaId,
        string $fileName,
        int $fileSize,
        string $fileExtension,
        array $mappedWorkload,
        array &$failedMedia,
        Context $context,
    ): void {
        $mimeType = \mime_content_type($filePath);

        if ($mimeType === false) {
            $failedMedia[] = $mediaId;
            $mappedWorkload[$mediaId]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

            $this->loggingService->log(
                MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                    ->withEntityName(MediaDefinition::ENTITY_NAME)
                    ->withFieldName('mimeType')
                    ->withConvertedData(
                        [
                            'file_path' => $filePath,
                            'media_id' => $mediaId,
                            'file_name' => $fileName,
                            'file_size' => $fileSize,
                            'file_extension' => $fileExtension,
                        ]
                    )
                    ->withEntityId($mediaId)
                    ->build(MediaMimeTypeUnknownLog::class)
            );

            return;
        }

        $mediaFile = new MediaFile($filePath, $mimeType, $fileExtension, $fileSize);
        $fileName = (string) \preg_replace('/[^a-z0-9_-]+/', '-', \mb_strtolower($fileName));

        try {
            $this->fileSaver->persistFileToMedia($mediaFile, $fileName, $mediaId, $context);
        } catch (MediaException $mediaException) {
            if ($mediaException->getErrorCode() === MediaException::MEDIA_DUPLICATED_FILE_NAME) {
                $this->fileSaver->persistFileToMedia(
                    $mediaFile,
                    $fileName . \mb_substr(Uuid::randomHex(), 0, 5),
                    $mediaId,
                    $context
                );
            } elseif (\in_array($mediaException->getErrorCode(), [MediaException::MEDIA_ILLEGAL_FILE_NAME, MediaException::MEDIA_EMPTY_FILE_NAME], true)) {
                $this->fileSaver->persistFileToMedia($mediaFile, Uuid::randomHex(), $mediaId, $context);
            }
        }
    }

    private function getShopUrl(MigrationContextInterface $migrationContext): string
    {
        $connection = $migrationContext->getConnection();

        $credentials = $connection->getCredentialFields();
        if (!isset($credentials['shopUrl'])) {
            return '';
        }

        return \rtrim((string) $credentials['shopUrl'], '/');
    }

    /**
     * @param array<int, array<string, mixed>> $media
     * @param array<int, MediaProcessWorkloadStruct> $mappedWorkload
     * @param array<int, mixed> $workload
     *
     * @return array<MediaProcessWorkloadStruct>
     */
    private function downloadMediaFiles(
        array $media,
        string $shopUrl,
        array $mappedWorkload,
        array $workload,
        MigrationContextInterface $migrationContext,
        Context $context,
    ): array {
        // Do download requests and store the promises
        $client = new Client();
        $promises = $this->doMediaDownloadRequests($media, $mappedWorkload, $client, $shopUrl);

        // Wait for the requests to complete, even if some of them fail
        $results = Utils::settle($promises)->wait();

        // handle responses
        $failureUuids = [];
        $finishedUuids = [];
        foreach ($results as $uuid => $result) {
            $state = $result['state'];
            $additionalData = $mappedWorkload[$uuid]->getAdditionalData();

            $oldWorkloadSearchResult = \array_filter(
                $workload,
                static function (MediaProcessWorkloadStruct $work) use ($uuid) {
                    return $work->getMediaId() === $uuid;
                }
            );

            /** @var MediaProcessWorkloadStruct $oldWorkload */
            $oldWorkload = \array_pop($oldWorkloadSearchResult);

            if ($state !== 'fulfilled') {
                $mappedWorkload[$uuid] = $oldWorkload;
                $mappedWorkload[$uuid]->setAdditionalData($additionalData);
                $mappedWorkload[$uuid]->setErrorCount($mappedWorkload[$uuid]->getErrorCount() + 1);

                if ($mappedWorkload[$uuid]->getErrorCount() > $this->migrationConfig->migrationDefaultExceptionThreshold) {
                    $failureUuids[] = $uuid;
                    $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

                    $this->loggingService->log(
                        MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                            ->withEntityName(MediaDefinition::ENTITY_NAME)
                            ->withConvertedData(
                                [
                                    'media_id' => $mappedWorkload[$uuid]->getMediaId(),
                                    'uri' => $additionalData['uri'],
                                    'media' => $media,
                                ]
                            )
                            ->withEntityId($mappedWorkload[$uuid]->getMediaId())
                            ->build(MediaFileMissingLog::class)
                    );
                }

                continue;
            }

            /** @var Response $response */
            $response = $result['value'];
            $fileExtension = \pathinfo($additionalData['uri'], \PATHINFO_EXTENSION);
            $filePath = \tempnam(\sys_get_temp_dir(), 'SwagMigrationMagento-');
            if ($filePath === false) {
                $failureUuids[] = $uuid;
                $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

                $this->loggingService->log(
                    MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                        ->withEntityName(MediaDefinition::ENTITY_NAME)
                        ->withConvertedData(
                            [
                                'media_id' => $mappedWorkload[$uuid]->getMediaId(),
                                'uri' => $additionalData['uri'],
                                'media' => $media,
                            ]
                        )
                        ->withEntityId($mappedWorkload[$uuid]->getMediaId())
                        ->build(MediaTemporaryFileFailedLog::class)
                );

                continue;
            }

            $streamContext = \stream_context_create([
                'http' => [
                    'follow_location' => 0,
                    'max_redirects' => 0,
                ],
            ]);

            $fileHandle = \fopen($filePath, 'ab', false, $streamContext);
            if ($fileHandle === false) {
                $failureUuids[] = $uuid;
                $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

                $this->loggingService->log(
                    MigrationLogBuilder::fromMigrationContext($migrationContext)
                        ->withEntityName(MediaDefinition::ENTITY_NAME)
                        ->withSourceData([
                            'media_id' => $uuid,
                            'source_path' => $filePath,
                            'media' => $media,
                        ])
                        ->withEntityId($uuid)
                        ->build(MediaTemporaryFileFailedLog::class)
                );

                continue;
            }
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
                        $mappedWorkload,
                        $failureUuids,
                        $context
                    );
                    \unlink($filePath);
                    $finishedUuids[] = $uuid;
                } catch (\Exception $e) {
                    $failureUuids[] = $uuid;
                    $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);

                    $this->loggingService->log(
                        MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                            ->withEntityName(MediaDefinition::ENTITY_NAME)
                            ->withException($e)
                            ->withConvertedData(
                                [
                                    'file_path' => $filePath,
                                    'uri' => $additionalData['uri'],
                                    'media_id' => $uuid,
                                    'media' => $media,
                                ]
                            )
                            ->withEntityId($uuid)
                            ->build(RunExceptionLog::class)
                    );
                }
            }

            if ($oldWorkload->getErrorCount() === $mappedWorkload[$uuid]->getErrorCount()) {
                $mappedWorkload[$uuid]->setErrorCount(0);
            }
        }

        $this->setProcessedFlag($migrationContext->getRunUuid(), $context, $finishedUuids, $failureUuids);

        return \array_values($mappedWorkload);
    }
}

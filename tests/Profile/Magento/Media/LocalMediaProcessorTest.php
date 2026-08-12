<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Media;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Swag\MigrationMagento\Profile\Magento\Media\LocalMediaProcessor;
use Swag\MigrationMagento\Profile\Magento24\Magento24Profile;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaProcessWorkloadStruct;
use SwagMigrationAssistant\Migration\Media\SwagMigrationMediaFileCollection;
use SwagMigrationAssistant\Migration\Media\SwagMigrationMediaFileDefinition;
use SwagMigrationAssistant\Migration\MigrationConfiguration;
use SwagMigrationAssistant\Migration\MigrationContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class LocalMediaProcessorTest extends TestCase
{
    /**
     * @var StaticEntityRepository<SwagMigrationMediaFileCollection>
     */
    private StaticEntityRepository $migrationMediaFileRepo;

    public function testCopyMediaFiles(): void
    {
        $runId = Uuid::randomHex();
        $fixtureDirectory = __DIR__ . '/_fixtures';

        $mediaFiles = [
            $this->createFileData($fixtureDirectory . '/test1.jpg', $runId),
            $this->createFileData($fixtureDirectory . '/test2.jpg', $runId),
        ];

        $mappedWorkload = [];
        foreach ($mediaFiles as &$media) {
            $media['uri'] = '/' . $media['file_name'];
            $mappedWorkload[$media['media_id']] = new MediaProcessWorkloadStruct(
                $media['media_id'],
                $runId,
                MediaProcessWorkloadStruct::IN_PROGRESS_STATE,
                ['path' => $media['path'], 'fileSize' => $media['file_size'], 'fileName' => $media['file_name']]
            );
        }
        unset($media);

        $connection = new SwagMigrationConnectionEntity();
        $connection->setCredentialFields(['installationRoot' => $fixtureDirectory]);

        $migrationContext = new MigrationContext(
            $connection,
            new Magento24Profile(),
            null,
            null,
            Uuid::randomHex(),
            0,
            100
        );

        Context::createDefaultContext();

        $mediaProcessor = $this->createLocaleMediaProcessor($mediaFiles);
        $reflectionMethod = (new \ReflectionClass(LocalMediaProcessor::class))->getMethod('copyMediaFiles');

        $result = $reflectionMethod->invokeArgs(
            $mediaProcessor,
            [$mediaFiles, $mappedWorkload, $migrationContext, Context::createDefaultContext()]
        );

        foreach ($result as $workload) {
            static::assertInstanceOf(MediaProcessWorkloadStruct::class, $workload);
            static::assertSame(MediaProcessWorkloadStruct::FINISH_STATE, $workload->getState());
        }
    }

    public function testMissingShopUrlMarksMediaAsFailed(): void
    {
        $runId = Uuid::randomHex();
        $mediaFile = $this->createFileData(__DIR__ . '/_fixtures/test1.jpg', $runId);
        $connection = new SwagMigrationConnectionEntity();
        $connection->setCredentialFields(['installationRoot' => '/path/does/not/exist']);
        $migrationContext = $this->createMigrationContext($connection, $runId);
        $workload = new MediaProcessWorkloadStruct($mediaFile['media_id'], $runId);

        $result = $this->createLocaleMediaProcessor([$mediaFile])->process(
            $migrationContext,
            Context::createDefaultContext(),
            [$workload]
        );

        static::assertSame(MediaProcessWorkloadStruct::ERROR_STATE, $result[0]->getState());
        static::assertSame(
            [[['id' => $mediaFile['id'], 'processFailure' => true]]],
            $this->migrationMediaFileRepo->updates
        );
    }

    public function testRejectedDownloadMarksMediaAsFailedAfterThreshold(): void
    {
        $runId = Uuid::randomHex();
        $mediaFile = $this->createFileData(__DIR__ . '/_fixtures/test1.jpg', $runId);
        $connection = new SwagMigrationConnectionEntity();
        $connection->setCredentialFields([
            'installationRoot' => '/path/does/not/exist',
            'shopUrl' => 'https://unreachable.invalid',
        ]);
        $migrationContext = $this->createMigrationContext($connection, $runId);
        $workload = new MediaProcessWorkloadStruct(
            $mediaFile['media_id'],
            $runId,
            errorCount: 3
        );
        $processor = $this->createLocaleMediaProcessor([$mediaFile]);
        $processor->rejectDownloads = true;

        $result = $processor->process($migrationContext, Context::createDefaultContext(), [$workload]);

        static::assertSame(MediaProcessWorkloadStruct::ERROR_STATE, $result[0]->getState());
        static::assertSame(
            [[['id' => $mediaFile['id'], 'processFailure' => true]]],
            $this->migrationMediaFileRepo->updates
        );
    }

    public function testSynchronousRequestExceptionCreatesRejectedPromise(): void
    {
        $client = new Client([
            'handler' => static function (): never {
                throw new \RuntimeException('unreachable');
            },
        ]);
        $workload = new MediaProcessWorkloadStruct(
            Uuid::randomHex(),
            Uuid::randomHex(),
            additionalData: ['uri' => 'https://unreachable.invalid/media.jpg', 'file_size' => 10]
        );
        $processor = $this->createLocaleMediaProcessor([]);

        $result = Promise\Utils::settle([$processor->request($workload, $client)])->wait();

        static::assertSame('rejected', $result[0]['state']);
        static::assertSame(0, $workload->getErrorCount());
    }

    /**
     * @param array<int, mixed> $mediaFiles
     */
    private function createLocaleMediaProcessor(array $mediaFiles, int $expectedPersistedFiles = 0): TestLocalMediaProcessor
    {
        $this->migrationMediaFileRepo = StaticEntityRepository::of(
            SwagMigrationMediaFileCollection::class,
            definition: new SwagMigrationMediaFileDefinition()
        );

        $mediaFileRepo = StaticEntityRepository::of(
            MediaCollection::class,
            definition: new MediaDefinition(),
        );

        $loggerMock = $this->createMock(LoggingServiceInterface::class);

        $requestedMediaIds = [];
        $databaseMediaFiles = $this->mediaIdsFromHexToBytes($mediaFiles);
        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->method('setParameter')->willReturnCallback(
            static function (string $key, mixed $value) use (&$requestedMediaIds, $queryBuilderMock): QueryBuilder {
                if ($key === 'ids') {
                    $requestedMediaIds = $value;
                }

                return $queryBuilderMock;
            }
        );
        $queryBuilderMock->method('fetchAllAssociative')->willReturnCallback(
            static function () use ($databaseMediaFiles, &$requestedMediaIds): array {
                return \array_values(\array_filter(
                    $databaseMediaFiles,
                    static fn (array $mediaFile): bool => \in_array($mediaFile['media_id'], $requestedMediaIds, true)
                ));
            }
        );

        $dbalConnectionMock = $this->createMock(Connection::class);
        $dbalConnectionMock->method('createQueryBuilder')->willReturn($queryBuilderMock);

        $fileSaverMock = $this->createMock(FileSaver::class);
        // TestCase: Check that the filename starts with "/tmp/", the file exists and the size is correct
        $fileSaverMock->expects($this->exactly($expectedPersistedFiles))
            ->method('persistFileToMedia')
            ->willReturnCallback(static function ($mediaFile, $destination, $mediaId): void {
                static::assertInstanceOf(MediaFile::class, $mediaFile);
                static::assertStringStartsWith('/tmp/', $mediaFile->getFileName());
                static::assertFileExists($mediaFile->getFileName());
                static::assertSame($mediaFile->getFileSize(), \filesize($mediaFile->getFileName()));
                static::assertSame('jpg', $mediaFile->getFileExtension());

                static::assertIsString($destination);
                static::assertStringStartsWith('test', $destination);

                static::assertIsString($mediaId);
                static::assertTrue(Uuid::isValid($mediaId));
            });

        return new TestLocalMediaProcessor(
            $this->migrationMediaFileRepo,
            $mediaFileRepo,
            $fileSaverMock,
            $loggerMock,
            $dbalConnectionMock,
            new MigrationConfiguration()
        );
    }

    private function createMigrationContext(
        SwagMigrationConnectionEntity $connection,
        string $runId,
    ): MigrationContext {
        return new MigrationContext(
            $connection,
            new Magento24Profile(),
            null,
            null,
            $runId,
            0,
            100
        );
    }

    /**
     * @return array{id: string, media_id: string, run_id:string, file_name: string, file_size: int, uri: string, path: string}
     */
    private function createFileData(string $filePath, string $runId): array
    {
        $fileSize = \filesize($filePath);
        static::assertIsInt($fileSize);
        static::assertNotEmpty($fileSize);

        $fileNameArray = \explode('/', $filePath);

        return [
            'id' => Uuid::randomHex(),
            'media_id' => Uuid::randomHex(),
            'run_id' => $runId,
            'file_name' => $fileNameArray[\array_key_last($fileNameArray)],
            'file_size' => $fileSize,
            'uri' => 'http://test.localhost/test1.jpg?random=' . \random_int(10000, 99999),
            'path' => $filePath,
        ];
    }

    /**
     * @param array<int, mixed> $mediaFiles
     *
     * @return array<int, mixed>
     */
    private function mediaIdsFromHexToBytes(array $mediaFiles): array
    {
        foreach ($mediaFiles as &$media) {
            $media['id'] = Uuid::fromHexToBytes($media['id']);
            $media['run_id'] = Uuid::fromHexToBytes($media['run_id']);
            $media['media_id'] = Uuid::fromHexToBytes($media['media_id']);
        }

        return $mediaFiles;
    }
}

<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Media;

use Doctrine\DBAL\Connection;
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
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class LocalMediaProcessorTest extends TestCase
{
    public function testCopyMediaFiles(): void
    {
        $runId = Uuid::randomHex();

        $mediaFiles = [
            $this->createFileData(__DIR__ . '/_fixtures/test1.jpg', $runId),
            $this->createFileData(__DIR__ . '/_fixtures/test2.jpg', $runId),
        ];

        $mappedWorkload = [];
        foreach ($mediaFiles as $media) {
            $mappedWorkload[$media['media_id']] = new MediaProcessWorkloadStruct(
                $media['media_id'],
                $runId,
                MediaProcessWorkloadStruct::IN_PROGRESS_STATE,
                ['path' => $media['path'], 'fileSize' => $media['file_size'], 'fileName' => $media['file_name']]
            );
        }

        $migrationContext = new MigrationContext(
            new SwagMigrationConnectionEntity(),
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
        $reflectionMethod->setAccessible(true);
        $result = $reflectionMethod->invokeArgs($mediaProcessor, [$mediaFiles, $mappedWorkload, $migrationContext, Context::createDefaultContext()]);

        foreach ($result as $workload) {
            static::assertInstanceOf(MediaProcessWorkloadStruct::class, $workload);
            static::assertSame(MediaProcessWorkloadStruct::FINISH_STATE, $workload->getState());
        }
    }

    /**
     * @param array<int, mixed> $mediaFiles
     */
    private function createLocaleMediaProcessor(array $mediaFiles): LocalMediaProcessor
    {
        /** @var StaticEntityRepository<SwagMigrationMediaFileCollection> $migrationMediaFileRepo */
        $migrationMediaFileRepo = new StaticEntityRepository(
            [],
            new SwagMigrationMediaFileDefinition()
        );

        /** @var StaticEntityRepository<MediaCollection> $mediaFileRepo */
        $mediaFileRepo = new StaticEntityRepository(
            [],
            new MediaDefinition(),
        );

        $loggerMock = $this->createMock(LoggingServiceInterface::class);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->method('fetchAllAssociative')->willReturn($this->mediaIdsFromHexToBytes($mediaFiles));

        $dbalConnectionMock = $this->createMock(Connection::class);
        $dbalConnectionMock->method('createQueryBuilder')->willReturn($queryBuilderMock);

        $fileSaverMock = $this->createMock(FileSaver::class);
        // TestCase: Check that the filename starts with "/tmp/", the file exists and the size is correct
        $fileSaverMock->expects($this->exactly(2))
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

        return new class($migrationMediaFileRepo, $mediaFileRepo, $fileSaverMock, $loggerMock, $dbalConnectionMock) extends LocalMediaProcessor {
            public function supports(MigrationContextInterface $migrationContext): bool
            {
                return true;
            }
        };
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

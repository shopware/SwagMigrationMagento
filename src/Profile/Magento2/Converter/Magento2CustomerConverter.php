<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Converter;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Profile\Magento\Converter\CustomerConverter;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Argon2Id13Encoder;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Md5Encoder;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Sha256Encoder;
use SwagMigrationAssistant\Migration\Logging\Log\Builder\SwagMigrationLogBuilder;
use SwagMigrationAssistant\Migration\Logging\Log\ExceptionRunLog;

#[Package('fundamentals@after-sales')]
abstract class Magento2CustomerConverter extends CustomerConverter
{
    private const PASSWORD_HASH_SPLIT_LIMIT = 3;

    protected function setPassword(array &$data, array &$converted): bool
    {
        [, , $version] = \explode(':', $data['password_hash'], self::PASSWORD_HASH_SPLIT_LIMIT);
        $converted['legacyPassword'] = $data['password_hash'];

        $converted['legacyEncoder'] = Magento2Sha256Encoder::NAME;
        if ($version === '0') {
            $converted['legacyEncoder'] = Magento2Md5Encoder::NAME;
        }

        if ($version === '2') {
            $converted['legacyEncoder'] = Magento2Argon2Id13Encoder::NAME;

            if (!\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13') || !\extension_loaded('sodium')) {
                $exception = new \Exception('Password algorithm is not available, please install and activate sodium php extension.');

                $this->loggingService->addLogEntry(
                    SwagMigrationLogBuilder::fromMigrationContext($this->migrationContext)
                        ->withEntityName(CustomerDefinition::ENTITY_NAME)
                        ->withFieldName('password_hash')
                        ->withFieldSourcePath('password_hash')
                        ->withSourceData($data)
                        ->withConvertedData($converted)
                        ->withExceptionMessage($exception->getMessage())
                        ->withExceptionTrace($exception->getTrace())
                        ->build(ExceptionRunLog::class)
                );

                return false;
            }
        }

        unset($data['password_hash']);

        return true;
    }
}

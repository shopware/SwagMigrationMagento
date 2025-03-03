<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Exception;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@after-sales')]
class MigrationMagentoException extends HttpException
{
    public const INCORRECT_TABLE_PREFIX = 'SWAG_MIGRATION_MAGENTO__INCORRECT_TABLE_PREFIX';

    public const MEDIA_PATH_NOT_REACHABLE = 'SWAG_MIGRATION_MAGENTO__MEDIA_PATH_NOT_REACHABLE';

    public const MEDIA_FILE_SIZE_ERROR = 'SWAG_MIGRATION_MAGENTO__MEDIA_FILE_SIZE_ERROR';

    public const MEDIA_MIME_TYPE_ERROR = 'SWAG_MIGRATION_MAGENTO__MEDIA_MIME_TYPE_ERROR';

    public static function incorrectTablePrefix(string $prefix): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INCORRECT_TABLE_PREFIX,
            \sprintf('The configured table prefix "%s" is incorrect.', $prefix)
        );
    }

    public static function mediaPathNotReachable(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_PATH_NOT_REACHABLE,
            \sprintf('The local media path %s is not reachable.', $path)
        );
    }

    public static function mediaFileSizeError(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_FILE_SIZE_ERROR,
            \sprintf('Could not determine size of file %s', $path)
        );
    }
}

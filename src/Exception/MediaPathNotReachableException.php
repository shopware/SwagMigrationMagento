<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MediaPathNotReachableException extends ShopwareHttpException
{
    public function __construct(string $path)
    {
        parent::__construct('The local media path ' . $path . ' is not reachable.');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'SWAG_MIGRATION_MAGENTO__MEDIA_PATH_NOT_REACHABLE';
    }
}

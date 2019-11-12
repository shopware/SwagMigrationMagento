<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidTablePrefixException extends ShopwareHttpException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'SWAG_MIGRATION_MAGENTO__INCORRECT_TABLE_PREFIX';
    }
}

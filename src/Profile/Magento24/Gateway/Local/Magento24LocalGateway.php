<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento24\Gateway\Local;

use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Magento2LocalGateway;
use Swag\MigrationMagento\Profile\Magento24\Magento24Profile;
use SwagMigrationAssistant\Migration\Profile\ProfileInterface;

#[Package('fundamentals@after-sales')]
class Magento24LocalGateway extends Magento2LocalGateway
{
    public function supports(ProfileInterface $profile): bool
    {
        return $profile instanceof Magento24Profile;
    }
}

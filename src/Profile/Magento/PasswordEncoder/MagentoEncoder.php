<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\PasswordEncoder;

use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;

class MagentoEncoder implements LegacyEncoderInterface
{
    public function getName(): string
    {
        return 'Magento19';
    }

    public function isPasswordValid(string $password, string $hash): bool
    {
        if (mb_strpos($hash, ':') === false) {
            return hash_equals($hash, md5($password));
        }
        [$md5, $salt] = explode(':', $hash);

        return hash_equals($md5, md5($salt . $password));
    }
}

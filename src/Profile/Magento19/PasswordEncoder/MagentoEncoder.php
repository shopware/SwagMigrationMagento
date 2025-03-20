<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento19\PasswordEncoder;

use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

#[Package('fundamentals@after-sales')]
class MagentoEncoder implements LegacyEncoderInterface
{
    public function getName(): string
    {
        return 'Magento19';
    }

    public function isPasswordValid(string $password, string $hash): bool
    {
        if (\mb_strpos($hash, ':') !== false) {
            [$hash, $salt] = \explode(':', $hash);
            $password = $salt . $password;
        }

        return \hash_equals($hash, Hasher::hash($password, 'md5'))
            || \hash_equals($hash, Hasher::hash($password, 'sha256'))
            || \hash_equals($hash, Hasher::hash($password, 'sha512'))
            || \password_verify($password, $hash);
    }
}

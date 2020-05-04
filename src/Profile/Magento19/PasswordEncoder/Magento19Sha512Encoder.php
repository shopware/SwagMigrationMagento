<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento19\PasswordEncoder;

class Magento19Sha512Encoder extends Magento19Encoder
{
    public function getName(): string
    {
        return 'Magento19Sha512';
    }

    public function getDisplayName(): string
    {
        return 'SHA-512';
    }

    public function isPasswordValid(string $password, string $hash): bool
    {
        if (mb_strpos($hash, ':') === false) {
            return hash_equals($hash, hash('sha512', $password));
        }
        [$hashString, $salt] = explode(':', $hash);

        return hash_equals($hashString, hash('sha512', $salt . $password));
    }
}

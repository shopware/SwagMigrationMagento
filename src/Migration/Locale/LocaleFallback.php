<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Locale;

use Shopware\Core\Framework\Log\Package;

/**
 * Builds a fallback chain for a locale code, from most to least specific, by dropping a
 * BCP-47 script subtag and then the region.
 *
 * Magento stores some locales with a script subtag (zh_Hans_CN, zh_Hant_TW, sr_Latn_RS). The
 * reader hyphenates them (zh-Hans-CN), but Shopware's locale table has no script-subtag rows
 * (it uses zh-CN, zh-TW). The chain lets the lookup fall back to a code that does exist.
 */
#[Package('fundamentals@after-sales')]
final class LocaleFallback
{
    private const ISO_15924_SUBTAG_LENGTH = 4;

    /**
     * "zh-Hans-CN" -> ["zh-Hans-CN", "zh-CN", "zh"]
     * "sr-Latn-RS" -> ["sr-Latn-RS", "sr-RS", "sr"]
     * "de-DE"      -> ["de-DE", "de"]
     * "en"         -> ["en"]
     *
     * @return string[]
     */
    public static function candidates(string $localeCode): array
    {
        $parts = \explode('-', $localeCode);
        $candidates = [$localeCode];

        if (\count($parts) === 3 && self::isScriptSubtag($parts[1])) {
            $candidates[] = $parts[0] . '-' . $parts[2];
        }

        if (\count($parts) > 1) {
            $candidates[] = $parts[0];
        }

        return \array_values(\array_unique($candidates));
    }

    /**
     * A locale's script subtag is an ISO 15924 code: always exactly four letters (Hans, Latn, ...).
     */
    private static function isScriptSubtag(string $subtag): bool
    {
        return \strlen($subtag) === self::ISO_15924_SUBTAG_LENGTH && \ctype_alpha($subtag);
    }
}

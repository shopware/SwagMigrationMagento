<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Migration\Locale;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Migration\Locale\LocaleFallback;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class LocaleFallbackTest extends TestCase
{
    /**
     * @param string[] $expected
     */
    #[DataProvider('candidatesProvider')]
    public function testCandidates(string $localeCode, array $expected): void
    {
        static::assertSame($expected, LocaleFallback::candidates($localeCode));
    }

    /**
     * @return array<string, array{0: string, 1: string[]}>
     */
    public static function candidatesProvider(): array
    {
        return [
            'simplified chinese drops script subtag' => ['zh-Hans-CN', ['zh-Hans-CN', 'zh-CN', 'zh']],
            'traditional chinese drops script subtag' => ['zh-Hant-TW', ['zh-Hant-TW', 'zh-TW', 'zh']],
            'serbian latin drops script subtag' => ['sr-Latn-RS', ['sr-Latn-RS', 'sr-RS', 'sr']],
            'plain region code keeps language base' => ['de-DE', ['de-DE', 'de']],
            'two part chinese without script' => ['zh-CN', ['zh-CN', 'zh']],
            'language only stays single' => ['en', ['en']],
        ];
    }
}

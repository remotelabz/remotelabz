<?php

namespace App\Tests\Service;

use App\Service\UploadLimit;
use PHPUnit\Framework\TestCase;

class UploadLimitTest extends TestCase
{
    /**
     * @dataProvider provideSizes
     */
    public function testFormat(int $bytes, string $expected): void
    {
        $this->assertSame($expected, UploadLimit::format($bytes));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function provideSizes(): array
    {
        return [
            'zero is unlimited' => [0, 'unlimited'],
            'negative is unlimited' => [-1, 'unlimited'],
            'bytes' => [512, '512 B'],
            'kilobytes' => [2048, '2 KB'],
            'one megabyte' => [1048576, '1 MB'],
            'two megabytes' => [2097152, '2 MB'],
            // Regression: the trailing-zero trimming used to eat the zeros of
            // the integer part, turning "100 MB" into "1 MB".
            'hundred megabytes' => [104857600, '100 MB'],
            'two hundred megabytes' => [209715200, '200 MB'],
            'one gigabyte' => [1073741824, '1 GB'],
            'five gigabytes' => [5368709120, '5 GB'],
            'fractional megabytes' => [(int) (1.5 * 1048576), '1.5 MB'],
            'integer fractional value' => [(int) (2.0 * 1048576), '2 MB'],
        ];
    }

    public function testMaxBytesIsTheSmallestPositivePhpLimit(): void
    {
        $upload = self::iniToBytes((string) ini_get('upload_max_filesize'));
        $post = self::iniToBytes((string) ini_get('post_max_size'));

        $positive = array_filter([$upload, $post], static fn (int $b): bool => $b > 0);
        $expected = empty($positive) ? 0 : min($positive);

        $this->assertSame($expected, UploadLimit::maxBytes());
    }

    private static function iniToBytes(string $size): int
    {
        $size = trim($size);
        if ('' === $size) {
            return 0;
        }

        $unit = strtolower(substr($size, -1));
        $bytes = (int) $size;

        switch ($unit) {
            case 'g':
                $bytes *= 1024;
                // no break
            case 'm':
                $bytes *= 1024;
                // no break
            case 'k':
                $bytes *= 1024;
        }

        return $bytes;
    }
}

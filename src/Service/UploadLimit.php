<?php

namespace App\Service;

/**
 * Effective maximum upload size allowed by PHP, and a human readable rendering
 * of it. Handy both to reject an oversized file server-side and to advertise the
 * limit to the front-end so it can pre-check before even sending the request
 * (a request bigger than `post_max_size` is silently emptied by PHP, which then
 * looks like an "empty" upload rather than an oversized one).
 */
class UploadLimit
{
    /**
     * The smallest of `upload_max_filesize` and `post_max_size`, in bytes.
     * A value of 0 means "no limit configured" (PHP semantics); in that case
     * only the non-zero limit, if any, is considered.
     */
    public static function maxBytes(): int
    {
        $limits = array_filter([
            self::convert((string) ini_get('upload_max_filesize')),
            self::convert((string) ini_get('post_max_size')),
        ], static fn (int $bytes): bool => $bytes > 0);

        return empty($limits) ? 0 : min($limits);
    }

    /**
     * Human readable form of a byte count, e.g. 2097152 => "2 MB".
     * Returns "unlimited" for a non-positive value.
     */
    public static function format(int $bytes): string
    {
        if ($bytes <= 0) {
            return 'unlimited';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < \count($units) - 1) {
            $value /= 1024;
            ++$unit;
        }

        $decimals = ($unit > 0 && $value < 10) ? 1 : 0;
        $formatted = number_format($value, $decimals, '.', '');

        // Strip the trailing zeros of the fractional part only ("2.0" => "2").
        // Doing it unconditionally would also eat the zeros of an integer part
        // ("100" => "1"), which is what turned "100 MB" into "1 MB".
        if ($decimals > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted . ' ' . $units[$unit];
    }

    /** Convert a PHP ini size shorthand ("2M", "512K", "1G", "1024") to bytes. */
    private static function convert(string $size): int
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

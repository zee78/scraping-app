<?php

namespace App\Helpers;

class ProductIdGenerator
{
    public static function generate(
        string $title,
        ?string $size,
        ?string $color,
        ?string $condition
    ): string {
        // Hard-coded mapping for now (safe & client-friendly)
        

        $parts = array_filter([
            self::clean($title),
            self::clean($size),
            $color,
            $condition,
            self::clean($size),
        ]);

        return implode('-', $parts);
    }

    protected static function clean(?string $value): ?string
    {
        if (!$value) return null;

        return strtoupper(
            preg_replace('/[^A-Z0-9]/', '', $value)
        );
    }
}

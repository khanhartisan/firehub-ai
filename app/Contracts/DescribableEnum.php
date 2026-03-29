<?php

namespace App\Contracts;

/**
 * Enums that can be turned into a human-readable description string.
 *
 * Used for labels in admin or logs (e.g. ScrapingStatus, ScrapableType).
 */
interface DescribableEnum
{
    /**
     * Return a human-readable description for the given enum case.
     *
     * @param  static  $enum  The enum instance
     * @return string  Description string
     */
    public static function describe(self $enum): string;
}
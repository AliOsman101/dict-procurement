<?php

namespace App\Helpers;

class NumberToWords
{
    /**
     * Convert a number to its word representation.
     *
     * @param string $locale The language locale (e.g., 'en' for English)
     * @param float|int $number The number to convert
     * @return string
     */
    public static function transformNumber(string $locale, $number): string
    {
        $number = (float) $number;
        $integerPart = (int) $number;
        $decimalPart = round(($number - $integerPart) * 100); // For cents, if any

        $words = self::numberToWords($integerPart);

        if ($decimalPart > 0) {
            $words .= ' and ' . self::numberToWords($decimalPart) . ' cent' . ($decimalPart > 1 ? 's' : '');
        }

        return ucfirst(trim($words));
    }

    /**
     * Convert an integer to words.
     *
     * @param int $number The integer to convert
     * @return string
     */
    private static function numberToWords(int $number): string
    {
        $units = [
            0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
            5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
            10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
            14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen',
            18 => 'eighteen', 19 => 'nineteen'
        ];

        $tens = [
            20 => 'twenty', 30 => 'thirty', 40 => 'forty', 50 => 'fifty',
            60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety'
        ];

        $scales = [
            1000000000 => 'billion',
            1000000 => 'million',
            1000 => 'thousand',
            100 => 'hundred'
        ];

        if ($number < 0) {
            return 'minus ' . self::numberToWords(abs($number));
        }

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            $tensValue = floor($number / 10) * 10;
            $unitValue = $number % 10;
            $result = $tens[$tensValue];
            if ($unitValue > 0) {
                $result .= '-' . $units[$unitValue];
            }
            return $result;
        }

        foreach ($scales as $scale => $scaleName) {
            if ($number >= $scale) {
                $quotient = floor($number / $scale);
                $remainder = $number % $scale;
                $result = self::numberToWords($quotient) . ' ' . $scaleName;
                if ($remainder > 0) {
                    $result .= ' ' . self::numberToWords($remainder);
                }
                return $result;
            }
        }

        return '';
    }

    /**
     * Add an ordinal suffix to a number (e.g., 1 -> 1st, 2 -> 2nd).
     *
     * @param int $number The number to convert
     * @return string
     */
    public static function ordinal(int $number): string
    {
        $suffixes = ['th', 'st', 'nd', 'rd'];
        $lastDigit = $number % 10;
        $lastTwoDigits = $number % 100;

        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
            return $number . 'th';
        }

        $suffix = ($lastDigit <= 3) ? $suffixes[$lastDigit] : 'th';
        return $number . $suffix;
    }
}
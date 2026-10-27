<?php
/**
 * YBT Management Software — Formatting Helpers
 * Currency, date, and number formatting with Indian number system support.
 */

/**
 * Format currency amount
 */
function formatCurrency(float $amount, ?string $symbol = null): string {
    $defaults = require YBT_ROOT . '/config/defaults.php';
    $regional = $defaults['regional'];
    $symbol = $symbol ?? $regional['currency_symbol'];

    if (($regional['number_system'] ?? 'indian') === 'indian') {
        return $symbol . formatIndianNumber($amount);
    }

    return $symbol . number_format($amount, 2, $regional['decimal_separator'], $regional['thousand_separator']);
}

/**
 * Format number in Indian numbering system (1,23,456.78)
 */
function formatIndianNumber(float $number, int $decimals = 2): string {
    $isNegative = $number < 0;
    $number = abs($number);

    $parts = explode('.', number_format($number, $decimals, '.', ''));
    $intPart = $parts[0];
    $decPart = $parts[1] ?? str_repeat('0', $decimals);

    // Indian grouping: last 3 digits, then groups of 2
    $len = strlen($intPart);
    if ($len <= 3) {
        $formatted = $intPart;
    } else {
        $lastThree = substr($intPart, -3);
        $remaining = substr($intPart, 0, $len - 3);
        $formatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining) . ',' . $lastThree;
    }

    $result = $formatted . ($decimals > 0 ? '.' . $decPart : '');
    return $isNegative ? '-' . $result : $result;
}

/**
 * Format date
 */
function formatDate(?string $date, ?string $format = null): string {
    if (!$date) return '—';
    $defaults = require YBT_ROOT . '/config/defaults.php';
    $format = $format ?? $defaults['regional']['date_format'];
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

/**
 * Format datetime
 */
function formatDateTime(?string $datetime, ?string $format = null): string {
    if (!$datetime) return '—';
    $defaults = require YBT_ROOT . '/config/defaults.php';
    $format = $format ?? $defaults['regional']['datetime_format'];
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '—';
}

/**
 * Format number
 */
function formatNumber(float $number, int $decimals = 0): string {
    $defaults = require YBT_ROOT . '/config/defaults.php';
    $regional = $defaults['regional'];

    if (($regional['number_system'] ?? 'indian') === 'indian') {
        return formatIndianNumber($number, $decimals);
    }

    return number_format($number, $decimals, $regional['decimal_separator'], $regional['thousand_separator']);
}

/**
 * Format percentage
 */
function formatPercent(float $value, int $decimals = 1): string {
    return number_format($value, $decimals) . '%';
}

/**
 * Convert amount to words (Indian system)
 */
function amountInWords(float $amount): string {
    if ($amount == 0) return 'Zero';

    $isNegative = $amount < 0;
    $amount = abs($amount);
    $intPart = (int)floor($amount);
    $decPart = (int)round(($amount - $intPart) * 100);

    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $numToWords = function(int $n) use ($ones, $tens, &$numToWords): string {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        return $ones[(int)($n / 100)] . ' Hundred' . ($n % 100 ? ' and ' . $numToWords($n % 100) : '');
    };

    $result = '';
    if ($intPart >= 10000000) {
        $result .= $numToWords((int)($intPart / 10000000)) . ' Crore ';
        $intPart %= 10000000;
    }
    if ($intPart >= 100000) {
        $result .= $numToWords((int)($intPart / 100000)) . ' Lakh ';
        $intPart %= 100000;
    }
    if ($intPart >= 1000) {
        $result .= $numToWords((int)($intPart / 1000)) . ' Thousand ';
        $intPart %= 1000;
    }
    if ($intPart > 0) {
        $result .= $numToWords($intPart);
    }

    $result = trim($result);
    if ($decPart > 0) {
        $result .= ' and ' . $numToWords($decPart) . ' Paise';
    } else {
        $result .= ' Only';
    }

    return ($isNegative ? 'Minus ' : '') . 'Rupees ' . $result;
}

/**
 * Format number to words alias
 */
function formatNumberToWords(float $amount): string {
    return amountInWords($amount);
}

/**
 * Format file size
 */
function formatFileSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

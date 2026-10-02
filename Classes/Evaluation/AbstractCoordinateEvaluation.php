<?php
namespace SICOR\SicOsm\Evaluation;

/**
 * Backend evaluation for coordinates in decimal degrees.
 *
 * Accepts dot or comma as decimal separator, rejects values outside ±LIMIT
 * and stores 7 decimals (≈ 1 cm). TCA type number/decimal is not usable here,
 * because it rounds to 2 decimals (≈ 1 km).
 */
abstract class AbstractCoordinateEvaluation
{
    protected const LIMIT = 0.0;

    /**
     * Server-side evaluation, called by DataHandler
     *
     * @param mixed $value
     * @param string $is_in
     * @param bool $set Set to false to reject the value
     */
    public function evaluateFieldValue($value, $is_in, &$set): string
    {
        $value = str_replace(',', '.', trim((string)$value));
        if (!is_numeric($value) || abs((float)$value) > static::LIMIT) {
            $set = false;
            return $value;
        }
        return number_format((float)$value, 7, '.', '');
    }

    /**
     * Value shown in the backend form: without trailing zeros of the decimal column.
     * 0 (= not set yet, e.g. new record waiting for geocoding) is shown as an empty field;
     * saving an empty field keeps the stored value (see evaluateFieldValue()).
     */
    public function deevaluateFieldValue(array $parameters): string
    {
        $value = (string)($parameters['value'] ?? '');
        if (is_numeric($value) && (float)$value === 0.0) {
            return '';
        }
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}

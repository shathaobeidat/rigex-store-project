<?php

declare(strict_types=1);

final class PaymentCurrency
{
    public const STRIPE_CURRENCY = 'usd';
    public const USD_CENTS_PER_JOD = 141;

    public static function toStripeMinorUnits(float $jodAmount): int
    {
        return (int) round($jodAmount * self::USD_CENTS_PER_JOD);
    }
}

<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Model;

/**
 * How many of the points asked for an order uses, and what they take off (in
 * the base currency): no more than the balance, nothing below the fewest
 * allowed, at most the share of the products' price the store allows. Points
 * are whole: a capped amount uses the points that cover it.
 */
class Redemption
{
    /**
     * @return array{points: int, base: float}
     */
    public static function calculate(
        int $requested,
        int $balance,
        int $minPoints,
        float $pointValue,
        float $eligibleBase,
        float $maxPercent
    ): array {
        $points = min($requested, $balance);
        if ($points <= 0 || $points < $minPoints || $pointValue <= 0 || $eligibleBase <= 0) {
            return ['points' => 0, 'base' => 0.0];
        }
        $maxBase = floor($eligibleBase * $maxPercent + 1e-6) / 100;
        $base = min(round($points * $pointValue, 2), $maxBase);
        if ($base <= 0) {
            return ['points' => 0, 'base' => 0.0];
        }

        return ['points' => min($points, (int) ceil($base / $pointValue - 1e-9)), 'base' => $base];
    }

    /**
     * Shares of an amount in proportion to weights, in cents; the last one
     * takes the rounding.
     *
     * @param array<array-key, float> $weights
     * @return array<array-key, float>
     */
    public static function split(float $amount, array $weights): array
    {
        $sum = array_sum($weights);
        $shares = [];
        if ($sum <= 0) {
            return array_map(static fn () => 0.0, $weights);
        }
        $left = $amount;
        $keys = array_keys($weights);
        $last = end($keys);
        foreach ($weights as $key => $weight) {
            $share = $key === $last ? round($left, 2) : round($amount * $weight / $sum, 2);
            $share = min($share, round($left, 2));
            $shares[$key] = $share;
            $left -= $share;
        }

        return $shares;
    }
}

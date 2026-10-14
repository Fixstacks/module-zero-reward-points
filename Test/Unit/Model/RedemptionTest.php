<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Zero\RewardPoints\Model\Redemption;

class RedemptionTest extends TestCase
{
    public function testUsesWhatIsAskedUpToTheBalance(): void
    {
        $this->assertSame(['points' => 500, 'base' => 5.0], Redemption::calculate(500, 2000, 100, 0.01, 58, 50));
        $this->assertSame(['points' => 2000, 'base' => 20.0], Redemption::calculate(PHP_INT_MAX, 2000, 100, 0.01, 58, 50));
    }

    public function testCappedByTheShareOfTheProducts(): void
    {
        // 50% of 58.01 = 29.00 (rounded down): 2900 points.
        $this->assertSame(['points' => 2900, 'base' => 29.0], Redemption::calculate(5000, 5000, 0, 0.01, 58.01, 50));
        // A point worth 0.03: 29.00 needs 967 points (966 would be 28.98).
        $this->assertSame(['points' => 967, 'base' => 29.0], Redemption::calculate(5000, 5000, 0, 0.03, 58, 50));
    }

    public function testNothingBelowTheFewestOrWithoutProducts(): void
    {
        $this->assertSame(['points' => 0, 'base' => 0.0], Redemption::calculate(99, 99, 100, 0.01, 58, 50));
        $this->assertSame(['points' => 0, 'base' => 0.0], Redemption::calculate(500, 500, 0, 0.01, 0, 50));
        $this->assertSame(['points' => 0, 'base' => 0.0], Redemption::calculate(0, 500, 0, 0.01, 58, 50));
    }

    public function testSplitInProportionTheLastTakesTheRounding(): void
    {
        $this->assertSame([11.72, 8.28], Redemption::split(20.0, [34.0, 24.0]));
        $shares = Redemption::split(10.0, [1.0, 1.0, 1.0]);
        $this->assertSame([3.33, 3.33, 3.34], $shares);
        $this->assertSame([0.0, 0.0], Redemption::split(5.0, [0.0, 0.0]));
    }
}

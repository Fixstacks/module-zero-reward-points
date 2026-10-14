<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Model;

/**
 * What a ledger row was, in words.
 */
class Actions
{
    public static function label(array $row): string
    {
        $order = (string) ($row['order_increment_id'] ?? '');

        return (string) match ((string) $row['action']) {
            Points::EARN_ORDER => __('Order #%1', $order),
            Points::SPEND_ORDER => __('Used on order #%1', $order),
            Points::RETURN_ORDER => __('Given back: order #%1', $order),
            Points::REVOKE_ORDER => __('Refund: order #%1', $order),
            Points::EARN_SIGNUP => __('Welcome bonus'),
            Points::EARN_REVIEW => __('Review'),
            Points::EARN_ADMIN, Points::TAKE_ADMIN => __('From the store'),
            Points::EXPIRE => __('Expired'),
            default => (string) $row['action'],
        };
    }
}

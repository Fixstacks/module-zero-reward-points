<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * A customer's points on a website: a ledger (zero_reward_transaction), the
 * balance its sum. Earnings keep what of them is unspent ("remaining");
 * spending, taking back and expiry use the soonest to expire first. Changes
 * lock the customer's rows, so two orders can't spend the same points.
 */
class Points
{
    public const EARN_ORDER = 'order';
    public const EARN_SIGNUP = 'signup';
    public const EARN_REVIEW = 'review';
    public const EARN_ADMIN = 'admin';
    public const RETURN_ORDER = 'order_return';
    public const SPEND_ORDER = 'spend';
    public const REVOKE_ORDER = 'order_revoke';
    public const TAKE_ADMIN = 'admin_take';
    public const EXPIRE = 'expire';

    private const TABLE = 'zero_reward_transaction';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config
    ) {
    }

    public function balance(int $customerId, int $websiteId): int
    {
        $connection = $this->resource->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), ['SUM(points)'])
                ->where('customer_id = ?', $customerId)
                ->where('website_id = ?', $websiteId)
        );
    }

    /**
     * Newest first.
     *
     * @return array<int, array>
     */
    public function history(int $customerId, int $websiteId, int $limit = 100): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE))
                ->where('customer_id = ?', $customerId)
                ->where('website_id = ?', $websiteId)
                ->order(['transaction_id DESC'])
                ->limit($limit)
        );
    }

    /**
     * Unspent points expiring within the days given: how many, and when the first do.
     *
     * @return array{points: int, first: ?string}
     */
    public function expiring(int $customerId, int $websiteId, int $days = 30): array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE), ['points' => 'SUM(remaining)', 'first' => 'MIN(expires_at)'])
                ->where('customer_id = ?', $customerId)
                ->where('website_id = ?', $websiteId)
                ->where('remaining > 0')
                ->where('expires_at IS NOT NULL')
                ->where('expires_at <= ?', gmdate('Y-m-d H:i:s', time() + $days * 86400))
        ) ?: [];

        return ['points' => (int) ($row['points'] ?? 0), 'first' => $row['first'] ?? null];
    }

    /**
     * Whether something was already recorded (an action for a reference).
     */
    public function has(string $action, string $reference, ?int $customerId = null): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['transaction_id'])
            ->where('action = ?', $action)
            ->where('reference = ?', $reference)
            ->limit(1);
        if ($customerId !== null) {
            $select->where('customer_id = ?', $customerId);
        }

        return (bool) $connection->fetchOne($select);
    }

    /**
     * Adds points (an earning: they can expire).
     */
    public function add(
        int $customerId,
        int $websiteId,
        int $points,
        string $action,
        ?string $orderIncrementId = null,
        ?string $reference = null,
        ?string $comment = null
    ): void {
        if ($points <= 0) {
            return;
        }
        $days = $this->config->expireDays($websiteId);
        $this->resource->getConnection()->insert($this->resource->getTableName(self::TABLE), [
            'customer_id' => $customerId,
            'website_id' => $websiteId,
            'points' => $points,
            'remaining' => $points,
            'action' => $action,
            'order_increment_id' => $orderIncrementId,
            'reference' => $reference,
            'comment' => $comment !== null ? mb_substr($comment, 0, 255) : null,
            'expires_at' => $days ? gmdate('Y-m-d H:i:s', time() + $days * 86400) : null,
        ]);
    }

    /**
     * Spends exactly these points.
     *
     * @throws LocalizedException When the balance is short
     */
    public function spend(
        int $customerId,
        int $websiteId,
        int $points,
        string $action,
        ?string $orderIncrementId = null,
        ?string $reference = null,
        ?string $comment = null
    ): void {
        if ($points <= 0) {
            return;
        }
        $this->change($customerId, $websiteId, function (int $balance) use ($points): int {
            if ($balance < $points) {
                throw new LocalizedException(__('You have %1 reward points, fewer than the %2 this order uses.', $balance, $points));
            }

            return $points;
        }, $action, $orderIncrementId, $reference, $comment);
    }

    /**
     * Takes up to these points (no more than the balance).
     *
     * @return int Points taken
     */
    public function take(
        int $customerId,
        int $websiteId,
        int $points,
        string $action,
        ?string $orderIncrementId = null,
        ?string $reference = null,
        ?string $comment = null
    ): int {
        if ($points <= 0) {
            return 0;
        }

        return $this->change(
            $customerId,
            $websiteId,
            static fn (int $balance): int => min($points, max(0, $balance)),
            $action,
            $orderIncrementId,
            $reference,
            $comment
        );
    }

    /**
     * Expires unspent points past their date.
     *
     * @return int Points expired
     */
    public function expire(): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $expired = 0;
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['transaction_id', 'customer_id', 'website_id', 'remaining'])
                ->where('remaining > 0')
                ->where('expires_at IS NOT NULL')
                ->where('expires_at <= ?', gmdate('Y-m-d H:i:s'))
        );
        foreach ($rows as $row) {
            $connection->beginTransaction();
            try {
                $remaining = (int) $connection->fetchOne(
                    $connection->select()->from($table, ['remaining'])
                        ->where('transaction_id = ?', (int) $row['transaction_id'])
                        ->forUpdate(true)
                );
                if ($remaining > 0) {
                    $connection->update($table, ['remaining' => 0], ['transaction_id = ?' => (int) $row['transaction_id']]);
                    $connection->insert($table, [
                        'customer_id' => (int) $row['customer_id'],
                        'website_id' => (int) $row['website_id'],
                        'points' => -$remaining,
                        'action' => self::EXPIRE,
                        'reference' => (string) $row['transaction_id'],
                    ]);
                    $expired += $remaining;
                }
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollBack();
                throw $e;
            }
        }

        return $expired;
    }

    /**
     * Customers with points on a website, most points first.
     *
     * @return array<int, array{customer_id: int, website_id: int, points: int, earned: int, updated: string}>
     */
    public function balances(?string $email = null, int $limit = 200): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['t' => $this->resource->getTableName(self::TABLE)], [
                'customer_id',
                'website_id',
                'points' => 'SUM(t.points)',
                'earned' => 'SUM(GREATEST(t.points, 0))',
                'updated' => 'MAX(t.created_at)',
            ])
            ->join(
                ['c' => $this->resource->getTableName('customer_entity')],
                'c.entity_id = t.customer_id',
                ['email', 'firstname', 'lastname']
            )
            ->group(['t.customer_id', 't.website_id'])
            ->order(['points DESC', 'updated DESC'])
            ->limit($limit);
        if ($email !== null && $email !== '') {
            $select->where('c.email LIKE ?', '%' . addcslashes($email, '%_\\') . '%');
        }

        return $connection->fetchAll($select);
    }

    /**
     * Locks the customer's ledger, works out how many points go (from the
     * balance), takes them from the earnings soonest to expire, records it.
     *
     * @param callable(int): int $howMany
     */
    private function change(
        int $customerId,
        int $websiteId,
        callable $howMany,
        string $action,
        ?string $orderIncrementId,
        ?string $reference,
        ?string $comment
    ): int {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->beginTransaction();
        try {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['transaction_id', 'points', 'remaining', 'expires_at'])
                    ->where('customer_id = ?', $customerId)
                    ->where('website_id = ?', $websiteId)
                    ->forUpdate(true)
            );
            $balance = array_sum(array_map(static fn ($row) => (int) $row['points'], $rows));
            $points = $howMany($balance);
            if ($points > 0) {
                $earnings = array_filter($rows, static fn ($row) => (int) $row['remaining'] > 0);
                usort($earnings, static fn ($a, $b) => [$a['expires_at'] === null, $a['expires_at'], (int) $a['transaction_id']]
                    <=> [$b['expires_at'] === null, $b['expires_at'], (int) $b['transaction_id']]);
                $left = $points;
                foreach ($earnings as $row) {
                    if ($left <= 0) {
                        break;
                    }
                    $use = min($left, (int) $row['remaining']);
                    $connection->update($table, ['remaining' => (int) $row['remaining'] - $use], ['transaction_id = ?' => (int) $row['transaction_id']]);
                    $left -= $use;
                }
                $connection->insert($table, [
                    'customer_id' => $customerId,
                    'website_id' => $websiteId,
                    'points' => -$points,
                    'action' => $action,
                    'order_increment_id' => $orderIncrementId,
                    'reference' => $reference,
                    'comment' => $comment !== null ? mb_substr($comment, 0, 255) : null,
                ]);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $points;
    }
}

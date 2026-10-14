<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\RewardPoints\Model\Actions;
use Zero\RewardPoints\Model\Points;

/**
 * Customers > Reward Points.
 */
class Admin implements ArgumentInterface
{
    public function __construct(
        private readonly Points $points,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url
    ) {
    }

    public function search(): string
    {
        return trim((string) $this->request->getParam('email'));
    }

    public function balances(): array
    {
        return $this->points->balances($this->search());
    }

    /**
     * One customer's changes, when the search is their exact email.
     *
     * @return array<int, array>
     */
    public function history(array $balance): array
    {
        return array_map(static fn (array $row) => $row + ['label' => Actions::label($row)], $this->points->history((int) $balance['customer_id'], (int) $balance['website_id'], 50));
    }

    /**
     * @return array<int, string>
     */
    public function websites(): array
    {
        $websites = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websites[(int) $website->getId()] = (string) $website->getName();
        }

        return $websites;
    }

    public function url(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }
}

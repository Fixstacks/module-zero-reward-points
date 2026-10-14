<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\ViewModel;

use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\RewardPoints\Model\Config;

/**
 * "Earn N points" by the price on a product page (the same for every
 * visitor: the page is cached).
 */
class Product implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{points: int, from: bool}|null
     */
    public function earn(): ?array
    {
        $product = $this->registry->registry('current_product');
        $store = $this->storeManager->getStore();
        $websiteId = (int) $store->getWebsiteId();
        if (!$product instanceof CatalogProduct || !$this->config->isEnabled($websiteId) || !$this->config->showOnProduct($websiteId)
            // A gift card (Zero_GiftCard) earns nothing: what it buys later does.
            || trim((string) $product->getData('zero_giftcard_amounts')) !== ''
            // Price on Request (Zero_QuoteRequest): the points would tell the price.
            || $product->getData('zero_quote_only')
        ) {
            return null;
        }
        // The price info's amounts are in the shown currency: back to the base one.
        $rate = (float) $store->getCurrentCurrencyRate() ?: 1.0;
        $price = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getBaseAmount() / $rate;
        $points = $this->config->pointsFor($price, $websiteId);

        return $points > 0 ? ['points' => $points, 'from' => $product->getTypeId() !== 'simple' && $product->getTypeId() !== 'virtual'] : null;
    }
}

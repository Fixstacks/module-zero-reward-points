<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zero\RewardPoints\Model\OrderPoints;

/**
 * A paid invoice earns points for what its products cost after discounts
 * (not shipping or tax). Gift cards bought earn nothing: what they buy later
 * does.
 */
class EarnOnInvoice implements ObserverInterface
{
    public function __construct(private readonly OrderPoints $orderPoints)
    {
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        $base = 0.0;
        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if (!$orderItem || $orderItem->getParentItem() || $this->isGiftCard($orderItem)) {
                continue;
            }
            $base += (float) $item->getBaseRowTotal() - abs((float) $item->getBaseDiscountAmount());
        }
        $this->orderPoints->earn($invoice->getOrder(), $base);
    }

    /**
     * A gift card (Zero_GiftCard) is bought with giftcard[...] in its request.
     */
    private function isGiftCard(\Magento\Sales\Model\Order\Item $item): bool
    {
        $request = $item->getProductOptionByCode('info_buyRequest');

        return is_array($request) && isset($request['giftcard']);
    }
}

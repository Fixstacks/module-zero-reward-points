<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * "Use my points" / "Don't use" in the checkout's order summary (HTMX POST;
 * form key checked by core's CSRF validation): sets the points the cart asks
 * to use (all it can, or none), collects the totals, answers with the box
 * and the totals (out of band), as the discount code does.
 */
class Apply implements HttpPostActionInterface
{
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getId() && $quote->getCustomerId()) {
            // All the balance: the totals take what the order allows.
            $quote->setData('zero_reward_points', $this->request->getParam('use') === '1' ? PHP_INT_MAX >> 32 : 0);
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->cartRepository->save($quote);
        }
        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement('zero.reward.checkout');
        $resultLayout->getLayout()->addOutputElement('zero.checkout.ordersummarytotals');

        return $resultLayout;
    }
}

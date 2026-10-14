<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Controller\Adminhtml\Points;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Zero\RewardPoints\Model\Points;

/**
 * Adds points to a customer (or takes them: a negative number) on a
 * website, with a note the customer sees.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_RewardPoints::points';

    public function __construct(
        Context $context,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Points $points
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $email = trim((string) $this->getRequest()->getParam('email'));
        $websiteId = (int) $this->getRequest()->getParam('website_id');
        $change = (int) $this->getRequest()->getParam('points');
        $comment = trim((string) $this->getRequest()->getParam('comment')) ?: null;
        try {
            if ($change === 0) {
                throw new LocalizedException(__('Enter the points to add, or a negative number to take.'));
            }
            try {
                $customer = $this->customerRepository->get($email, $websiteId);
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('No customer with the email %1 on that website.', $email));
            }
            $customerId = (int) $customer->getId();
            $websiteId = (int) $customer->getWebsiteId();
            if ($change > 0) {
                $this->points->add($customerId, $websiteId, $change, Points::EARN_ADMIN, null, null, $comment);
                $this->messageManager->addSuccessMessage(__('%1 points added to %2.', $change, $email));
            } else {
                $taken = $this->points->take($customerId, $websiteId, -$change, Points::TAKE_ADMIN, null, null, $comment);
                $this->messageManager->addSuccessMessage(__('%1 points taken from %2.', $taken, $email));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('zero_reward/points/index', ['email' => $email]);
    }
}

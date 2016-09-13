<?php
/**
 * Netresearch_OPS
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to
 * newer versions in the future.
 *
 * @copyright Copyright (c) 2015 Netresearch GmbH & Co. KG (http://www.netresearch.de/)
 * @license   Open Software License (OSL 3.0)
 * @link      http://opensource.org/licenses/osl-3.0.php
 */

/**
 * Capture.php
 *
 * @category Payment
 * @package  Netresearch_OPS
 * @author   Paul Siedler <paul.siedler@netresearch.de>
 */
?>
<?php

class Netresearch_OPS_Model_Response_Type_Capture extends Netresearch_OPS_Model_Response_Type_Abstract
{
    /**
     * Handles the specific actions for the concrete payment status
     */
    protected function _handleResponse()
    {
        if (!Netresearch_OPS_Model_Status::isCapture($this->getStatus())) {
            throw new Mage_Core_Exception(Mage::helper('ops')->__('%s is not a capture status!', $this->getStatus()));
        }

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $this->getMethodInstance()->getInfoInstance();
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();


        /**
         * Basically we have to check the following things here:
         *
         * Order state      - payment_review suggests an already existing intermediate status
         *                  - pending_payment or new suggests no feedback yet
         *
         * payment status   - intermediate and not failed -> move to payment review or add another comment
         *                  - intermediate and failed -> if recoverable let the order open and place comment
         *                  - finished - finish invoice dependent on order state
         */

        if (Netresearch_OPS_Model_Status::isIntermediate($this->getStatus())) {

            $message = $this->getIntermediateStatusComment();
            $payment->setIsTransactionPending(true);
            if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
                || $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING
            ) {
                // transaction was placed on PSP, initial feedback to shop or partial capture case

                $payment->setPreparedMessage($message);
                if ($this->getShouldRegisterFeedback()) {
                    $payment->registerCaptureNotification($this->getAmount());
                }

            } elseif ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
                // payment was pending and is still pending
                $payment->setIsTransactionApproved(false);
                $payment->setIsTransactionDenied(false);
                $payment->setPreparedMessage($message);

                if ($this->getShouldRegisterFeedback()) {
                    $payment->setNotificationResult(true);
                    $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, false);
                }

            }
        } else {
            // final status, means 9 or 95
            $message = $this->getFinalStatusComment();
            if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
                $payment->setNotificationResult(true);
                $payment->setPreparedMessage($message);
                if ($this->getShouldRegisterFeedback()) {
                    $payment->setNotificationResult(true);
                    $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_ACCEPT, false);
                }
            } else {
                $payment->setPreparedMessage($message);
                if ($this->getShouldRegisterFeedback()) {
                    $payment->registerCaptureNotification($this->getAmount());
                }
            }
        }

        if ($this->getShouldRegisterFeedback()) {
            $payment->save();
            $order->save();

            // gateway payments do not send confirmation emails by default
            Mage::helper('ops/data')->sendTransactionalEmail($order);
            
            $invoice = Mage::getModel('sales/order_invoice')->load($this->getTransactionId(), 'transaction_id');
            if($invoice->getId()){
                Mage::helper('ops')->sendTransactionalEmail($invoice);
            }
        }
    }
}

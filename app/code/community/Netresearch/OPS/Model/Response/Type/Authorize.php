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
 * Authorize.php
 *
 * @category Payment
 * @package  Netresearch_OPS
 * @author   Paul Siedler <paul.siedler@netresearch.de>
 */
?>
<?php

class Netresearch_OPS_Model_Response_Type_Authorize extends Netresearch_OPS_Model_Response_Type_Abstract
{
    /**
     * Handles the specific actions for the concrete payment status
     */
    protected function _handleResponse()
    {
        if (!Netresearch_OPS_Model_Status::isAuthorize($this->getStatus())) {
            throw new Mage_Core_Exception(Mage::helper('ops')->__('%s is not a authorize status!', $this->getStatus()));
        }


        /** @var Mage_Sales_Model_Order $order */
        $order = $this->getMethodInstance()->getInfoInstance()->getOrder();
        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $this->getMethodInstance()->getInfoInstance();

        // if no parent transaction id has been set yet set the parentTransactionId so we can void
        if (!$payment->getParentTransactionId()) {
            $payment->setParentTransactionId($this->getPayid());
        }

        if (Netresearch_OPS_Model_Status::isFinal($this->getStatus())) {
            // handle authorization declined
            // thrown exception gets catched by core and order will not been created
            if ($this->getStatus() == Netresearch_OPS_Model_Status::AUTHORISATION_DECLINED
            ) {
                if (!$this->getShouldRegisterFeedback()) {
                    Mage::throwException(
                        Mage::helper('ops')->__(
                            'Payment failed because the authorization was declined! Please choose another payment method.'
                        )
                    );
                } elseif ($payment->getAdditionalInformation('status')
                    == Netresearch_OPS_Model_Status::WAITING_FOR_IDENTIFICATION
                    && $order->getState() === Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
                ) {
                    try {
                        // if the payment was previously in payment review/has status 46 the identification obviously failed and the order gets canceled
                        $payment->setNotificationResult(true);
                        $payment->registerPaymentReviewAction(
                            Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY, false
                        );
                    } catch (Mage_Core_Exception $e) {
                        /**
                         * in CE 1.7 Mage_Sales_Model_Order::registerCancellation will always fail with exception if the
                         * order is in payment_review state. we therefore cancel the order 'manually'.
                         *
                         * below code is c&p from Mage_Sales_Model_Order::registerCancellation:
                         *
                         * @see Mage_Sales_Model_Order::registerCancellation
                         */
                        if ($e->getMessage() === Mage::helper('sales')->__('Order does not allow to be canceled.')) {
                            $cancelState = Mage_Sales_Model_Order::STATE_CANCELED;
                            foreach ($order->getAllItems() as $item) {
                                if ($cancelState != Mage_Sales_Model_Order::STATE_PROCESSING
                                    && $item->getQtyToRefund()
                                ) {
                                    if ($item->getQtyToShip() > $item->getQtyToCancel()) {
                                        $cancelState = Mage_Sales_Model_Order::STATE_PROCESSING;
                                    } else {
                                        $cancelState = Mage_Sales_Model_Order::STATE_COMPLETE;
                                    }
                                }
                                $item->cancel();
                            }

                            $order->setSubtotalCanceled($order->getSubtotal() - $order->getSubtotalInvoiced());
                            $order->setBaseSubtotalCanceled(
                                $order->getBaseSubtotal() - $order->getBaseSubtotalInvoiced()
                            );

                            $order->setTaxCanceled($order->getTaxAmount() - $order->getTaxInvoiced());
                            $order->setBaseTaxCanceled($order->getBaseTaxAmount() - $order->getBaseTaxInvoiced());

                            $order->setShippingCanceled($order->getShippingAmount() - $order->getShippingInvoiced());
                            $order->setBaseShippingCanceled(
                                $order->getBaseShippingAmount() - $order->getBaseShippingInvoiced()
                            );

                            $order->setDiscountCanceled(
                                abs($order->getDiscountAmount()) - $order->getDiscountInvoiced()
                            );
                            $order->setBaseDiscountCanceled(
                                abs($order->getBaseDiscountAmount()) - $order->getBaseDiscountInvoiced()
                            );

                            $order->setTotalCanceled($order->getGrandTotal() - $order->getTotalPaid());
                            $order->setBaseTotalCanceled($order->getBaseGrandTotal() - $order->getBaseTotalPaid());

                            $order->setState($cancelState, true, $this->getFinalStatusComment());
                        }
                    }
                }
            } elseif ($this->getStatus() == Netresearch_OPS_Model_Status::CANCELED_BY_CUSTOMER) {
                $order->registerCancellation($this->getFinalStatusComment());
            }

            if ($this->getShouldRegisterFeedback()) {
                if ($order->getState() === Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {

                    $action = Mage_Sales_Model_Order_Payment::REVIEW_ACTION_ACCEPT;
                    $targetState = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
                    if (Netresearch_OPS_Model_Status::canResendPaymentInfo($this->getStatus())
                        && Mage::helper('ops/payment')->isInlinePayment($payment)
                    ) {
                        $targetState = Mage_Sales_Model_Order::STATE_CANCELED;
                        $action = Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY;
                    }
                    $payment->setNotificationResult(true);
                    $payment->registerPaymentReviewAction($action, false);
                    if ($order->getState() != $targetState) {
                        $order->setState($targetState, true, $this->getFinalStatusComment());
                    }
                } elseif ($order->getState() === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT
                    || $order->getState() === Mage_Sales_Model_Order::STATE_NEW
                ) {
                    $payment->registerAuthorizationNotification($this->getAmount());
                    $order->setState(
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, $this->getFinalStatusComment()
                    );

                }
            } else {
                $this->addFinalStatusComment();
            }
        } else {
            $payment->setIsTransactionPending(true);
            if ($this->getStatus() == Netresearch_OPS_Model_Status::STAND_BY_SUSPECTED_FRAUD) {
                $payment->setIsFraudDetected(true);
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                    Mage_Sales_Model_Order::STATUS_FRAUD,
                    $this->getFraudStatusComment(
                        Mage::helper('ops')->__('Please have a look in PayEngine backend for more information.')
                    )
                );
            } else {
                $order->addStatusHistoryComment($this->getIntermediateStatusComment());
            }
            if ($this->getShouldRegisterFeedback()) {
                $payment->registerAuthorizationNotification($this->getAmount());
            }
        }

        if ($this->getShouldRegisterFeedback()) {
            $payment->save();
            $order->save();

            // gateway payments do not send confirmation emails by default
            if ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED){
                Mage::helper('ops/data')->sendTransactionalEmail($order);
            }
        }
    }
}

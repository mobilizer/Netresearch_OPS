<?php

/**
 * Netresearch_OPS_PaymentController
 *
 * @package
 * @copyright 2011 Netresearch
 * @author    Thomas Kappel <thomas.kappel@netresearch.de>
 * @author    André Herrn <andre.herrn@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_PaymentController extends Netresearch_OPS_Controller_Abstract
{

    /**
     * Load place from layout to make POST on ops
     */
    public function placeformAction()
    {


        $lastIncrementId = $this->_getCheckout()->getLastRealOrderId();

        if ($lastIncrementId) {
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($lastIncrementId);
        }

        $this->_getCheckout()->getQuote()->setIsActive(false)->save();
        $this->_getCheckout()->setOPSQuoteId($this->_getCheckout()->getQuoteId());
        $this->_getCheckout()->setOPSLastSuccessQuoteId($this->_getCheckout()->getLastSuccessQuoteId());
        $this->_getCheckout()->clear();


        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Render 3DSecure response HTML_ANSWER
     */
    public function placeform3dsecureAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Display our pay page, need to ops payment with external pay page mode     *
     */
    public function paypageAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * when payment gateway accept the payment, it will land to here
     * need to change order status as processed ops
     * update transaction id
     *
     */
    public function acceptAction()
    {
        $redirect = '';
        try {
            $order = $this->_getOrder();
            if($this->getQuote()){
                $this->getQuote()->setIsActive(false)->save();
            }
            $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
            $this->_getCheckout()->setLastQuoteId($order->getQuoteId());
            $this->_getCheckout()->setLastOrderId($order->getId());
        } catch (Exception $e) {
            /** @var Netresearch_OPS_Helper_Data $helper */
            $helper = Mage::helper('ops');
            $helper->log($helper->__("Exception in acceptAction: " . $e->getMessage()));
            $this->getPaymentHelper()->refillCart($this->_getOrder());
            $redirect = 'checkout/cart';
        }
        if ($redirect === '') {
            $redirect = 'checkout/onepage/success';
        }

        $this->redirectOpsRequest($redirect);
    }

    /**
     * the payment result is uncertain
     * exception status can be 52 or 92
     * need to change order status as processing ops
     * update transaction id
     *
     */
    public function exceptionAction()
    {
        $order = $this->_getOrder();
        $this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
        $this->_getCheckout()->setLastQuoteId($order->getQuoteId());
        $this->_getCheckout()->setLastOrderId($order->getId());

        $this->redirectOpsRequest('checkout/onepage/success');
    }

    /**
     * when payment got decline
     * need to change order status to cancelled
     * take the user back to shopping cart
     *
     */
    public function declineAction()
    {
        try {
            $this->_getCheckout()->setQuoteId($this->_getCheckout()->getOPSQuoteId());
        } catch (Exception $e) {

        }

        $this->getPaymentHelper()->refillCart($this->_getOrder());

        $message = Mage::helper('ops')->__(
            'Your payment information was declined. Please select another payment method.'
        );
        Mage::getSingleton('core/session')->addNotice($message);
        $redirect = 'checkout/onepage';
        $this->redirectOpsRequest($redirect);
    }

    /**
     * when user cancel the payment
     * change order status to cancelled
     * need to redirect user to shopping cart
     *
     * @return Netresearch_OPS_ApiController
     */
    public function cancelAction()
    {
        try {
            $this->_getCheckout()->setQuoteId($this->_getCheckout()->getOPSQuoteId());
        } catch (Exception $e) {

        }
        if (false == $this->_getOrder()->getId()) {
            $this->_order = null;
            $this->_getOrder($this->_getCheckout()->getLastQuoteId());
        }

        $this->getPaymentHelper()->refillCart($this->_getOrder());

        $redirect = 'checkout/cart';
        $this->redirectOpsRequest($redirect);

    }

    /**
     * when user cancel the payment and press on button "Back to Catalog" or "Back to Merchant Shop" in Orops
     *
     * @return Netresearch_OPS_ApiController
     */
    public function continueAction()
    {
        $order = Mage::getModel('sales/order')->load(
            $this->_getCheckout()->getLastOrderId()
        );
        $this->getPaymentHelper()->refillCart($order);
        $redirect = $this->getRequest()->getParam('redirect');
        if ($redirect == 'catalog'): //In Case of "Back to Catalog" Button in OPS
            $redirect = '/';
        else: //In Case of Cancel Auto-Redirect or "Back to Merchant Shop" Button
            $redirect = 'checkout/cart';
        endif;
        $this->redirectOpsRequest($redirect);
    }

    /*
     * Check the validation of the request from OPS
     */

    protected function checkRequestValidity()
    {
        if (!$this->_validateOPSData()) {
            throw new Exception("Hash is not valid");
        }
    }

    public function registerDirectDebitPaymentAction()
    {
        $params = $this->getRequest()->getParams();
        $validator = Mage::getModel('ops/validator_payment_directDebit');
        if (false === $validator->isValid($params)) {
            $this->getResponse()
                ->setHttpResponseCode(406)
                ->setBody($this->__(implode(PHP_EOL, $validator->getMessages())))
                ->sendHeaders();

            return;
        }
        $payment = $this->_getCheckout()->getQuote()->getPayment();
        $helper = Mage::helper('ops/directDebit');
        $payment = $helper->setDirectDebitDataToPayment($payment, $params);

        $payment->save();

        $this->getResponse()->sendHeaders();
    }


    public function saveCcBrandAction()
    {
        $brand = $this->_request->getParam('brand');
        $alias = $this->_request->getParam('alias');
        $payment = $this->getQuote()->getPayment();
        $payment->setAdditionalInformation('CC_BRAND', $brand);
        $payment->setAdditionalInformation('alias', $alias);
        $payment->setDataChanges(true);
        $payment->save();
        Mage::helper('ops')->log('saved cc brand ' . $brand . ' for quote #' . $this->getQuote()->getId());
        $this->getResponse()->sendHeaders();
    }

    /**
     * Action to retry paying the order on Ingenico
     *
     */
    public function retryAction()
    {

        $order = $this->_getOrder();
        $payment = $order->getPayment();
        $message = false;

        if ($this->_validateOPSData() === false) {
            $message = Mage::helper('ops')->__('Hash not valid');

        } else {

            if (is_array($payment->getAdditionalInformation())
                && array_key_exists('status', $payment->getAdditionalInformation())
                && Mage::helper('ops/payment')->isPaymentFailed($payment->getAdditionalInformation('status'))
            ) {

                $this->loadLayout();
                $this->renderLayout();

            } else {
                $message = Mage::helper('ops')->__(
                    'Not possible to reenter the payment details for order %s', $order->getIncrementId()
                );
            }
        }
        if ($message) {
            Mage::getSingleton('core/session')->addNotice($message);
            $this->redirectOpsRequest('/');
        }
    }

    protected function wasIframeRequest()
    {
        return $this->getConfig()->getConfigData('template', $this->_getOrder()->getStoreId())
        === Netresearch_OPS_Model_Payment_Abstract::TEMPLATE_OPS_IFRAME;
    }

    /**
     * Generates the Javascript snippet that move the redirect to the parent frame in iframe mode.
     *
     * @param $redirect
     *
     * @return string javascript snippet
     */
    protected function generateJavaScript($redirect)
    {
        $javascript
            = "
        <script type=\"text/javascript\">
            window.top.location.href = '" . Mage::getUrl($redirect) . "'
        </script>";

        return $javascript;
    }


    /**
     * Redirects the customer to the given redirect path or inserts the js-snippet needed for iframe template mode into
     * the response instead
     *
     * @param $redirect
     */
    protected function redirectOpsRequest($redirect)
    {
        if ($this->wasIframeRequest()) {
            $this->getResponse()->setBody($this->generateJavaScript($redirect));
        } else {
            $this->_redirect($redirect);
        }
    }
}

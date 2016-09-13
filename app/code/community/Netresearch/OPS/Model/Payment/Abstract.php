<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**PAYMENT_PROCESSING
 * OPS payment method model
 */
class Netresearch_OPS_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{

    protected $pm = '';
    protected $brand = '';

    protected $_code = 'ops';
    protected $_formBlockType = 'ops/form';
    protected $_infoBlockType = 'ops/info';
    protected $_config = null;
    protected $requestHelper = null;
    protected $backendOperationParameterModel = null;

    /**
     * @param null $backendOperationParameterModel
     */
    public function setBackendOperationParameterModel($backendOperationParameterModel)
    {
        $this->backendOperationParameterModel = $backendOperationParameterModel;
    }

    /**
     * @return Netresearch_OPS_Model_Backend_Operation_Parameter
     */
    public function getBackendOperationParameterModel()
    {
        if (null === $this->backendOperationParameterModel) {
            $this->backendOperationParameterModel = Mage::getModel('ops/backend_operation_parameter');
        }

        return $this->backendOperationParameterModel;
    }

    /**
     * Magento Payment Behaviour Settings
     */
    protected $_isGateway = false;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = true;
    protected $_canManageRecurringProfiles = false;

    /**
     * OPS behaviour settings
     */

    protected $_needsCartDataForRequest = false;

    protected $_needsShipToParams = true;

    protected $_needsBillToParams = false;

    /**
     * OPS template modes
     */
    const TEMPLATE_OPS_REDIRECT              = 'ops';
    const TEMPLATE_OPS_IFRAME                = 'ops_iframe';
    const TEMPLATE_OPS_TEMPLATE              = 'ops_template';
    const TEMPLATE_MAGENTO_INTERNAL          = 'magento';


    /**
     * redirect references
     */

    const REFERENCE_QUOTE_ID = 'quoteId';
    const REFERENCE_ORDER_ID = 'orderId';

    /**
     * Layout of the payment method
     */
    const PMLIST_HORIZONTAL_LEFT = 0;
    const PMLIST_HORIZONTAL = 1;
    const PMLIST_VERTICAL = 2;

    /**
     * OPS payment action constant
     */
    const OPS_AUTHORIZE_ACTION = 'RES';
    const OPS_AUTHORIZE_CAPTURE_ACTION = 'SAL';
    const OPS_CAPTURE_FULL = 'SAS';
    const OPS_CAPTURE_PARTIAL = 'SAL';
    const OPS_CAPTURE_DIRECTDEBIT_NL = 'VEN';
    const OPS_DELETE_AUTHORIZE = 'DEL';
    const OPS_DELETE_AUTHORIZE_AND_CLOSE = 'DES';
    const OPS_REFUND_FULL = 'RFS';
    const OPS_REFUND_PARTIAL = 'RFD';

    /**
     * 3D-Secure
     */
    const OPS_DIRECTLINK_WIN3DS = 'MAINW';

    /**
     * Module Transaction Type Codes
     */
    const OPS_CAPTURE_TRANSACTION_TYPE = 'capture';
    const OPS_VOID_TRANSACTION_TYPE = 'void';
    const OPS_REFUND_TRANSACTION_TYPE = 'refund';
    const OPS_DELETE_TRANSACTION_TYPE = 'delete';
    const OPS_AUTHORIZE_TRANSACTION_TYPE = 'authorize';

    /**
     * Session key for device fingerprinting consent
     */
    const FINGERPRINT_CONSENT_SESSION_KEY = 'device_fingerprinting_consent';

    /**
     * Return OPS Config
     *
     * @return Netresearch_OPS_Model_Config
     */
    public function getConfig()
    {
        if (is_null($this->_config)) {
            $this->_config = Mage::getSingleton('ops/config');
        }

        return $this->_config;
    }

    public function getConfigPaymentAction()
    {
        return $this->getPaymentAction();
    }

    /**
     * return if shipment params are needed for request
     *
     * @return bool
     */
    public function getNeedsShipToParams()
    {
        return $this->_needsShipToParams;
    }

    /**
     * return if billing params are needed for request
     *
     * @return bool
     */
    public function getNeedsBillToParams()
    {
        return $this->_needsBillToParams;
    }

    /**
     * returns the request helper
     *
     * @return Netresearch_OPS_Helper_Payment_Request
     */
    public function getRequestHelper()
    {
        if (null === $this->requestHelper) {
            $this->requestHelper = Mage::helper('ops/payment_request');
        }

        return $this->requestHelper;
    }

    /**
     * if payment method is available
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return boolean
     */
    public function isAvailable($quote = null)
    {

        $storeId = 0;
        // allow multi store/site for backend orders with disabled backend payment methods in default store
        if (!is_null($quote) && !is_null($quote->getId())) {
            $storeId = $quote->getStoreId();
        }
        if (Mage_Core_Model_App::ADMIN_STORE_ID == Mage::app()->getStore()->getId()
            && false == $this->isEnabledForBackend($storeId)
        ) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * if method is enabled for backend payments
     *
     * @return bool
     */
    public function isEnabledForBackend($storeId = 0)
    {
        return $this->getConfig()->isEnabledForBackend($this->_code, $storeId);
    }

    /**
     * Redirect url to ops submit form
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->getConfig()->getPaymentRedirectUrl();
    }

    protected function getPayment()
    {
        $checkout = Mage::getSingleton('checkout/session');
        $payment = $checkout->getQuote()->getPayment();
        if (!$payment->getId()) {
            $payment = Mage::getModel('sales/order')->loadByIncrementId($checkout->getLastRealOrderId())->getPayment();
        }
    }

    public function getOpsBrand($payment = null)
    {
        if (is_null($payment)) {
            $payment = $this->getInfoInstance();
        }
        $brand = trim($payment->getAdditionalInformation('BRAND'));
        if (!strlen($brand)) {
            $brand = $this->brand;
        }

        return $brand;
    }

    public function getOpsCode($payment = null)
    {
        if (is_null($payment)) {
            $payment = $this->getInfoInstance();
        }
        $pm = trim($payment->getAdditionalInformation('PM'));
        if (!strlen($pm)) {
            $pm = $this->pm;
        }

        return $pm;
    }

    /**
     * Return payment_action value from config area
     *
     * @return string
     */
    public function getPaymentAction()
    {
        return $this->getConfig()->getPaymentAction($this->getStoreId());
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param string[]|null          $requestParams
     *
     * @return string[]
     */
    public function getMethodDependendFormFields($order, $requestParams = null)
    {
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        if (null === $shippingAddress || false === $shippingAddress) {
            $shippingAddress = $billingAddress;
        }
        $payment = $order->getPayment()->getMethodInstance();
        $quote = Mage::helper('ops/order')->getQuote($order->getQuoteId());

        $formFields = array();
        $formFields['ORIG'] = Mage::helper("ops")->getModuleVersionString();
        $formFields['BRAND'] = $payment->getOpsBrand($order->getPayment());
        if ($this->getConfig()->canSubmitExtraParameter($order->getStoreId())) {
            $formFields['CN'] = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();
            $formFields['COM'] = $this->_getOrderDescription($order);
            $formFields['ADDMATCH'] = Mage::helper('ops/order')->checkIfAddressesAreSame($order);
            $ownerParams = $this->getRequestHelper()->getOwnerParams($billingAddress, $quote);
            $formFields['ECOM_BILLTO_POSTAL_POSTALCODE'] = $billingAddress->getPostcode();
            $formFields = array_merge($formFields, $ownerParams);
        }

        if (Mage::helper('customer/data')->isLoggedIn()) {
            $formFields['CUID'] = Mage::helper('customer')->getCustomer()->getId();
        }

        return $formFields;
    }

    /**
     * return ship to params if needed otherwise false
     *
     * @param $shippingAddress
     *
     * @return array|bool
     */
    public function getShipToParams($shippingAddress)
    {
        $shipToParams = false;
        if ($this->getNeedsShipToParams()
            && $this->getConfig()->canSubmitExtraParameter()
            && $shippingAddress
        ) {
            $shipToParams = $this->getRequestHelper()->extractShipToParameters($shippingAddress);
        }

        return $shipToParams;
    }

    /**
     * return ship to params if needed otherwise false
     *
     * @param $billingAddress
     *
     * @return array|bool
     */
    public function getBillToParams($billingAddress)
    {
        $billToParams = false;
        if ($this->getNeedsBillToParams()
            && $this->getConfig()->canSubmitExtraParameter()
            && $billingAddress
        ) {
            $billToParams = $this->getRequestHelper()->extractBillToParameters($billingAddress);
        }

        return $billToParams;
    }

    /**
     * Prepare params array to send it to gateway page via POST
     *
     * @param Mage_Sales_Model_Order
     * @param array
     *
     * @return array
     */
    public function getFormFields($order, $requestParams, $isRequest = true)
    {
        $requestHelper = Mage::helper('ops/payment_request');

        if (empty($order)) {
            if (!($order = $this->getOrder())) {
                return array();
            }
        }

        // get mandatory parameters
        $formFields = $this->getMandatoryFormFields($order);

        // get method specific parameters
        $methodDependendFields = $this->getMethodDependendFormFields($order, $requestParams);
        if (is_array($methodDependendFields)) {
            $formFields = array_merge($formFields, $methodDependendFields);
        }


        $paymentAction = $this->_getOPSPaymentOperation();
        if ($paymentAction ) {
            $formFields['OPERATION'] = $paymentAction;
        }

        $formFields = array_merge($formFields, $requestHelper->getTemplateParams($order->getStoreId()));

        $formFields['ACCEPTURL']        = $this->getConfig()->getAcceptUrl();
        $formFields['DECLINEURL']       = $this->getConfig()->getDeclineUrl();
        $formFields['EXCEPTIONURL']     = $this->getConfig()->getExceptionUrl();
        $formFields['CANCELURL']        = $this->getConfig()->getCancelUrl();
        $formFields['BACKURL']          = $this->getConfig()->getCancelUrl();


        /** @var  $order Mage_Sales_Model_Order */
        $shipToFormFields = $this->getShipToParams($order->getShippingAddress());
        if (is_array($shipToFormFields)) {
            $formFields = array_merge($formFields, $shipToFormFields);
        }

        $billToFormFields = $this->getBillToParams($order->getBillingAddress());
        if (is_array($billToFormFields)) {
            $formFields = array_merge($formFields, $billToFormFields);
        }

        $cartDataFormFields = $this->getOrderItemData($order);

        if (is_array($cartDataFormFields)) {
            $formFields = array_merge($formFields, $cartDataFormFields);
        }

        // get method specific parameters
        $methodDependendFields = $this->getMethodDependendFormFields($order, $requestParams);
        if (is_array($methodDependendFields)) {
            $formFields = array_merge($formFields, $methodDependendFields);
        }

        $shaSign = Mage::helper('ops/payment')->shaCrypt(
            Mage::helper('ops/payment')
                ->getSHASign($formFields, null, $order->getStoreId())
        );

        if ($isRequest) {
            $helper = Mage::helper('ops');
            $helper->log(
                $helper->__(
                    "Register Order %s in PayEngine \n\nAll form fields: %s\nPayEngine String to hash: %s\nHash: %s",
                    $order->getIncrementId(),
                    serialize($formFields),
                    Mage::helper('ops/payment')->getSHASign($formFields, null, $order->getStoreId()),
                    $shaSign
                )
            );
        }

        $formFields['SHASIGN'] = $shaSign;

        return $formFields;
    }

    /**
     * Get OPS Payment Action value
     *
     * @param string
     *
     * @return string
     */
    protected function _getOPSPaymentOperation()
    {
        $value = $this->getPaymentAction();
        if ($value == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
            $value = self::OPS_AUTHORIZE_ACTION;
        } elseif ($value == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
            $value = self::OPS_AUTHORIZE_CAPTURE_ACTION;
        }

        return $value;
    }

    protected function convertToLatin1($StringToConvert)
    {
        $returnString = '';
        $chars = str_split($StringToConvert);
        foreach ($chars as $char) {
            if (31 < ord($char) && ord($char) < 127) {
                $returnString .= $char;
            }
        }

        return $returnString;
    }

    /**
     * get formated order description
     *
     * @param Mage_Sales_Model_Order
     *
     * @return string
     */
    public function _getOrderDescription($order)
    {
        $descriptionItems = array();
        $description = '';
        $lengs = 0;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            // we know that PayEngine is not able to handle characters that are not available in iso-8859-1
            //            $descriptionItems[] = mb_ereg_replace("[^a-zA-Z0-9äáàéèíóöõúüûÄÁÀÉÍÓÖÕÚÜÛ_ ]" , "" , $item->getName());
            $descriptionItems[] = $this->convertToLatin1($item->getName());
            $description = Mage::helper('core/string')->substr(implode(', ', $descriptionItems), 0, 100);
            //COM field is limited to 100 chars max
            if (100 <= Mage::helper('core/string')->strlen($description)) {
                break;
            }
        }

        return $description;
    }

    /**
     * Get Main OPS Helper
     *
     * @return Netresearch_OPS_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('ops/data');
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
            ->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);

        if (!$paymentAction) {
            $paymentAction = $this->getConfigPaymentAction();
        }

        $message = $this->getHelper()
            ->__('Customer got redirected to PayEngine for %s. Awaiting feedback.', $paymentAction);

        /** @var Mage_Sales_Model_Order $order */
        $order = $this->getInfoInstance()->getOrder();

        $order->addStatusHistoryComment($message);

        return $this;
    }

    /**
     * accept payment
     *
     * @see \Mage_Sales_Model_Order_Payment::registerPaymentReviewAction
     *
     * @param Mage_Payment_Model_Info $payment
     *
     * @return boolean
     * @throws Mage_Core_Exception
     */
    public function acceptPayment(Mage_Payment_Model_Info $payment)
    {
        parent::acceptPayment($payment);
        $status = $payment->getAdditionalInformation('status');

        if ($status == Netresearch_OPS_Model_Status::AUTHORIZED || $status == Netresearch_OPS_Model_Status::PAYMENT_REQUESTED) {
            return true;
        }

        Mage::throwException(
            $this->getHelper()->__(
                'The order can not be accepted via Magento. For the actual status of the payment check the PayEngine backend.'
            )
        );
    }

    /**
     * cancel order if in payment review state
     *
     * @see \Mage_Sales_Model_Order_Payment::registerPaymentReviewAction
     *
     * @param Mage_Payment_Model_Info $payment
     *
     * @return boolean
     * @throws Mage_Core_Exception
     */
    public function denyPayment(Mage_Payment_Model_Info $payment)
    {
        parent::denyPayment($payment);

        Mage::getSingleton('admin/session')->addNotice(
            $this->getHelper()->__('Order has been canceled permanently in Magento. Changes in PayEngine platform will no longer be considered.')
        );

        return true;
    }

    /**
     * check if payment is in payment review state
     *
     * @param Mage_Payment_Model_Info $payment
     *
     * @return bool
     */
    public function canReviewPayment(Mage_Payment_Model_Info $payment)
    {
        $status = $payment->getAdditionalInformation('status');
        return Netresearch_OPS_Model_Status::canResendPaymentInfo($status);
    }

    /**
     * Determines if a capture will be processed
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @throws Mage_Core_Exception
     * @return \Mage_Payment_Model_Abstract|void
     */
    public function capture(Varien_Object $payment, $amount)
    {
        // disallow PayEngine online capture if amount is zero
        if ($amount < 0.01) {
            return parent::capture($payment, $amount);
        }

        if (true === Mage::registry('ops_auto_capture')) {
            Mage::unregister('ops_auto_capture');

            return parent::capture($payment, $amount);
        }

        $orderId = $payment->getOrder()->getId();
        $arrInfo = Mage::helper('ops/order_capture')->prepareOperation($payment, $amount);
        $storeId = $payment->getOrder()->getStoreId();

        if ($this->isRedirectNoticed($orderId)) {
            return $this;
        }
        try {
            $requestParams = $this->getBackendOperationParameterModel()->getParameterFor(
                self::OPS_CAPTURE_TRANSACTION_TYPE,
                $this,
                $payment,
                $amount,
                $arrInfo
            );
            $response = Mage::getSingleton('ops/api_directlink')->performRequest(
                $requestParams,
                Mage::getModel('ops/config')->getDirectLinkGatewayPath($storeId),
                $storeId
            );

            Mage::getModel('ops/response_handler')->processResponse($response, $this, false);

            return $this;

        } catch (Exception $e) {
            Mage::getModel('ops/status_update')->updateStatusFor($payment->getOrder());
            Mage::helper('ops')->log("Exception in capture request:" . $e->getMessage());
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Refund
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return \Mage_Payment_Model_Abstract|void
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $refundHelper = Mage::helper('ops/order_refund');

        if ($refundHelper->getOpenRefundTransaction($payment)->getId()) {
            Mage::throwException($this->getHelper()->__("There is already one creditmemo in the queue. The Creditmemo will be created automatically as soon as PayEngine sends an acknowledgement."));
        }

        $refundHelper->setAmount($amount)->setPayment($payment);
        $arrInfo = $refundHelper->prepareOperation($payment, $amount);
        $storeId = $payment->getOrder()->getStoreId();

        try {
            $requestParams = $this->getBackendOperationParameterModel()->getParameterFor(
                self::OPS_REFUND_TRANSACTION_TYPE,
                $this,
                $payment,
                $amount,
                $arrInfo
            );
            $response = Mage::getSingleton('ops/api_directlink')->performRequest(
                $requestParams,
                Mage::getModel('ops/config')->getDirectLinkGatewayPath($storeId),
                $storeId
            );
            Mage::getModel('ops/response_handler')->processResponse($response, $this, false);
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getModel('ops/status_update')->updateStatusFor($payment->getOrder());
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    /**
     * Returns the mandatory fields for requests to PayEngine
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */
    public function getMandatoryFormFields($order)
    {
        $formFields = Mage::helper('ops/payment_request')->getMandatoryRequestFields($order);
        $paymentAction = $this->_getOPSPaymentOperation();
        if ($paymentAction) {
            $formFields['OPERATION'] = $paymentAction;
        }

        return $formFields;
    }

    /**
     * determines if the close transaction parameter is set in the credit memo data
     *
     * @param array $creditMemoData
     *
     * @return bool
     */
    protected function getCloseTransactionFromCreditMemoData($creditMemoData)
    {
        $closeTransaction = false;
        if (array_key_exists('ops_close_transaction', $creditMemoData)
            && strtolower(trim($creditMemoData['ops_close_transaction'])) == 'on'
        ) {
            $closeTransaction = true;
        }

        return $closeTransaction;
    }

    /**
     * Custom cancel behavior, deny cancel and force custom to use void instead
     *
     * @param Varien_Object $payment
     *
     * @return void
     * @throws Mage_Core_Exception
     */
    public function cancel(Varien_Object $payment)
    {
        /*
         * Important: If an order was voided successfully and the user clicks on cancel in order-view
         * this method is not triggered anymore
         */

        //Proceed parent cancel method in case that regirstry value ops_auto_void is set
        if (true === Mage::registry('ops_auto_void')):
            Mage::unregister('ops_auto_void');

            return parent::cancel($payment);
        endif;

        //If order has state 'pending_payment' and the payment has PayEngine-status 0 or null (unknown) then cancel the order
        if (true === $this->canCancelManually($payment->getOrder())) {
            $payment->getOrder()->addStatusHistoryComment(
                $this->getHelper()->__("The order was cancelled manually. The PayEngine-state is 0 or null.")
            );

            return parent::cancel($payment);
        }

        //Abort cancel method by throwing a Mage_Core_Exception
        throw new Mage_Core_Exception($this->getHelper()->__('Please use void to cancel the operation.'));
    }

    /**
     * Custom void behavior, trigger PayEngine cancel request
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return void
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment)
    {

        $status = $payment->getAdditionalInformation('status');

        if(!Netresearch_OPS_Model_Status::canVoidTransaction($status)){
            Mage::throwException($this->getHelper()->__('Status %s can not be voided.', $status));
        }

        //Set initital params
        $orderID = $payment->getOrder()->getId();
        $order = Mage::getModel("sales/order")->load($orderID);

        //Calculate amount which has to be captured
        $alreadyCaptured = $payment->getBaseAmountPaidOnline();

        $grandTotal = Mage::helper('ops/payment')
            ->getBaseGrandTotalFromSalesObject($order);
        $voidAmount = $grandTotal - $alreadyCaptured;
        $storeId = $order->getStoreId();
        //Build void directLink-Request-Params
        $requestParams = array(
            'AMOUNT'    => $this->getHelper()->getAmount($voidAmount),
            'PAYID'     => $payment->getAdditionalInformation('paymentId'),
            'OPERATION' => self::OPS_DELETE_AUTHORIZE,
            'CURRENCY'  => Mage::app()->getStore($storeId)->getBaseCurrencyCode()
        );

        //Check if there is already a waiting void transaction, if yes: redirect to order view
        if (Mage::helper('ops/directlink')->checkExistingTransact(
            self::OPS_VOID_TRANSACTION_TYPE, $orderID
        )
        ) {
            $this->getHelper()->getAdminSession()->addError(
                $this->getHelper()->__(
                    'You already sent a void request. Please wait until the void request will be acknowledged.'
                )
            );

            return;
        }

        try {
            //perform ops cancel request
            $response = Mage::getSingleton('ops/api_directlink')
                ->performRequest(
                    $requestParams,
                    Mage::getModel('ops/config')->getDirectLinkGatewayPath($storeId),
                    $order->getStoreId()
                );

            if ($response['STATUS'] == Netresearch_OPS_Model_Status::INVALID_INCOMPLETE
                && $payment->getAdditionalInformation('status') == Netresearch_OPS_Model_Status::PAYMENT_REQUESTED
            ) {
                Mage::throwException(
                    $this->getHelper()->__('Order can no longer be voided. You have to refund the order online.')
                );
            }


            $handler = Mage::getModel('ops/response_handler');
            $handler->processResponse($response, $this, false);


            return $this;

        } catch (Exception $e) {
            Mage::getModel('ops/status_update')->updateStatusFor($payment->getOrder());
            Mage::helper('ops')->log(
                "Exception in void request:" . $e->getMessage()
            );
            throw new Mage_Core_Exception($e->getMessage());
        }
    }

    /**
     * get question for fields with disputable value
     * users are asked to correct the values before redirect to PayEngine
     *
     * @param Mage_Sales_Model_Order $order         Current order
     * @param array                  $requestParams Request parameters
     *
     * @return string
     */
    public function getQuestion($order, $requestParams)
    {
        return '';
    }

    /**
     * get an array of fields with disputable value
     * users are asked to correct the values before redirect to PayEngine
     *
     * @param Mage_Sales_Model_Order $order         Current order
     * @param array                  $requestParams Request parameters
     *
     * @return array
     */
    public function getQuestionedFormFields($quote, $requestParams)
    {
        return array();
    }

    /**
     * if we need some missing form params
     * users are asked to correct the values before redirect to PayEngine
     *
     * @param Mage_Sales_Model_Order $order
     * @param array                  $requestParams Parameters sent in current request
     * @param array                  $formFields    Parameters to be sent to PayEngine
     *
     * @return bool
     */
    public function hasFormMissingParams($order, $requestParams, $formFields = null)
    {
        if (false == is_array($requestParams)) {
            $requestParams = array();
        }
        if (is_null($formFields)) {
            $formFields = $this->getFormFields($order, $requestParams, false);
        }
        $availableParams = array_merge($requestParams, $formFields);
        $requiredParams = $this->getQuestionedFormFields($order, $requestParams);
        foreach ($requiredParams as $requiredParam) {
            if (false == array_key_exists($requiredParam, $availableParams)
                || 0 == strlen($availableParams[$requiredParam])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if order can be cancelled manually
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    public function canCancelManually($order)
    {
        $payment = $order->getPayment();

        //If payment has PayEngine-status 0 or null (unknown) or another offline cancelable status
        $status = $payment->getAdditionalInformation('status');

        return is_null($status)
        || in_array(
            $status, array(
                Netresearch_OPS_Model_Status::INVALID_INCOMPLETE,
                Netresearch_OPS_Model_Status::CANCELED_BY_CUSTOMER,
                Netresearch_OPS_Model_Status::AUTHORISATION_DECLINED,
                Netresearch_OPS_Model_Status::PAYMENT_DELETED
            )
        );
    }

    public function getOpsHtmlAnswer($payment = null)
    {
        $returnValue = '';
        if (is_null($payment)) {
            $quoteId = Mage::getSingleton('checkout/session')->getQuote()->getId();
            if (is_null($quoteId)) {
                $orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
                $order = Mage::getModel('sales/order')->loadByAttribute('increment_id', $orderIncrementId);
            } else {
                $order = Mage::getModel('sales/order')->loadByAttribute('quote_id', $quoteId);
            }
            if ($order instanceof Mage_Sales_Model_Order && 0 < $order->getId()) {
                $payment = $order->getPayment();
                $returnValue = $payment->getAdditionalInformation('HTML_ANSWER');
            }
        } elseif ($payment instanceof Mage_Payment_Model_Info) {
            $returnValue = $payment->getAdditionalInformation('HTML_ANSWER');
        }

        return $returnValue;
    }

    public function getShippingTaxRate($order)
    {
        return $this->getRequestHelper()->getShippingTaxRate($order);
    }

    protected function isRedirectNoticed($orderId)
    {
        if (Mage::helper('ops/directlink')->checkExistingTransact(self::OPS_CAPTURE_TRANSACTION_TYPE, $orderId)) {
            $this->getHelper()->redirectNoticed(
                $orderId,
                $this->getHelper()->__(
                    'You already sent a capture request. Please wait until the capture request is acknowledged.'
                )
            );

            return true;
        }
        if (Mage::helper('ops/directlink')->checkExistingTransact(self::OPS_VOID_TRANSACTION_TYPE, $orderId)) {
            $this->getHelper()->redirectNoticed(
                $orderId,
                $this->getHelper()->__(
                    'There is one void request waiting. Please wait until this request is acknowledged.'
                )
            );

            return true;
        }

        return false;
    }

    public function setConfig(Netresearch_OPS_Model_Config $config)
    {
        $this->_config = $config;
    }

    /**
     * If cart Item information has to be transmitted to PayEngine
     *
     * @return bool
     */

    public function needsOrderItemDataForRequest()
    {
        return $this->_needsCartDataForRequest;
    }

    /**
     * Returns array with the order item data formatted in PayEngine fashion if payment method implementation
     * needs the data otherwise just returns false.
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array|false
     */
    public function getOrderItemData(Mage_Sales_Model_Order $order)
    {
        if (!$this->needsOrderItemDataForRequest()) {
            return false;
        }

        return $this->getRequestHelper()->extractOrderItemParameters($order);
    }

    /**
     * @inheritDoc
     */
    public function canVoid(Varien_Object $payment)
    {

        if(Netresearch_OPS_Model_Status::canVoidTransaction($payment->getAdditionalInformation('status'))){
            return parent::canVoid($payment);
        } else {
            return false;
        }

    }

    /**
     * @inheritDoc
     */
    public function assignData($data)
    {
        parent::assignData($data);

        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo->getData($this->getCode())) {
            foreach ($paymentInfo->getData($this->getCode()) as $key => $value) {
                $paymentInfo->setAdditionalInformation($key, $value);
            }
        }

        return $this;
    }


    /**
     * @inheritDoc
     */
    public function validate()
    {

        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $billingAddress = $paymentInfo->getOrder()->getBillingAddress();
            $shippingAddress = $paymentInfo->getOrder()->getShippingAddress();
            $salesObject = $paymentInfo->getOrder();
        } else {
            $salesObject = $paymentInfo->getQuote();
            $billingAddress = $salesObject->getBillingAddress();
            $shippingAddress = $salesObject->getShippingAddress();
        }

        $validator = Mage::getModel('ops/validator_parameter_factory')->getValidatorFor(
            Netresearch_OPS_Model_Validator_Parameter_Factory::TYPE_REQUEST_PARAMS_VALIDATION
        );

        $params = $this->getRequestHelper()->getOwnerParams($billingAddress, $salesObject);
        $billingParams = $this->getBillToParams($billingAddress);
        $shippingParams = $this->getShipToParams($shippingAddress);
        if($shippingParams) {
            $params = array_merge($params, $shippingParams);
        }
        if($billingParams){
            $params = array_merge($params, $billingParams);
        }

        if(false === $validator->isValid($params)){
            $result = Mage::helper('ops/validation_result')->getValidationFailedResult(
                $validator->getMessages(), $salesObject
            );
            throw new Mage_Payment_Exception('Validation failed', $result['fields']);
        }

        return parent::validate();
    }


}

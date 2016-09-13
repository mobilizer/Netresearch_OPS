<?php

/**
 * Netresearch_OPS_Block_Form_OpsId
 *
 * @package   OPS
 * @copyright 2012 Netresearch App Factory AG <http://www.netresearch.de>
 * @author    Thomas Birke <thomas.birke@netresearch.de>
 * @license   OSL 3.0
 */
class Netresearch_OPS_Block_Form_Cc extends Netresearch_OPS_Block_Form
{

    private $aliasDataForCustomer = array();

    /**
     * CC Payment Template
     */
    const FRONTEND_TEMPLATE = 'ops/form/cc.phtml';

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate(self::FRONTEND_TEMPLATE);
    }


    /**
     * gets all Alias CC brands
     *
     * @return array
     */
    public function getAliasBrands()
    {
        return Mage::getModel('ops/source_cc_aliasInterfaceEnabledTypes')
            ->getAliasInterfaceCompatibleTypes();
    }

    /**
     * @param null $storeId
     * @param bool $admin
     *
     * @return string
     */
    public function getAliasAcceptUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getAliasAcceptUrl($storeId, $admin);
    }

    /**
     * @param null $storeId
     * @param bool $admin
     *
     * @return string
     */
    public function getAliasExceptionUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getAliasExceptionUrl($storeId, $admin);
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getAliasGatewayUrl($storeId = null)
    {
        return Mage::getModel('ops/config')->getAliasGatewayUrl($storeId);
    }

    /**
     * @return string
     */
    public function getSaveCcBrandUrl()
    {
        return Mage::getModel('ops/config')->getSaveCcBrandUrl();
    }

    /**
     * @param null $storeId
     * @param bool $admin
     *
     * @return mixed
     */
    public function getCcSaveAliasUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getCcSaveAliasUrl($storeId, $admin);
    }

    /**
     * checks if the 'alias' payment method (!) is available
     * no check for customer has aliases here
     * just a passthrough of the isAvailable of Netresearch_OPS_Model_Payment_Abstract::isAvailable
     *
     * @return boolean
     */
    public function isAliasPMEnabled()
    {
        return Mage::getModel('ops/config')->isAliasManagerEnabled($this->getMethodCode());
    }


    /**
     * retrieves the alias data for the logged in customer
     *
     * @return array | null - array the alias data or null if the customer
     * is not logged in
     */
    protected function getStoredAliasForCustomer()
    {
        if (Mage::helper('customer/data')->isLoggedIn()
            && Mage::getModel('ops/config')->isAliasManagerEnabled($this->getMethodCode())
        ) {
            $quote = $this->getQuote();
            $aliases = Mage::helper('ops/alias')->getAliasesForAddresses(
                $quote->getCustomer()->getId(), $quote->getBillingAddress(),
                $quote->getShippingAddress(), $quote->getStoreId()
            )
                ->addFieldToFilter('state', Netresearch_OPS_Model_Alias_State::ACTIVE)
                ->addFieldToFilter('payment_method', $this->getMethodCode())
                ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_DESC);


            foreach ($aliases as $key => $alias) {
                $this->aliasDataForCustomer[$key] = $alias;
            }
        }

        return $this->aliasDataForCustomer;
    }


    /**
     * retrieves single values to given keys from the alias data
     *
     * @param $aliasId
     * @param $key - string the key for the alias data
     *
     * @return null|string - null if key is not set in the alias data, otherwise
     * the value for the given key from the alias data
     */
    protected function getStoredAliasDataForCustomer($aliasId, $key)
    {
        $returnValue = null;
        $aliasData = array();

        if (empty($this->aliasDataForCustomer)) {
            $aliasData = $this->getStoredAliasForCustomer();
        } else {
            $aliasData = $this->aliasDataForCustomer;
        }

        if (array_key_exists($aliasId, $aliasData) && $aliasData[$aliasId]->hasData($key)) {
            $returnValue = $aliasData[$aliasId]->getData($key);
        }

        return $returnValue;
    }

    /**
     * retrieves the given path (month or year) from stored expiration date
     *
     * @param $key - the requested path
     *
     * @return null | string the extracted part of the date
     */
    public function getExpirationDatePart($aliasId, $key)
    {
        $returnValue = null;
        $expirationDate = $this->getStoredAliasDataForCustomer($aliasId, 'expiration_date');
        // set expiration date to actual date if no stored Alias is used
        if ($expirationDate === null) {
            $expirationDate = date('my');
        }

        if (0 < strlen(trim($expirationDate))
        ) {
            $expirationDateValues = str_split($expirationDate, 2);

            if ($key == 'month') {
                $returnValue = $expirationDateValues[0];
            }
            if ($key == 'year') {
                $returnValue = $expirationDateValues[1];
            }

            if ($key == 'complete') {
                $returnValue = implode('/', $expirationDateValues);
            }
        }

        return $returnValue;

    }

    /**
     * retrieves the masked alias card number and formats it in a card specific format
     *
     * @return null|string - null if no alias data were found,
     * otherwise the formatted card number
     */
    public function getAliasCardNumber($aliasId)
    {
        $aliasCardNumber = $this->getStoredAliasDataForCustomer($aliasId, 'pseudo_account_or_cc_no');
        if (0 < strlen(trim($aliasCardNumber))) {
            $aliasCardNumber = Mage::helper('ops/alias')->formatAliasCardNo(
                $this->getStoredAliasDataForCustomer($aliasId, 'brand'), $aliasCardNumber
            );
        }

        return $aliasCardNumber;
    }

    /**
     * @return null|string - the card holder either from alias data or
     * the name from the the user who is logged in, null otherwise
     */
    public function getCardHolderName($aliasId)
    {
        $cardHolderName = $this->getStoredAliasDataForCustomer($aliasId, 'card_holder');
        $customerHelper = Mage::helper('customer/data');
        if ((is_null($cardHolderName) || 0 === strlen(trim($cardHolderName)))
            && $customerHelper->isLoggedIn()
            && Mage::getModel('ops/config')->isAliasManagerEnabled($this->getMethodCode())
        ) {
            $cardHolderName = $customerHelper->getCustomerName();
        }

        return $cardHolderName;
    }

    /**
     * the brand of the stored card data
     *
     * @return null|string - string if stored card data were found, null otherwise
     */
    public function getStoredAliasBrand($aliasId)
    {
        $storedBrand = $this->getStoredAliasDataForCustomer($aliasId, 'brand');
        $methodCode = $this->getMethodCode();
        if (in_array($storedBrand, Mage::getModel('ops/config')->getInlinePaymentCcTypes($methodCode))) {
            return $storedBrand;
        }

        return '';
    }

    /**
     * determines whether the alias hint is shown to guests or not
     *
     * @return bool true if alias feature is enabled and display the hint to
     * guests is enabled
     */
    public function isAliasInfoBlockEnabled()
    {
        return ($this->isAliasPMEnabled()
            && Mage::getModel('ops/config')->isAliasInfoBlockEnabled());
    }

    /**
     * @return string[]
     */
    public function getCcBrands()
    {
        return explode(',', $this->getConfig()->getAcceptedCcTypes($this->getMethodCode()));
    }

}

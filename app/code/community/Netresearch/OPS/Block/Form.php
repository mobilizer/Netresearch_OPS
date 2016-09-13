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

class Netresearch_OPS_Block_Form extends Mage_Payment_Block_Form_Cc
{

    protected $pmLogo = null;

    protected $fieldMapping = array();

    protected $config = null;

    /**
     * Frontend Payment Template
     */
    const FRONTEND_TEMPLATE = 'ops/form.phtml';

    /**
     * Init OPS payment form
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate(self::FRONTEND_TEMPLATE);
    }

    /**
     * get OPS config
     *
     * @return Netresearch_Ops_Model_Config
     */
    public function getConfig()
    {
        if (null === $this->config) {
            $this->config =  Mage::getSingleton('ops/config');
        }

        return $this->config;
    }

    /**
     * @param Netresearch_OPS_Model_Config $config
     * @return $this
     */
    public function setConfig(Netresearch_OPS_Model_Config $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * @return array
     */
    public function getDirectDebitCountryIds()
    {
        return explode(',', $this->getConfig()->getDirectDebitCountryIds());
    }

    public function getBankTransferCountryIds()
    {
        return explode(',', $this->getConfig()->getBankTransferCountryIds());
    }

    /**
     * @param null $storeId
     * @return string
     */
    public function getPSPID($storeId = null)
    {
        return Mage::getModel('ops/config')->getPSPID($storeId);
    }

    /**
     * @param null $storeId
     * @param bool $admin
     * @return string
     */
    public function getGenerateHashUrl($storeId = null, $admin = false)
    {
        return Mage::getModel('ops/config')->getGenerateHashUrl($storeId, $admin);
    }

    /**
     * @return string
     */
    public function getValidationUrl()
    {
        return Mage::getModel('ops/config')->getValidationUrl();
    }

    /**
     * @return array
     */
    public function getDirectEbankingBrands()
    {
        return explode(',', $this->getConfig()->getDirectEbankingBrands());
    }


    /**
     * wrapper for Netresearch_OPS_Helper_Data::checkIfUserRegistering
     *
     * @return type bool
     */
    public function isUserRegistering()
    {
        return Mage::Helper('ops/data')->checkIfUserIsRegistering();
    }

    /**
     * wrapper for Netresearch_OPS_Helper_Data::checkIfUserRegistering
     *
     * @return type bool
     */
    public function isUserNotRegistering()
    {
        return Mage::Helper('ops/data')->checkIfUserIsNotRegistering();
    }

    /**
     * @return string
     */
    public function getPmLogo()
    {
        return $this->pmLogo;
    }

    /**
     * @return Simple_Xml
     */
    protected function getFieldMapping()
    {
        return $this->getConfig()->getFrontendFieldMapping();
    }

    /**
     * returns the corresponding fields for frontend validation if needed
     *
     * @return string - the json encoded fields
     */
    public function getFrontendValidators()
    {
        $frontendFields = array();
        if ($this->getConfig()->canSubmitExtraParameter($this->getQuote()->getStoreId())) {
            $fieldsToValidate = $this->getConfig()->getParameterLengths();
            $mappedFields = $this->getFieldMapping();
            foreach ($fieldsToValidate as $key => $value) {
                if (array_key_exists($key, $mappedFields)) {
                    $frontendFields = $this->getFrontendValidationFields($mappedFields, $key, $value, $frontendFields);
                }
            }
        }

        return Mage::helper('core/data')->jsonEncode($frontendFields);
    }

    /**
     * @param $mappedFields
     * @param $key
     * @param $value
     * @param $frontendFields
     *
     * @return mixed
     */
    public function getFrontendValidationFields($mappedFields, $key, $value, $frontendFields)
    {
        if (!is_array($mappedFields[$key])) {
            $frontendFields[$mappedFields[$key]] = $value;
        } else {
            foreach ($mappedFields[$key] as $mKey) {
                $frontendFields[$mKey] = $value;
            }
        }

        return $frontendFields;
    }

    public function getImageForBrand($brand)
    {
        $brandName = str_replace(' ', '', $brand);
        return $this->getSkinUrl('images/ops/alias/brands/'. $brandName .'.png');
    }
}

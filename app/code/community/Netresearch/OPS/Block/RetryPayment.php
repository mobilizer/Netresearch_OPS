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
 * RetryPayment.php
 *
 * @category payment
 * @package  Netresearch_OPS
 * @author   Paul Siedler <paul.siedler@netresearch.de>
 */ 

class Netresearch_OPS_Block_RetryPayment extends Netresearch_OPS_Block_Placeform {

    protected $order = null;

    protected function _getApi()
    {
        return $this->_getOrder()->getPayment()->getMethodInstance();
    }

    protected function _getOrder()
    {
        if(is_null($this->order)){
            $opsOrderId = $this->getRequest()->getParam('orderID');
            $this->order = Mage::helper('ops/order')->getOrder($opsOrderId);
        }
        return $this->order;
    }


}
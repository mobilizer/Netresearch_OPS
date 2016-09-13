<?php
class Netresearch_OPS_Test_Controller_AliasControllerTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{

    public function testAcceptAliasAction()
    {
        $quote = Mage::getModel('sales/quote');
        $payment = Mage::getModel('sales/quote_payment');
        $quote->setPayment($payment);
        $sessionMock = $this->getModelMock('checkout/session', array('getQuote'));
        $sessionMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $this->replaceByMock('singleton', 'checkout/session', $sessionMock);
        $aliasHelperMock = $this->getHelperMock('ops/alias', array('saveAlias', 'setAliasToPayment'));
        $this->replaceByMock('helper', 'ops/alias', $aliasHelperMock);
        $routeToDispatch = 'ops/alias/accept';
        $params = array('Alias_AliasId' => '4711');
        $this->dispatch($routeToDispatch, $params);
        $result = $this->getResponse()->getOutputBody();
        $this->assertEquals($result, "<script type='application/javascript'>window.onload =  function() {  top.document.fire('alias:success', '4711'); };</script>");

        $params = array('Alias_AliasId' => '4711', 'Card_CVC' => '123');
        $this->dispatch($routeToDispatch, $params);
        $result = $this->getResponse()->getOutputBody();
        $this->assertEquals($result, "<script type='application/javascript'>window.onload =  function() {  top.document.fire('alias:success', '4711'); };</script>");

    }

    /**
     * @loadFixture orders.yaml
     */
    public function testGenerateHashAction()
    {

        $fakeQuote = Mage::getModel('sales/order')->load(11);
        $quoteMock = $this->getModelMock('sales/quote', array('load', 'save'));
        $quoteMock->expects($this->any())
            ->method('load')
            ->will($this->returnValue($fakeQuote));
        $this->replaceByMock('model', 'sales/quote', $quoteMock);
        $params = array(
            'alias' => 4711,
            'storeId' => 1
        );

        $configHelperMock = $this->getModelMock('ops/config', array('getAliasAcceptUrl', 'getAliasExceptionUrl'));
        $configHelperMock->expects($this->any())
            ->method('getAliasAcceptUrl')
            ->with(1)
            ->will($this->returnValue(1));
        $configHelperMock->expects($this->any())
            ->method('getAliasExceptionUrl')
            ->with(1)
            ->will($this->returnValue(1));
        $this->replaceByMock('model', 'ops/config', $configHelperMock);

        $this->dispatch('ops/alias/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);

        $params = array(
            'alias' => 4712,
            'storeId' => 0
        );

        $configHelperMock = $this->getModelMock('ops/config', array('getAliasAcceptUrl', 'getAliasExceptionUrl'));
        $configHelperMock->expects($this->any())
            ->method('getAliasAcceptUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $configHelperMock->expects($this->any())
            ->method('getAliasExceptionUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $this->replaceByMock('model', 'ops/config', $configHelperMock);

        $this->dispatch('ops/alias/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);

        $params = array(
            'alias' => 4713,
            'storeId' => 1,
            'isAdmin' => 1,
            'brand' => 'visa'
        );

        $configHelperMock = $this->getModelMock('ops/config', array('getAliasAcceptUrl', 'getAliasExecptionUrl'));
        $configHelperMock->expects($this->any())
            ->method('getAliasAcceptUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $configHelperMock->expects($this->any())
            ->method('getAliasExceptionUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $this->replaceByMock('model', 'ops/config', $configHelperMock);

        $this->dispatch('ops/alias/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);

    }
}
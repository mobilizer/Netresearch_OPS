<?php

class Netresearch_OPS_Test_Controller_PaymentControllerTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{

    public function setUp()
    {
        parent::setUp();
        $helperMock = $this->getHelperMock(
            'ops/payment', array(
            'shaCryptValidation',
            'cancelOrder',
            'declineOrder',
            'handleException',
            'getSHAInSet',
            'refillCart'
        )
        );
        $helperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(true));

        $this->replaceByMock('helper', 'ops/payment', $helperMock);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testAcceptAction()
    {
        $params = array();
        $this->dispatch('ops/payment/accept', $params);
        $this->assertRedirect('checkout/cart');


        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch('ops/payment/accept', $params);
        $this->assertRedirect('checkout/onepage/success');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch('ops/payment/accept', $params);
        $this->assertRedirect('checkout/onepage/success');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testExceptionAction()
    {
        $params = array();
        $this->dispatch('ops/payment/exception', $params);
        $this->assertRedirect('checkout/cart');

        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch('ops/payment/exception', $params);
        $this->assertRedirect('checkout/onepage/success');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch('ops/payment/exception', $params);
        $this->assertRedirect('checkout/onepage/success');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testDeclineAction()
    {
        $routeToDispatch = 'ops/payment/decline';
        $params = array();
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');


        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testCancelAction()
    {
        $routeToDispatch = 'ops/payment/cancel';
        $params = array();
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testContinueAction()
    {
        $routeToDispatch = 'ops/payment/continue';
        $params = array();
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/cart');


        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/cart');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/cart');

        $params = array(
            'orderID'  => '#100000011',
            'redirect' => 'catalog'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('/');

        $params = array(
            'orderID'  => '23',
            'redirect' => 'catalog'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('/');


    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testRepayActionWithInvalidHash()
    {
        // test 1: hash not valid
        $order = Mage::getModel('sales/order')->load(11);
        $opsOrderId = Mage::helper('ops/order')->getOpsOrderId($order);

        $paymentHelperMock = $this->getHelperMock('ops/payment', array('shaCryptValidation'));
        $paymentHelperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/payment', $paymentHelperMock);


        $params = array('orderID' => $opsOrderId, 'SHASIGN' => 'foo');
        $this->dispatch('ops/payment/retry', $params);
        $this->assertRedirectTo('/');
        $message = Mage::getSingleton('core/session')->getMessages()->getLastAddedMessage();
        $this->assertNotNull($message);
        $this->assertEquals($message->getText(), 'Hash not valid');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testRepayActionWithInvalidOrder()
    {

        // test 1: hash valid, order can not be retried
        // orderID 100000011
        $order = Mage::getModel('sales/order')->load(11);
        $opsOrderId = Mage::helper('ops/order')->getOpsOrderId($order);

        $paymentHelperMock = $this->getHelperMock('ops/payment', array('shaCryptValidation'));
        $paymentHelperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/payment', $paymentHelperMock);

        $params = array(
            'orderID' => $opsOrderId,
            'SHASIGN' => 'foo'
        );
        $this->dispatch('ops/payment/retry', $params);
        $this->assertRedirectTo('/');
        $message = Mage::getSingleton('core/session')->getMessages()->getLastAddedMessage();
        $this->assertNotNull($message);
        $this->assertEquals(
            $message->getText(), 'Not possible to reenter the payment details for order ' . $order->getIncrementId()
        );

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testRepayActionWithSuccess()
    {
        // test 3: order is fine
        // orderID 100000013

        $order = Mage::getModel('sales/order')->load(13);
        $opsOrderId = Mage::helper('ops/order')->getOpsOrderId($order);

        $paymentHelperMock = $this->getHelperMock('ops/payment', array('shaCryptValidation'));
        $paymentHelperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/payment', $paymentHelperMock);

        $params = array(
            'orderID' => $opsOrderId,
            'SHASIGN' => 'foo'
        );

        $this->dispatch('ops/payment/retry', $params);
        $this->assertLayoutLoaded();
        $this->assertLayoutHandleLoaded('ops_payment_retry');

    }
}
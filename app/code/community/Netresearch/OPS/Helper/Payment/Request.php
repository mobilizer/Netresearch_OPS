<?php

/**
 * @author      Michael Lühr <michael.luehr@netresearch.de>
 * @category    Netresearch
 * @package     Netresearch_OPS
 * @copyright   Copyright (c) 2014 Netresearch GmbH & Co. KG (http://www.netresearch.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Netresearch_OPS_Helper_Payment_Request
{
    protected $config = null;

    /**
     * @param Netresearch_OPS_Model_Config $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @return Netresearch_OPS_Model_Config
     */
    public function getConfig()
    {
        if ($this->config === null) {
            $this->config = Mage::getModel('ops/config');
        }

        return $this->config;
    }

    /**
     * extracts the ship to information from a given address
     *
     * @param Mage_Customer_Model_Address_Abstract $address
     * @param Mage_Sales_Model_Quote || Mage_Sales_Model_Order              $salesObject
     *
     * @return array - the parameters containing the ship to data
     */
    public function extractShipToParameters($address, $salesObject = null)
    {
        $paramValues = array();

        if (!$address instanceof Mage_Customer_Model_Address_Abstract) {
            // virtual carts may not have a shipping address - so fall back to billing
            if (!is_null($salesObject) && $salesObject->getShippingAddress()) {
                $address = $salesObject->getShippingAddress();
            } else {
                $address = $salesObject->getBillingAddress();
            }

            if(!$address){
                return $paramValues;
            }
        }

        $paramValues['ECOM_SHIPTO_POSTAL_CITY'] = $address->getCity();
        $paramValues['ECOM_SHIPTO_POSTAL_POSTALCODE'] = $address->getPostcode();
        $paramValues['ECOM_SHIPTO_POSTAL_STATE'] = $this->getIsoRegionCode($address);
        $paramValues['ECOM_SHIPTO_POSTAL_COUNTRYCODE'] = $address->getCountry();
        $paramValues['ECOM_SHIPTO_POSTAL_NAME_FIRST'] = $address->getFirstname();
        $paramValues['ECOM_SHIPTO_POSTAL_NAME_LAST'] = $address->getLastname();
        $paramValues['ECOM_SHIPTO_POSTAL_STREET_LINE1'] = $address->getStreet(1);
        $paramValues['ECOM_SHIPTO_POSTAL_STREET_LINE2'] = $address->getStreet(2);

        return $paramValues;
    }

    /**
     * Extracts the billing address parameters for the ECOM_BILLTO fields
     *
     * @param Mage_Customer_Model_Address_Abstract $address
     * @param Mage_Sales_Model_Quote || Mage_Sales_Model_Order              $salesObject
     *
     * @return string[] - array containing the ECOM_BILLTO parameters
     */
    public function extractBillToParameters($address, $salesObject = null)
    {
        $paramValues = array();

        if (!$address instanceof Mage_Customer_Model_Address_Abstract) {
            if(!is_null($salesObject)) {
                $address = $salesObject->getBillingAddress();
            }
            if(!$address){
                return $paramValues;
            }
        }

        $paramValues['ECOM_BILLTO_POSTAL_CITY'] = $address->getCity();
        $paramValues['ECOM_BILLTO_POSTAL_POSTALCODE'] = $address->getPostcode();
        $paramValues['ECOM_BILLTO_POSTAL_COUNTY'] = $this->getIsoRegionCode($address);
        $paramValues['ECOM_BILLTO_POSTAL_COUNTRYCODE'] = $address->getCountry();
        $paramValues['ECOM_BILLTO_POSTAL_NAME_FIRST'] = $address->getFirstname();
        $paramValues['ECOM_BILLTO_POSTAL_NAME_LAST'] = $address->getLastname();
        $paramValues['ECOM_BILLTO_POSTAL_POSTALCODE'] = $address->getPostcode();
        $paramValues['ECOM_BILLTO_POSTAL_STREET_LINE1'] = $address->getStreet(1);
        $paramValues['ECOM_BILLTO_POSTAL_STREET_LINE2'] = $address->getStreet(2);
        $paramValues['ECOM_BILLTO_POSTAL_STREET_LINE3'] = $address->getStreet(3);

        return $paramValues;
    }

    /**
     * extraxcts the according PayEngine owner* parameter
     *
     * @param Mage_Customer_Model_Address_Abstract $billingAddress
     *
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order               $salesObject
     *
     * @return string[]
     */
    public function getOwnerParams(Mage_Customer_Model_Address_Abstract $billingAddress, $salesObject)
    {
        $ownerParams = array();
        if ($this->getConfig()->canSubmitExtraParameter($salesObject->getStoreId())) {
            $ownerParams = array(
                'OWNERADDRESS'                  => str_replace("\n", ' ', $billingAddress->getStreet(1)),
                'OWNERTOWN'                     => $billingAddress->getCity(),
                'OWNERZIP'                      => $billingAddress->getPostcode(),
                'OWNERTELNO'                    => $billingAddress->getTelephone(),
                'OWNERCTY'                      => $billingAddress->getCountry(),

                'ECOM_BILLTO_POSTAL_POSTALCODE' => $billingAddress->getPostcode(),
            );
        }

        return $ownerParams;
    }

    /**
     * Returns the template parameters and their dependencies
     *
     * @return array
     */

    public function getTemplateParams($storeId = null)
    {
        $formFields = array();
        switch ($this->getConfig()->getConfigData('template')) {
            case Netresearch_OPS_Model_Payment_Abstract::TEMPLATE_MAGENTO_INTERNAL:
                $formFields['TP'] = $this->getConfig()->getPayPageTemplate($storeId);
                break;
            case Netresearch_OPS_Model_Payment_Abstract::TEMPLATE_OPS_TEMPLATE:
                $formFields['TP'] = $this->getConfig()->getTemplateIdentifier($storeId);
                break;
            case Netresearch_OPS_Model_Payment_Abstract::TEMPLATE_OPS_IFRAME:
                $formFields['PARAMPLUS'] = 'IFRAME=1';
            case Netresearch_OPS_Model_Payment_Abstract::TEMPLATE_OPS_REDIRECT:
                $formFields['PMLISTTYPE'] = $this->getConfig()->getConfigData('pmlist', $storeId);
                $formFields['TITLE'] = $this->getConfig()->getConfigData('html_title', $storeId);
                $formFields['BGCOLOR'] = $this->getConfig()->getConfigData('bgcolor', $storeId);
                $formFields['TXTCOLOR'] = $this->getConfig()->getConfigData('txtcolor', $storeId);
                $formFields['TBLBGCOLOR'] = $this->getConfig()->getConfigData('tblbgcolor', $storeId);
                $formFields['TBLTXTCOLOR'] = $this->getConfig()->getConfigData('tbltxtcolor', $storeId);
                $formFields['BUTTONBGCOLOR'] = $this->getConfig()->getConfigData('buttonbgcolor', $storeId);
                $formFields['BUTTONTXTCOLOR'] = $this->getConfig()->getConfigData('buttontxtcolor', $storeId);
                $formFields['FONTTYPE'] = $this->getConfig()->getConfigData('fonttype', $storeId);
                $formFields['LOGO'] = $this->getConfig()->getConfigData('logo', $storeId);
                $formFields['HOMEURL'] = $this->getConfig()->hasHomeUrl() ? $this->getConfig()->getContinueUrl(array('redirect' => 'home')) : 'NONE';
                $formFields['CATALOGURL'] = $this->getConfig()->hasCatalogUrl() ? $this->getConfig()->getContinueUrl(array('redirect' => 'catalog')) : '';
                break;
            default:
                break;
        };

        return $formFields;

    }

    /**
     * extracts the region code in iso format (if possible)
     *
     * @param Mage_Customer_Model_Address_Abstract $address
     *
     * @return string - the region code in iso format
     */
    public function getIsoRegionCode(Mage_Customer_Model_Address_Abstract $address)
    {
        $regionCode = trim($address->getRegionCode());
        $countryCode = $address->getCountry();
        if ($this->isAlreadyIsoCode($regionCode, $countryCode)) {
            return $regionCode;
        }
        if (0 === strpos($regionCode, $countryCode . '-')) {
            return str_replace($countryCode . '-', '', $regionCode);
        }

        return $this->getRegionCodeFromMapping($countryCode, $regionCode);
    }

    /**
     * checks if the given region code is already in iso format
     *
     * @param string $regionCode
     * @param string $countryCode
     *
     * @return bool
     */
    protected function isAlreadyIsoCode($regionCode, $countryCode)
    {
        return ((strlen($regionCode) < 3 && !in_array($countryCode, array('AT')))
            || (strlen($regionCode) === 3 && !in_array($countryCode, array('DE'))));
    }

    protected function getRegionCodeFromMapping($countryCode, $regionCode)
    {
        $countryRegionMapping = $this->getCountryRegionMapping($countryCode);
        if (array_key_exists($regionCode, $countryRegionMapping)) {
            return $countryRegionMapping[$regionCode];
        }

        return $countryCode;
    }

    /**
     * retrieves country specific region mapping
     *
     * @param $countryCode
     *
     * @return array - the country specific region mapping or empty array if mapping could not be found
     */
    protected function getCountryRegionMapping($countryCode)
    {
        if (strtoupper($countryCode) === 'DE') {
            return $this->getRegionMappingForGermany();
        }
        if (strtoupper($countryCode) === 'AT') {
            return $this->getRegionMappingForAustria();
        }
        if (strtoupper($countryCode) === 'ES') {
            return $this->getRegionMappingForSpain();
        }
        if (strtoupper($countryCode) === 'FI') {
            return $this->getRegionsMappingForFinland();
        }
        if (strtoupper($countryCode) === 'LV') {
            return $this->getRegionsMappingForLatvia();
        }

        return array();;
    }

    /**
     * translates the Magento's region code for germany into ISO format
     *
     * @return array
     */
    protected function getRegionMappingForGermany()
    {
        return array(
            'NDS' => 'NI',
            'BAW' => 'BW',
            'BAY' => 'BY',
            'BER' => 'BE',
            'BRG' => 'BB',
            'BRE' => 'HB',
            'HAM' => 'HH',
            'HES' => 'HE',
            'MEC' => 'MV',
            'NRW' => 'NW',
            'RHE' => 'RP',
            'SAR' => 'SL',
            'SAS' => 'SN',
            'SAC' => 'ST',
            'SCN' => 'SH',
            'THE' => 'TH'
        );
    }

    /**
     * translates the Magento's region code for austria into ISO format
     *
     * @return array
     */
    protected function getRegionMappingForAustria()
    {
        return array(
            'WI' => '9',
            'NO' => '3',
            'OO' => '4',
            'SB' => '5',
            'KN' => '2',
            'ST' => '6',
            'TI' => '7',
            'BL' => '1',
            'VB' => '8'
        );
    }

    /**
     * translates the Magento's region code for spain into ISO format
     *
     * @return array
     */
    protected function getRegionMappingForSpain()
    {
        return array(
            'A Coruсa'               => 'C',
            'Alava'                  => 'VI',
            'Albacete'               => 'AB',
            'Alicante'               => 'A',
            'Almeria'                => 'AL',
            'Asturias'               => 'O',
            'Avila'                  => 'AV',
            'Badajoz'                => 'BA',
            'Baleares'               => 'PM',
            'Barcelona'              => 'B',
            'Caceres'                => 'CC',
            'Cadiz'                  => 'CA',
            'Cantabria'              => 'S',
            'Castellon'              => 'CS',
            'Ceuta'                  => 'CE',
            'Ciudad Real'            => 'CR',
            'Cordoba'                => 'CO',
            'Cuenca'                 => 'CU',
            'Girona'                 => 'GI',
            'Granada'                => 'GR',
            'Guadalajara'            => 'GU',
            'Guipuzcoa'              => 'SS',
            'Huelva'                 => 'H',
            'Huesca'                 => 'HU',
            'Jaen'                   => 'J',
            'La Rioja'               => 'LO',
            'Las Palmas'             => 'GC',
            'Leon'                   => 'LE',
            'Lleida'                 => 'L',
            'Lugo'                   => 'LU',
            'Madrid'                 => 'M',
            'Malaga'                 => 'MA',
            'Melilla'                => 'ML',
            'Murcia'                 => 'MU',
            'Navarra'                => 'NA',
            'Ourense'                => 'OR',
            'Palencia'               => 'P',
            'Pontevedra'             => 'PO',
            'Salamanca'              => 'SA',
            'Santa Cruz de Tenerife' => 'TF',
            'Segovia'                => 'Z',
            'Sevilla'                => 'SG',
            'Soria'                  => 'SE',
            'Tarragona'              => 'SO',
            'Teruel'                 => 'T',
            'Toledo'                 => 'TE',
            'Valencia'               => 'TO',
            'Valladolid'             => 'V',
            'Vizcaya'                => 'VA',
            'Zamora'                 => 'BI',
            'Zaragoza'               => 'ZA',
        );
    }

    /**
     * translates the Magento's region code for finland into ISO format
     *
     * @return array
     */
    protected function getRegionsMappingForFinland()
    {
        return array(
            'Lappi'             => '10',
            'Pohjois-Pohjanmaa' => '14',
            'Kainuu'            => '05',
            'Pohjois-Karjala'   => '13',
            'Pohjois-Savo'      => '15',
            'Etelä-Savo'        => '04',
            'Etelä-Pohjanmaa'   => '03',
            'Pohjanmaa'         => '12',
            'Pirkanmaa'         => '11',
            'Satakunta'         => '17',
            'Keski-Pohjanmaa'   => '07',
            'Keski-Suomi'       => '08',
            'Varsinais-Suomi'   => '19',
            'Etelä-Karjala'     => '02',
            'Päijät-Häme'       => '16',
            'Kanta-Häme'        => '06',
            'Uusimaa'           => '18',
            'Itä-Uusimaa'       => '19',
            'Kymenlaakso'       => '09',
            'Ahvenanmaa'        => '01'
        );
    }

    /**
     * translates the Magento's region code for latvia into ISO format
     *
     * @return array
     */
    protected function getRegionsMappingForLatvia()
    {
        return array(
            'Ādažu novads'         => 'LV',
            'Aglonas novads'       => '001',
            'Aizputes novads'      => '003',
            'Aknīstes novads'      => '004',
            'Alojas novads'        => '005',
            'Alsungas novads'      => '006',
            'Amatas novads'        => '008',
            'Apes novads'          => '009',
            'Auces novads'         => '010',
            'Babītes novads'       => '012',
            'Baldones novads'      => '013',
            'Baltinavas novads'    => '014',
            'Beverīnas novads'     => '017',
            'Brocēnu novads'       => '018',
            'Burtnieku novads'     => '019',
            'Carnikavas novads'    => '020',
            'Cesvaines novads'     => '021',
            'Ciblas novads'        => '023',
            'Dagdas novads'        => '024',
            'Dundagas novads'      => '027',
            'Durbes novads'        => '028',
            'Engures novads'       => '029',
            'Ērgļu novads'         => 'LV',
            'Garkalnes novads'     => '031',
            'Grobiņas novads'      => '032',
            'Iecavas novads'       => '034',
            'Ikšķiles novads'      => '035',
            'Ilūkstes novads'      => '036',
            'Inčukalna novads'     => '037',
            'Jaunjelgavas novads'  => '038',
            'Jaunpiebalgas novads' => '039',
            'Jaunpils novads'      => '040',
            'Jēkabpils'            => '042',
            'Kandavas novads'      => '043',
            'Kārsavas novads'      => 'LV',
            'Ķeguma novads'        => 'LV',
            'Ķekavas novads'       => 'LV',
            'Kokneses novads'      => '046',
            'Krimuldas novads'     => '048',
            'Krustpils novads'     => '049',
            'Lielvārdes novads'    => '053',
            'Līgatnes novads'      => 'LV',
            'Līvānu novads'        => '056',
            'Lubānas novads'       => '057',
            'Mālpils novads'       => '061',
            'Mārupes novads'       => '062',
            'Mazsalacas novads'    => '060',
            'Naukšēnu novads'      => '064',
            'Neretas novads'       => '065',
            'Nīcas novads'         => '066',
            'Olaines novads'       => '068',
            'Ozolnieku novads'     => '069',
            'Pārgaujas novads'     => 'LV',
            'Pāvilostas novads'    => '070',
            'Pļaviņu novads'       => '072',
            'Priekules novads'     => '074',
            'Priekuļu novads'      => '075',
            'Raunas novads'        => '076',
            'Riebiņu novads'       => '078',
            'Rojas novads'         => '079',
            'Ropažu novads'        => '080',
            'Rucavas novads'       => '081',
            'Rugāju novads'        => '082',
            'Rūjienas novads'      => '084',
            'Rundāles novads'      => '083',
            'Salacgrīvas novads'   => '085',
            'Salas novads'         => '086',
            'Salaspils novads'     => '087',
            'Saulkrastu novads'    => '089',
            'Sējas novads'         => 'LV',
            'Siguldas novads'      => '091',
            'Skrīveru novads'      => '092',
            'Skrundas novads'      => '093',
            'Smiltenes novads'     => '094',
            'Stopiņu novads'       => '095',
            'Strenču novads'       => '096',
            'Tērvetes novads'      => '098',
            'Vaiņodes novads'      => '100',
            'Valmiera'             => 'LV',
            'Varakļānu novads'     => '102',
            'Vārkavas novads'      => 'LV',
            'Vecpiebalgas novads'  => '104',
            'Vecumnieku novads'    => '105',
            'Viesītes novads'      => '107',
            'Viļakas novads'       => '108',
            'Viļānu novads'        => '109',
            'Zilupes novads'       => '110'
        );
    }

    /**
     * Returns the mandatory fields for requests to PayEngine
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */

    public function getMandatoryRequestFields(Mage_Sales_Model_Order $order)
    {
        $payment = $order->getPayment()->getMethodInstance();
        $formFields = array();
        $formFields['PSPID'] = $this->getConfig()->getPSPID($order->getStoreId());
        $formFields['AMOUNT'] = Mage::helper('ops')->getAmount($order->getBaseGrandTotal());
        $formFields['CURRENCY'] = Mage::app()->getStore()->getBaseCurrencyCode();
        $formFields['ORDERID'] = Mage::helper('ops/order')->getOpsOrderId($order);
        $formFields['LANGUAGE'] = Mage::app()->getLocale()->getLocaleCode();
        $formFields['PM'] = $payment->getOpsCode($order->getPayment());
        $formFields['EMAIL'] = $order->getCustomerEmail();

        $formFields['ACCEPTURL'] = $this->getConfig()->getAcceptUrl();
        $formFields['DECLINEURL'] = $this->getConfig()->getDeclineUrl();
        $formFields['EXCEPTIONURL'] = $this->getConfig()->getExceptionUrl();
        $formFields['CANCELURL'] = $this->getConfig()->getCancelUrl();
        $formFields['BACKURL'] = $this->getConfig()->getCancelUrl();

        $formFields['FP_ACTIV'] = $this->isFingerPrintingActive($order) ? '1' : '0';

        return $formFields;
    }

    /**
     * Will return the combination of activiation via config and the state of consent of the customer
     *
     * @param $order
     *
     * @return bool
     */
    protected function isFingerPrintingActive($order)
    {
        return $this->getConfig()->getDeviceFingerPrinting($order->getStoreId())
        && Mage::getSingleton('customer/session')->getData(Netresearch_OPS_Model_Payment_Abstract::FINGERPRINT_CONSENT_SESSION_KEY);
    }

    /**
     * Extracts the order item parameters and puts them in a array like
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */
    public function extractOrderItemParameters(Mage_Sales_Model_Order $order)
    {
        $formFields = array();

        // add order items
        $count = 1;
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItemId()
                && $item->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
                || $item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE
            ) {
                continue;
            }
            $formFields = array_merge($formFields, $this->getItemFormFields($count, $item));
            $count++;
        }

        // add discount item
        $discountItemFormFields = $this->getDiscountItemFormFields($order, $count);
        if (is_array($discountItemFormFields)) {
            $formFields = array_merge($formFields, $discountItemFormFields);
            $count++;
        }

        // add shipping item
        $shippingItemFields = $this->getShippingItemFormFields($order);
        if (is_array($shippingItemFields)) {
            $formFields = array_merge($formFields, $shippingItemFields);
        }

        return $formFields;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     *
     * @return float
     */

    public function getShippingTaxRate($order)
    {
        $store = $order->getStore();
        $taxCalculation = Mage::getModel('tax/calculation');
        $request = $taxCalculation->getRateRequest(null, null, null, $store);
        $taxRateId = Mage::getStoreConfig('tax/classes/shipping_tax_class', $store);
        //taxRateId is the same model id as product tax classes, so you can do this:
        $percent = $taxCalculation->getRate($request->setProductClassId($taxRateId));

        return $percent;
    }

    /**
     * Genereates item array for shipping, returns false if order is virtual
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array | false
     */
    protected function getShippingItemFormFields($order)
    {
        if ($order->getIsNotVirtual()) {
            /* add shipping item */
            $formFields['ORDERSHIPMETH'] = substr($order->getShippingDescription(), 0, 25);
            $formFields['ORDERSHIPCOST'] = Mage::helper('ops')->getAmount($order->getBaseShippingAmount());
            $formFields['ORDERSHIPTAXCODE']
                = str_replace(',', '.', (string)(float)$this->getShippingTaxRate($order)) . '%';
            $formFields['ORDERSHIPTAX'] = Mage::helper('ops')->getAmount($order->getBaseShippingTaxAmount());
            return $formFields;
        }

        return false;
    }

    /**
     * Returns item array for PayEngine request for the specified item
     *
     * @param $count
     * @param $item
     *
     * @return array
     */
    protected function getItemFormFields($count, $item)
    {
        $formFields = array();
        $formFields['ITEMID' . $count] = $item->getItemId();
        $formFields['ITEMNAME' . $count] = substr($item->getName(), 0, 40);
        $formFields['ITEMPRICE' . $count] = number_format($item->getBasePriceInclTax(), 2, '.', '');
        $formFields['ITEMQUANT' . $count] = (int)$item->getQtyOrdered();
        $formFields['ITEMVATCODE' . $count] = str_replace(',', '.', (string)(float)$item->getTaxPercent()) . '%';
        $formFields['TAXINCLUDED' . $count] = 1;

        return $formFields;
    }

    /**
     * Creates array
     *
     * @param Mage_Sales_Model_Order $order
     * @param                        $count
     *
     * @return mixed
     */
    protected function getDiscountItemFormFields(Mage_Sales_Model_Order $order, $count)
    {
        $formFields = array();
        /* add coupon item */
        if ($order->getBaseDiscountAmount()) {
            $couponAmount = $order->getBaseDiscountAmount();
            $formFields['ITEMID' . $count] = 'DISCOUNT';
            $couponRuleName = 'DISCOUNT';
            if ($order->getCouponRuleName() && strlen(trim($order->getCouponRuleName())) > 0) {
                $couponRuleName = substr(trim($order->getCouponRuleName()), 0, 30);
            }
            $formFields['ITEMNAME' . $count] = $couponRuleName;
            $formFields['ITEMPRICE' . $count] = number_format($couponAmount, 2, '.', '');
            $formFields['ITEMQUANT' . $count] = 1;
            $formFields['ITEMVATCODE' . $count]
                = str_replace(',', '.', (string)(float)$this->getShippingTaxRate($order)) . '%';
            $formFields['TAXINCLUDED' . $count] = 1;
            return $formFields;

        }
        return false;
    }
} 
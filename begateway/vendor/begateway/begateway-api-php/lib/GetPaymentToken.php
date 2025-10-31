<?php

declare(strict_types=1);

namespace BeGateway;

class GetPaymentToken extends ApiAbstract
{
    public $customer;
    public $money;
    public $additional_data;
    protected $_description;
    protected $_tracking_id;
    protected $_success_url;
    protected $_decline_url;
    protected $_fail_url;
    protected $_cancel_url;
    protected $_notification_url;
    protected $_transaction_type;
    protected $_readonly;
    protected $_visible;
    protected $_payment_methods;
    protected $_expired_at;
    protected $_test_mode;
    protected $_attempts;
    protected $_headers = ['X-Api-Version: 2'];

    public function __construct()
    {
        $this->customer = new Customer();
        $this->money = new Money();
        $this->additional_data = new AdditionalData();
        $this->setPaymentTransactionType();
        $this->_language = Language::getDefaultLanguage();
        $this->_expired_at = null;
        $this->_readonly = [];
        $this->_visible = [];
        $this->_payment_methods = [];
        $this->_test_mode = false;
        $this->_attempts = null;
    }

    protected function _endpoint()
    {
        return Settings::$checkoutBase . '/ctp/api/checkouts';
    }

    protected function _buildRequestMessage()
    {
        $request = [
            'checkout' => [
                'transaction_type' => $this->getTransactionType(),
                'attempts' => $this->getAttempts(),
                'test' => $this->getTestMode(),
                'order' => [
                    'amount' => $this->money->getCents(),
                    'currency' => $this->money->getCurrency(),
                    'description' => $this->getDescription(),
                    'tracking_id' => $this->getTrackingId(),
                    'expired_at' => $this->getExpiryDate(),
                    'additional_data' => [
                        'receipt_text' => $this->additional_data->getReceipt(),
                        'contract' => $this->additional_data->getContract(),
                        'meta' => $this->additional_data->getMeta(),
                        'fiscalization' => $this->additional_data->getFiscalization(),
                        'platform_data' => $this->additional_data->getPlatformData(),
                        'integration_data' => $this->additional_data->getIntegrationData(),
                    ],
                ],
                'settings' => [
                    'notification_url' => $this->getNotificationUrl(),
                    'success_url' => $this->getSuccessUrl(),
                    'decline_url' => $this->getDeclineUrl(),
                    'fail_url' => $this->getFailUrl(),
                    'cancel_url' => $this->getCancelUrl(),
                    'language' => $this->getLanguage(),
                    'customer_fields' => [
                        'read_only' => $this->getReadonlyFields(),
                        'visible' => $this->getVisibleFields(),
                    ]
                ],
                'customer' => [
                    'email' => $this->customer->getEmail(),
                    'first_name' => $this->customer->getFirstName(),
                    'last_name' => $this->customer->getLastName(),
                    'country' => $this->customer->getCountry(),
                    'city' => $this->customer->getCity(),
                    'state' => $this->customer->getState(),
                    'zip' => $this->customer->getZip(),
                    'address' => $this->customer->getAddress(),
                    'phone' => $this->customer->getPhone(),
                    'birth_date' => $this->customer->getBirthDate(),
                ],
            ],
        ];

        if (is_null($this->getAttempts())) {
            unset($request['checkout']['attempts']);
        }

        $payment_methods = $this->_getPaymentMethods();
        if ($payment_methods != null) {
            $request['checkout']['payment_method'] = $payment_methods;
        }

        Logger::getInstance()->write($request, Logger::DEBUG, get_class($this) . '::' . __FUNCTION__);

        return $request;
    }

    public function submit()
    {
        return new ResponseCheckout($this->_remoteRequest());
    }

    public function setDescription($description)
    {
        $this->_description = $description;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function setTrackingId($tracking_id)
    {
        $this->_tracking_id = $tracking_id;
    }

    public function getTrackingId()
    {
        return $this->_tracking_id;
    }

    public function setNotificationUrl($notification_url)
    {
        $this->_notification_url = $notification_url;
    }

    public function getNotificationUrl()
    {
        return $this->_notification_url;
    }

    public function setSuccessUrl($success_url)
    {
        $this->_success_url = $success_url;
    }

    public function getSuccessUrl()
    {
        return $this->_success_url;
    }

    public function setDeclineUrl($decline_url)
    {
        $this->_decline_url = $decline_url;
    }

    public function getDeclineUrl()
    {
        return $this->_decline_url;
    }

    public function setFailUrl($fail_url)
    {
        $this->_fail_url = $fail_url;
    }

    public function getFailUrl()
    {
        return $this->_fail_url;
    }

    public function setCancelUrl($cancel_url)
    {
        $this->_cancel_url = $cancel_url;
    }

    public function getCancelUrl()
    {
        return $this->_cancel_url;
    }

    public function setAuthorizationTransactionType()
    {
        $this->_transaction_type = 'authorization';
    }

    public function setPaymentTransactionType()
    {
        $this->_transaction_type = 'payment';
    }

    public function setTokenizationTransactionType()
    {
        $this->_transaction_type = 'tokenization';
    }

    public function getTransactionType()
    {
        return $this->_transaction_type;
    }

    public function setLanguage($language_code)
    {
        if (in_array($language_code, Language::getSupportedLanguages())) {
            $this->_language = $language_code;
        } else {
            $this->_language = Language::getDefaultLanguage();
        }
    }

    public function getLanguage()
    {
        return $this->_language;
    }

    // date when payment expires for payment
    // date is in ISO8601 format
    public function setExpiryDate($date)
    {
        $iso8601 = null;

        if ($date != null) {
            $iso8601 = date('c', strtotime($date));
        }

        $this->_expired_at = $iso8601;
    }

    public function getExpiryDate()
    {
        return $this->_expired_at;
    }

    public function getReadonlyFields()
    {
        return $this->_readonly;
    }

    public function getVisibleFields()
    {
        return $this->_visible;
    }

    public function setVisible(array $visible)
    {
        $this->_visible = $visible;
    }

    public function setReadonly(array $readonly)
    {
        $this->_readonly = $readonly;
    }

    public function setFirstNameReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'first_name');
    }

    public function unsetFirstNameReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['first_name']);
    }

    public function setLastNameReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'last_name');
    }

    public function unsetLastNameReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['last_name']);
    }

    public function setEmailReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'email');
    }

    public function unsetEmailReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['email']);
    }

    public function setAddressReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'address');
    }

    public function unsetAddressReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['address']);
    }

    public function setCityReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'city');
    }

    public function unsetCityReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['city']);
    }

    public function setStateReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'state');
    }

    public function unsetStateReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['state']);
    }

    public function setZipReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'zip');
    }

    public function unsetZipReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['zip']);
    }

    public function setPhoneReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'phone');
    }

    public function unsetPhoneReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['phone']);
    }

    public function setCountryReadonly()
    {
        $this->_readonly = self::_searchAndAdd($this->_readonly, 'country');
    }

    public function unsetCountryReadonly()
    {
        $this->_readonly = array_diff($this->_readonly, ['country']);
    }

    public function setPhoneVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'phone');
    }

    public function unsetPhoneVisible()
    {
        $this->_visible = array_diff($this->_visible, ['phone']);
    }

    public function setAddressVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'address');
    }

    public function unsetAddressVisible()
    {
        $this->_visible = array_diff($this->_visible, ['address']);
    }

    public function setFirstNameVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'first_name');
    }

    public function unsetFirstNameVisible()
    {
        $this->_visible = array_diff($this->_visible, ['first_name']);
    }

    public function setLastNameVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'last_name');
    }

    public function unsetLastNameVisible()
    {
        $this->_visible = array_diff($this->_visible, ['last_name']);
    }

    public function setCityVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'city');
    }

    public function unsetCityVisible()
    {
        $this->_visible = array_diff($this->_visible, ['city']);
    }

    public function setStateVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'state');
    }

    public function unsetStateVisible()
    {
        $this->_visible = array_diff($this->_visible, ['state']);
    }

    public function setZipVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'zip');
    }

    public function unsetZipVisible()
    {
        $this->_visible = array_diff($this->_visible, ['zip']);
    }

    public function setCountryVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'country');
    }

    public function unsetCountryVisible()
    {
        $this->_visible = array_diff($this->_visible, ['country']);
    }

    public function setEmailVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'email');
    }

    public function unsetEmailVisible()
    {
        $this->_visible = array_diff($this->_visible, ['email']);
    }

    public function setBirthDateVisible()
    {
        $this->_visible = self::_searchAndAdd($this->_visible, 'birth_date');
    }

    public function unsetBirthDateVisible()
    {
        $this->_visible = array_diff($this->_visible, ['birth_date']);
    }

    public function addPaymentMethod($method)
    {
        $this->_payment_methods[] = $method;
    }

    public function setTestMode($mode = true)
    {
        $this->_test_mode = $mode;
    }

    public function getTestMode(): bool
    {
        return $this->_test_mode;
    }

    public function setAttempts($attempts)
    {
        $this->_attempts = $attempts;
    }

    public function getAttempts()
    {
        return $this->_attempts;
    }

    private function _searchAndAdd($array, $value)
    {
        // search for $value in $array
        // if not found, adds $value to $array and returns $array
        // otherwise returns not altered $array
        $arr = $array;
        if (! in_array($value, $arr)) {
            array_push($arr, $value);
        }

        return $arr;
    }

    private function _getPaymentMethods()
    {
        $arResult = [];

        if (! empty($this->_payment_methods)) {
            $arResult['types'] = [];
            foreach ($this->_payment_methods as $pm) {
                $arResult['types'][] = $pm->getName();
                $arResult[$pm->getName()] = $pm->getParamsArray();
            }
        } else {
            $arResult = null;
        }

        return $arResult;
    }
}

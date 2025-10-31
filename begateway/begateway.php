<?php
/*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author BeGateway <techsupport@ecomcharge.com>
*  @copyright  2018 eComCharge
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_')) {
	exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Begateway extends PaymentModule
{
	private $_html = '';
	/**
	 * predefined test account
	 *
	 * @var array
	 */
	private $presets = [
		'test' => [
			'shop_id' => '361',
			'shop_key' => 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d',
			'public_shop_key' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArO7bNKtnJgCn0PJVn2X7QmhjGQ2GNNw412D+NMP4y3Qs69y6i5T/zJBQAHwGKLwAxyGmQ2mMpPZCk4pT9HSIHwHiUVtvdZ/78CX1IQJON/Xf22kMULhquwDZcy3Cp8P4PBBaQZVvm7v1FwaxswyLD6WTWjksRgSH/cAhQzgq6WC4jvfWuFtn9AchPf872zqRHjYfjgageX3uwo9vBRQyXaEZr9dFR+18rUDeeEzOEmEP+kp6/Pvt3ZlhPyYm/wt4/fkk9Miokg/yUPnk3MDU81oSuxAw8EHYjLfF59SWQpQObxMaJR68vVKH32Ombct2ZGyzM7L5Tz3+rkk7C4z9oQIDAQAB',
			'domain_checkout' => 'checkout.begateway.com',
			'domain_gateway' => 'demo-gateway.begateway.com'
		]
	];

	public function __construct()
	{
		include_once(dirname(__FILE__).'/vendor/autoload.php');

		$this->name = 'begateway';
		$this->tab = 'payments_gateways';
		$this->version = '2.0.1';
		$this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
		$this->author = 'eComCharge';
		$this->controllers = ['validation'];
		$this->need_instance = 1;

		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		$this->bootstrap = true;
		$this->display = true;

		parent::__construct();

		$this->displayName = $this->l('BeGateway');
		$this->description = $this->l('Accept online payments');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');

		if (!count(Currency::checkPaymentCurrencies($this->id)) && $this->active) {
			$this->warning = $this->l('No currency has been set for this module.');
		}
	}

	public function install(): bool
	{
		if (Shop::isFeatureActive()) {
			Shop::setContext(Shop::CONTEXT_ALL);
		}

		if (!extension_loaded('curl')) {
			$this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
			return false;
		}

		if (!parent::install()) {
			$this->_errors[] = $this->l('Problem with start installation of this module');
			return false;
		}

		if (!($this->registerHook('displayBackOfficeHeader') &&
			$this->registerHook('paymentOptions') &&
			$this->registerHook('displayPaymentReturn'))) {
			$this->_errors[] = $this->l('Problem with hook registration for this module');
		}

//		$language_code = $this->context->language->iso_code;

		Module::updateTranslationsAfterInstall(false);

		Configuration::updateValue('BEGATEWAY_SHOP_ID', $this->presets['test']['shop_id']);
		Configuration::updateValue('BEGATEWAY_SHOP_PASS', $this->presets['test']['shop_key']);
		Configuration::updateValue('BEGATEWAY_TRANS_TYPE_CREDIT_CARD', 'payment');
		Configuration::updateValue('BEGATEWAY_ACTIVE_MODE', false);
		Configuration::updateValue('BEGATEWAY_DOMAIN_CHECKOUT', $this->presets['test']['domain_checkout']);
		Configuration::updateValue('BEGATEWAY_TEST_MODE', true);

		Configuration::updateValue('BEGATEWAY_ACTIVE_CREDIT_CARD', true);
		Configuration::updateValue('BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA', false);
		Configuration::updateValue('BEGATEWAY_ACTIVE_ERIP', false);

		// payment titles
		foreach (Language::getLanguages() as $language) {
			if (Tools::strtolower($language['iso_code']) == 'ru') {
				Configuration::updateValue('BEGATEWAY_TITLE_CREDIT_CARD_' . $language['iso_code'], 'Оплатить онлайн банковской картой');
				Configuration::updateValue('BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $language['iso_code'], 'Оплатить онлайн картой Халва');
				Configuration::updateValue('BEGATEWAY_TITLE_ERIP_' . $language['iso_code'], 'Оплатить через ЕРИП');
			} else {
				Configuration::updateValue('BEGATEWAY_TITLE_CREDIT_CARD_' . $language['iso_code'], 'Pay by credit card');
				Configuration::updateValue('BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $language['iso_code'], 'Pay by Halva');
				Configuration::updateValue('BEGATEWAY_TITLE_ERIP_' . $language['iso_code'], 'Pay by ERIP');
			}
		}

		$ow_status = Configuration::get('BEGATEWAY_STATE_WAITING');
		if ($ow_status === false) {
			$orderState = new OrderState();
		} else {
			$orderState = new OrderState((int)$ow_status);
		}

		$orderState->name = [];

		foreach (Language::getLanguages() as $language) {
			if (Tools::strtolower($language['iso_code']) == 'ru') {
				$orderState->name[$language['id_lang']] = 'Ожидание завершения оплаты';
			} else {
				$orderState->name[$language['id_lang']] = 'Awaiting for payment';
			}
		}

		$orderState->send_email = false;
		$orderState->color = '#4169E1';
		$orderState->hidden = false;
		$orderState->module_name = 'begateway';
		$orderState->delivery = false;
		$orderState->logable = false;
		$orderState->invoice = false;
		$orderState->unremovable = true;
		$orderState->save();

		Configuration::updateValue('BEGATEWAY_STATE_WAITING', (int)$orderState->id);

		copy(_PS_MODULE_DIR_ . 'begateway/views/img/logo.gif', _PS_IMG_DIR_ . 'os/' . (int)$orderState->id . '.gif');

		return true;
	}

	public function uninstall(): bool
	{
		Configuration::deleteByName('BEGATEWAY_ACTIVE_MODE');
		Configuration::deleteByName('BEGATEWAY_SHOP_ID');
		Configuration::deleteByName('BEGATEWAY_SHOP_PASS');
		Configuration::deleteByName('BEGATEWAY_TRANS_TYPE_CREDIT_CARD');
		Configuration::deleteByName('BEGATEWAY_DOMAIN_CHECKOUT');
		Configuration::deleteByName('BEGATEWAY_TEST_MODE');
		Configuration::deleteByName('BEGATEWAY_ACTIVE_CREDIT_CARD');
		Configuration::deleteByName('BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA');
		Configuration::deleteByName('BEGATEWAY_ACTIVE_ERIP');

		$orderStateId = Configuration::get('BEGATEWAY_STATE_WAITING');
		if ($orderStateId) {
			$orderState = new OrderState();
			$orderState->id = $orderStateId;
			$orderState->delete();
			unlink(_PS_IMG_DIR_ . 'os/' . (int)$orderState->id . '.gif');
		}

		Configuration::deleteByName('BEGATEWAY_STATE_WAITING');

		// payment titles
		foreach (Language::getLanguages() as $language) {
			Configuration::deleteByName('BEGATEWAY_TITLE_CREDIT_CARD_' . $language['iso_code']);
			Configuration::deleteByName('BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $language['iso_code']);
			Configuration::deleteByName('BEGATEWAY_TITLE_ERIP_' . $language['iso_code']);
		}

		if (!($this->unregisterHook('displayBackOfficeHeader') &&
			$this->unregisterHook('paymentOptions') &&
			$this->unregisterHook('displayPaymentReturn'))) {
			$this->_errors[] = $this->l('Problem with hook unregistration for this module');
		}

		return (bool)parent::uninstall();
	}

	public function hookDisplayBackOfficeHeader()
	{
		if (Tools::getValue('configure') == $this->name) {
			$this->context->controller->addJS($this->_path . 'views/js/back.js');
		}
	}

	/**
	 * Load the configuration form
	 */
	public function getContent()
	{
		$this->_html = '';

		/**
		 * If values have been submitted in the form, process.
		 */
		if (((bool)Tools::isSubmit('submitBegatewayModule')) == true) {
			$this->postProcess();
		}

		$this->context->smarty->assign('module_dir', $this->_path);

		$output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
		$output .= $this->_html;
		$output .= $this->renderForm();

		return $output;
	}

	/**
	 * Save form data.
	 */
	protected function postProcess()
	{
		$form_values = $this->getConfigFormValues();

		foreach (array_keys($form_values) as $key) {
			Configuration::updateValue($key, Tools::getValue($key));
		}

		$this->_html .= $this->displayConfirmation($this->trans('Settings updated', [], 'Admin.Notifications.Success'));
	}

	public function getConfigForm()
	{
		$id_lang = $this->context->language->iso_code;
		return [
			'form' => [
				'legend' => [
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				],
				'input' => [
					[
						'type' => 'switch',
						'label' => $this->l('Active'),
						'name' => 'BEGATEWAY_ACTIVE_MODE',
						'is_bool' => true,
						'values' => [
							[
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							],
							[
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							]
						],
					],
					[
						'type' => 'text',
						'label' => $this->l('Checkout page domain'),
						'name' => 'BEGATEWAY_DOMAIN_CHECKOUT',
						'required' => true
					],
					[
						'type' => 'switch',
						'label' => $this->l('Test mode'),
						'name' => 'BEGATEWAY_TEST_MODE',
						'values' => [
							[
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Test')
							],
							[
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Live')
							]
						]
					],
					[
						'type' => 'text',
						'label' => $this->l('Shop Id'),
						'name' => 'BEGATEWAY_SHOP_ID',
						'required' => true
					],
					[
						'type' => 'text',
						'label' => $this->l('Shop secret key'),
						'name' => 'BEGATEWAY_SHOP_PASS',
						'required' => true,
					],
					[
						'col' => 8,
						'type' => 'html',
						'name' => '<hr>',
					],
					[
						'type' => 'switch',
						'label' => $this->l('Credit card active'),
						'name' => 'BEGATEWAY_ACTIVE_CREDIT_CARD',
						'values' => [
							[
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							],
							[
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							]
						]
					],
					[
						'type' => 'select',
						'label' => $this->l('Transaction Type'),
						'name' => 'BEGATEWAY_TRANS_TYPE_CREDIT_CARD',
						'id' => 'BEGATEWAY_ACTIVE_CREDIT_CARD_OPTION1',
						'options' => [
							'query' => [
								['id' => 'payment', 'name' => $this->l('Payment')],
								['id' => 'authorization', 'name' => $this->l('Authorization')]
							],
							'name' => 'name',
							'id' => 'id'
						]
					],
					[
						'type' => 'text',
						'label' => $this->l('Title'),
						'name' => 'BEGATEWAY_TITLE_CREDIT_CARD_' . $id_lang,
						'id' => 'BEGATEWAY_ACTIVE_CREDIT_CARD_OPTION2',
						'required' => true,
					],
					[
						'col' => 8,
						'type' => 'html',
						'name' => '<hr id="BEGATEWAY_ACTIVE_CREDIT_CARD_OPTION9">',
					],
					[
						'type' => 'switch',
						'label' => $this->l('Halva active'),
						'name' => 'BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA',
						'values' => [
							[
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							],
							[
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							]
						]
					],
					[
						'type' => 'text',
						'label' => $this->l('Title'),
						'name' => 'BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $id_lang,
						'id' => 'BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA_OPTION2',
						'required' => true,
					],
					[
						'col' => 8,
						'type' => 'html',
						'name' => '<hr id="BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA_OPTION9">',
					],
					[
						'type' => 'switch',
						'label' => $this->l('ERIP active'),
						'name' => 'BEGATEWAY_ACTIVE_ERIP',
						'values' => [
							[
								'id' => 'active_on',
								'value' => true,
								'label' => $this->l('Enabled')
							],
							[
								'id' => 'active_off',
								'value' => false,
								'label' => $this->l('Disabled')
							]
						]
					],
					[
						'type' => 'text',
						'label' => $this->l('Title'),
						'name' => 'BEGATEWAY_TITLE_ERIP_' . $id_lang,
						'id' => 'BEGATEWAY_ACTIVE_ERIP_OPTION2',
						'required' => true,
					],
					[
						'col' => 8,
						'type' => 'html',
						'name' => '<hr id="BEGATEWAY_ACTIVE_ERIP_OPTION9">',
					],
				],
				'submit' => [
					'title' => $this->l('Save')
				]
			]
		];
	}

	public function renderForm()
	{
		$helper = new HelperForm();

		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$helper->module = $this;
		$helper->default_form_language = $this->context->language->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitBegatewayModule';

		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
			. '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		$helper->tpl_vars = [
			'fields_value' => $this->getConfigFormValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		];

		return $helper->generateForm([$this->getConfigForm()]);
	}

	public function getConfigFormValues()
	{
		$id_lang = $this->context->language->iso_code;
		return [
			'BEGATEWAY_ACTIVE_MODE' => Tools::getValue('BEGATEWAY_ACTIVE_MODE', Configuration::get('BEGATEWAY_ACTIVE_MODE', false)),
			'BEGATEWAY_SHOP_ID' => Tools::getValue('BEGATEWAY_SHOP_ID', Configuration::get('BEGATEWAY_SHOP_ID', $this->presets['test']['shop_id'])),
			'BEGATEWAY_SHOP_PASS' => Tools::getValue('BEGATEWAY_SHOP_PASS', Configuration::get('BEGATEWAY_SHOP_PASS', $this->presets['test']['shop_key'])),
			'BEGATEWAY_DOMAIN_CHECKOUT' => Tools::getValue('BEGATEWAY_DOMAIN_CHECKOUT', Configuration::get('BEGATEWAY_DOMAIN_CHECKOUT', $this->presets['test']['domain_checkout'])),
			'BEGATEWAY_TEST_MODE' => Tools::getValue('BEGATEWAY_TEST_MODE', Configuration::get('BEGATEWAY_TEST_MODE', true)),

			'BEGATEWAY_ACTIVE_CREDIT_CARD' => Tools::getValue('BEGATEWAY_ACTIVE_CREDIT_CARD', Configuration::get('BEGATEWAY_ACTIVE_CREDIT_CARD', false)),
			'BEGATEWAY_TRANS_TYPE_CREDIT_CARD' => Tools::getValue('BEGATEWAY_TRANS_TYPE_CREDIT_CARD', Configuration::get('BEGATEWAY_TRANS_TYPE_CREDIT_CARD', 'payment')),

			'BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA' => Tools::getValue('BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA', Configuration::get('BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA', false)),
			'BEGATEWAY_ACTIVE_ERIP' => Tools::getValue('BEGATEWAY_ACTIVE_ERIP', Configuration::get('BEGATEWAY_ACTIVE_ERIP', false)),
			'BEGATEWAY_TITLE_CREDIT_CARD_' . $id_lang => Tools::getValue('BEGATEWAY_TITLE_CREDIT_CARD_' . $id_lang, Configuration::get('BEGATEWAY_TITLE_CREDIT_CARD_' . $id_lang)),
			'BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $id_lang => Tools::getValue('BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $id_lang, Configuration::get('BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $id_lang)),
			'BEGATEWAY_TITLE_ERIP_' . $id_lang => Tools::getValue('BEGATEWAY_TITLE_ERIP_' . $id_lang, Configuration::get('BEGATEWAY_TITLE_ERIP_' . $id_lang))
		];
	}

	public function hookPaymentOptions($params)
	{
		if (!$this->active) {
			return [];
		}

		if (!Configuration::get('BEGATEWAY_ACTIVE_MODE', false)) {
			return [];
		}

		$this->smarty->assign('module_dir', $this->_path);

		$id_lang = $this->context->language->iso_code;

		$payments = [
			'credit_card' => [
				'isActive' => Configuration::get('BEGATEWAY_ACTIVE_CREDIT_CARD', false),
				'title' => Configuration::get('BEGATEWAY_TITLE_CREDIT_CARD_' . $id_lang),
				'id' => 'creditcard',
			],
			'credit_card_halva' => [
				'isActive' => Configuration::get('BEGATEWAY_ACTIVE_CREDIT_CARD_HALVA', false),
				'title' => Configuration::get('BEGATEWAY_TITLE_CREDIT_CARD_HALVA_' . $id_lang),
				'id' => 'creditcardhalva',
			],
			'erip' => [
				'isActive' => Configuration::get('BEGATEWAY_ACTIVE_ERIP', false),
				'title' => Configuration::get('BEGATEWAY_TITLE_ERIP_' . $id_lang),
				'id' => 'erip',
			]
		];
		$activePayments = [];
		foreach ($payments as $payment => $paymentInfos) {
			if ($paymentInfos['isActive'] == 1) {
				$activePayments['begateway_' . $payment] = [];
				$activePayments['begateway_' . $payment]['cta_text'] = $paymentInfos['title'];
				$activePayments['begateway_' . $payment]['logo'] = Media::getMediaPath(
					_PS_MODULE_DIR_ . $this->name . '/views/img/' . $this->name . '_' . $paymentInfos['id'] . '.png'
				);
				$activePayments['begateway_' . $payment]['action'] = $this->context->link->getModuleLink(
					$this->name,
					$paymentInfos['id'],
					[],
					true
				);
			}
		}

		$newOptions = [];
		if (sizeof($activePayments) > 0) {
			foreach ($activePayments as $legacyOption) {
				if (!$legacyOption) {
					continue;
				}

				foreach (PaymentOption::convertLegacyOption($legacyOption) as $option) {
					/** @var $option PaymentOption */
					$option->setModuleName($this->name);
					$newOptions[] = $option;
				}
			}
			return $newOptions;
		}

		return [];
	}

	public function hookDisplayPaymentReturn($params)
	{
		if (!$this->active) {
			return false;
		}
		/** @var order $order */
		$order = $params['order'];
		$currency = new Currency($order->id_currency);

		if (strcasecmp($order->module, 'begateway') != 0) {
			return false;
		}

		if (Tools::getValue('status') != 'failed' && $order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
			$this->smarty->assign('status', 'ok');
		}

		$this->smarty->assign([
			'id_order' => $order->id,
			'reference' => $order->reference,
			'params' => $params,
			'total' => Context::getContext()->getCurrentLocale()->formatPrice(
				$order->getOrdersTotalPaid(),
				(new Currency($order->id_currency))->iso_code,
				false
			),
		]);

		return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
	}

	public function init_begateway()
	{
		\BeGateway\Settings::$gatewayBase = 'https://gateway.begateway.com';
		\BeGateway\Settings::$checkoutBase = 'https://' . trim(Configuration::get('BEGATEWAY_DOMAIN_CHECKOUT'));
		\BeGateway\Settings::$shopId = trim(Configuration::get('BEGATEWAY_SHOP_ID'));
		\BeGateway\Settings::$shopKey = trim(Configuration::get('BEGATEWAY_SHOP_PASS'));
	}
}

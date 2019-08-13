<?php

/**
 * 2007-2018 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
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
 *  @author    MercadoPago
 *  @copyright Copyright (c) MercadoPago [http://www.mercadopago.com]
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of MercadoPago
 */

error_reporting(E_ALL);
ini_set('display_errors','On');

define('MP_VERSION', '4.0.1');
define('MP_ROOT_URL', dirname(__FILE__));

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mercadopago extends PaymentModule
{
    public $mercadopago;
    public $mpuseful;
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $bootstrap;
    public $displayName;
    public $description;
    public $confirmUninstall;
    public $module_key;
    public $ps_versions_compliancy;
    public static $form_alert;
    public static $form_message;

    public function __construct()
    {
        $this->loadFiles();
        $this->loadSettings();
        $this->mercadopago = MPApi::getInstance();
        $this->mpuseful = MPUseful::getInstance();

        $this->name = 'mercadopago';
        $this->tab = 'payments_gateways';
        $this->author = 'mercadopago';
        $this->need_instance = 1;
        $this->bootstrap = true;

        //Always update, because prestashop doesn't accept version coming from another variable (MP_VERSION)
        $this->version = '4.0.1';

        parent::__construct();

        $this->displayName = $this->l('Mercado Pago');
        $this->description = $this->l('Customize the payment experience of your customers in your online store.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the module?');
        $this->module_key = '4380f33bbe84e7899aacb0b7a601376f';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Install the module
     *
     * @return void
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension ') .
                $this->l('on your server to install this module.');
            return false;
        }

        //Prestashop configuration table
        $mp_currency = $this->context->currency->iso_code;
        Configuration::updateValue('MERCADOPAGO_COUNTRY_LINK', $this->mpuseful->setMPCurrency($mp_currency));
        Configuration::updateValue('MERCADOPAGO_AUTO_RETURN', true);
        Configuration::updateValue('MERCADOPAGO_SANDBOX_STATUS', true);
        Configuration::updateValue('MERCADOPAGO_INSTALLMENTS', 24);
        Configuration::updateValue('MERCADOPAGO_STANDARD', false);
        Configuration::updateValue('MERCADOPAGO_HOMOLOGATION', false);

        //Mercadopago configurations
        include(MP_ROOT_URL . '/sql/install.php');
        MPLog::generate('Mercadopago plugin installed in the store');

        //install hooks and dependencies
        return parent::install() &&
            $this->createPaymentStates() &&
            $this->registerHook('header') &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayWrapperTop') &&
            $this->registerHook('displayTopColumn');
    }

    /**
     * Uninstall the module
     *
     * @return void
     */
    public function uninstall()
    {
        MPLog::generate('Mercadopago plugin uninstalled in the store');
        include(MP_ROOT_URL . '/sql/uninstall.php');
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     *
     * @return void
     */
    public function getContent()
    {
        //add css to configuration page
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');

        $this->context->smarty->assign('module_dir', $this->_path);

        //test flow
        $mp_transaction = new MPTransaction();
        $count_test = $mp_transaction->where('is_payment_test', '=', 1)->andWhere('received_webhook', '=', 1)->count();

        //verify if seller is homologated
        if (Configuration::get('MERCADOPAGO_HOMOLOGATION') == false) {
            if (in_array('payments', $this->mercadopago->homologValidate())) {
                Configuration::updateValue('MERCADOPAGO_HOMOLOGATION', true);
            }
        }

        //return forms for admin views
        $country_link = Configuration::get('MERCADOPAGO_COUNTRY_LINK');

        $store = new StoreSettings();
        $rating = new RatingSettings();
        $custom = new CustomSettings();
        $ticket = new TicketSettings();
        $standard = new StandardSettings();
        $credentials = new CredentialsSettings();
        $localization = new LocalizationSettings();
        $homologation = new HomologationSettings();

        $store = $this->renderForm($store->submit, $store->values, $store->form);
        $custom = $this->renderForm($custom->submit, $custom->values, $custom->form);
        $ticket = $this->renderForm($ticket->submit, $ticket->values, $ticket->form);
        $standard = $this->renderForm($standard->submit, $standard->values, $standard->form);
        $credentials = $this->renderForm($credentials->submit, $credentials->values, $credentials->form);
        $localization = $this->renderForm($localization->submit, $localization->values, $localization->form);
        $homologation = $this->renderForm($homologation->submit, $homologation->values, $homologation->form);

        $output = $this->context->smarty->assign(array(
            'alert' => self::$form_alert,
            'message' => self::$form_message,
            'url_base' => __PS_BASE_URI__,
            'count_test' => $count_test,
            'seller_homolog' => Configuration::get('MERCADOPAGO_HOMOLOGATION'),
            'country_form' => $localization,
            'credentials' => $credentials,
            'homolog_form' => $homologation,
            'store_form' => $store,
            'standard_form' => $standard,
            'custom_form' => $custom,
            'ticket_form' => $ticket,
            'access_token' => Configuration::get('MERCADOPAGO_ACCESS_TOKEN'),
            'sandbox_status' => Configuration::get('MERCADOPAGO_SANDBOX_STATUS'),
            'sandbox_access_token' => Configuration::get('MERCADOPAGO_SANDBOX_ACCESS_TOKEN'),
            'standard_test' => Configuration::get('MERCADOPAGO_STANDARD'),
            'country_link' => $country_link,
            'application' => Configuration::get('MERCADOPAGO_APPLICATION_ID'),
            'seller_protect_link' => $this->mpuseful->setSellerProtectLink($country_link)
        ))
            ->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output;
    }

    /**
     * Load files
     *
     * @return void
     */
    public function loadFiles()
    {
        require_once MP_ROOT_URL . '/includes/MPApi.php';
        require_once MP_ROOT_URL . '/includes/MPLog.php';
        require_once MP_ROOT_URL . '/includes/MPUseful.php';
        require_once MP_ROOT_URL . '/includes/MPRestCli.php';
        require_once MP_ROOT_URL . '/model/MPModule.php';
        require_once MP_ROOT_URL . '/model/MPTransaction.php';
    }

    /**
     * Load settings
     *
     * @return void
     */
    public function loadSettings()
    {
        require_once MP_ROOT_URL . '/includes/module/settings/StoreSettings.php';
        require_once MP_ROOT_URL . '/includes/module/settings/RatingSettings.php';
        require_once MP_ROOT_URL . '/includes/module/settings/StandardSettings.php';
        require_once MP_ROOT_URL . '/includes/module/settings/CustomSettings.php';
        require_once MP_ROOT_URL . '/includes/module/settings/TicketSettings.php';
        require_once MP_ROOT_URL . '/includes/module/settings/CredentialsSettings.php';
        require_once MP_ROOT_URL . '/includes/module/settings/LocalizationSettings.php';
        require_once MP_ROOT_URL . '/includes/module/settings/HomologationSettings.php';
    }

    /**
     * Render forms
     *
     * @return void
     */
    protected function renderForm($submit, $values, $form)
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->submit_action = $submit;
        $helper->identifier = $this->identifier;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $values,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($form));
    }

    /**
     * Create the payment states
     *
     * @return void
     */
    protected function createPaymentStates()
    {
        $order_states = array(
            array('#ccfbff', $this->l('Transaction in Process'), 'in_process', '110010000'),
            array('#c9fecd', $this->l('Transaction Completed'), 'payment', '100010010'),
            array('#fec9c9', $this->l('Transaction Canceled'), 'order_canceled', '100010000'),
            array('#fec9c9', $this->l('Transaction Declined'), 'payment_error', '100010000'),
            array('#ffeddb', $this->l('Transaction Refunded'), 'refund', '100010000'),
            array('#c28566', $this->l('Transaction Chargedback'), 'charged_back', '110010000'),
            array('#b280b2', $this->l('Transaction in Mediation'), 'in_mediation', '110010000'),
            array('#fffb96', $this->l('Transaction Pending'), 'pending', '110010000'),
            array('#ccfbff', $this->l('Transaction Authorized'), 'authorized', '100010000'),
        );

        foreach ($order_states as $key => $value) {
            if ($this->orderStateAvailable(Configuration::get('MERCADOPAGO_STATUS_' . $key)) == 1) {
                continue;
            } else {
                $order_state = new OrderState();
                $order_state->name = array();
                $order_state->template = array();
                $order_state->module_name = $this->name;
                $order_state->color = $value[0];
                $order_state->invoice = $value[3][0];
                $order_state->send_email = $value[3][1];
                $order_state->unremovable = $value[3][2];
                $order_state->hidden = $value[3][3];
                $order_state->logable = $value[3][4];
                $order_state->delivery = $value[3][5];
                $order_state->shipped = $value[3][6];
                $order_state->paid = $value[3][7];
                $order_state->deleted = $value[3][8];

                $order_state->name = array_fill(0, 10, $value[1]);
                $order_state->template = array_fill(0, 10, $value[2]);

                if ($order_state->add()) {
                    $file = _PS_ROOT_DIR_ . '/img/os/' . (int) $order_state->id . '.gif';
                    copy((dirname(__file__) . '/views/img/mp_icon.gif'), $file);
                    Configuration::updateValue('MERCADOPAGO_STATUS_' . $key, $order_state->id);
                }
            }
        }

        return true;
    }

    /**
     * Check if the state exist before create another one
     *
     * @param integer $id_order_state
     * @return void
     */
    protected static function orderStateAvailable($id_order_state)
    {
        $result = Db::getInstance()->getRow(
            "SELECT COUNT(*) AS count_state FROM " . _DB_PREFIX_ . "order_state 
            WHERE id_order_state = '" . $id_order_state . "'"
        );
        return $result['count_state'];
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO
     *
     * @return void
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    /**
     * Show payment options in version 1.6
     *
     * @param mixed $params
     * @return void
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign('module_dir', $this->_path);

        if (Configuration::get('MERCADOPAGO_STANDARD_CHECKOUT') == true) {
            $mp_logo = _MODULE_DIR_ . 'mercadopago/views/img/mpinfo_checkout.png';
            $redirect = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ .
                '?fc=module&module=mercadopago&controller=standard&checkout=standard';

            $debito = 0;
            $credito = 0;
            $efectivo = 0;
            $tarjetas = $this->mercadopago->getPaymentMethods();
            foreach ($tarjetas as $tarjeta) {
                if (Configuration::get($tarjeta['config']) != "") {
                    if ($tarjeta['type'] == 'credit_card') {
                        $credito += 1;
                    } elseif ($tarjeta['type'] == 'debit_card' || $tarjeta['type'] == 'prepaid_card') {
                        $debito += 1;
                    } else {
                        $efectivo += 1;
                    }
                }
            }

            $this->context->smarty->assign(array(
                "debito" => $debito,
                "mp_logo" => $mp_logo,
                "credito" => $credito,
                "efectivo" => $efectivo,
                "tarjetas" => $tarjetas,
                "redirect" => $redirect,
                "installments" => Configuration::get('MERCADOPAGO_INSTALLMENTS')
            ));

            return $this->display(__file__, 'views/templates/hook/payment_six.tpl');
        }
    }

    /**
     * Show payment options in version 1.7
     *
     * @param mixed $params
     * @return void
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if (Configuration::get('MERCADOPAGO_STANDARD_CHECKOUT') == true) {
            $debito = 0;
            $credito = 0;
            $efectivo = 0;
            $tarjetas = $this->mercadopago->getPaymentMethods();
            foreach ($tarjetas as $tarjeta) {
                if (Configuration::get($tarjeta['config']) != "") {
                    if ($tarjeta['type'] == 'credit_card') {
                        $credito += 1;
                    } elseif ($tarjeta['type'] == 'debit_card' || $tarjeta['type'] == 'prepaid_card') {
                        $debito += 1;
                    } else {
                        $efectivo += 1;
                    }
                }
            }

            $infoTemplate = $this->context->smarty->assign(array(
                "debito" => $debito,
                "credito" => $credito,
                "efectivo" => $efectivo,
                "tarjetas" => $tarjetas,
                "module_dir" => $this->_path,
                "installments" => Configuration::get('MERCADOPAGO_INSTALLMENTS')
            ))
                ->fetch('module:mercadopago/views/templates/hook/payment_seven.tpl');

            $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $newOption->setCallToActionText($this->l('I want to pay with Mercado Pago without additional cost.'))
                ->setLogo(_MODULE_DIR_ . 'mercadopago/views/img/mpinfo_checkout.png')
                ->setAdditionalInformation($infoTemplate)
                ->setAction($this->context->link->getModuleLink($this->name, 'standard'));

            return [$newOption];
        }
    }

    /**
     * Check currency
     *
     * @param mixed $cart
     * @return boolean
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * This hook is used to display the order confirmation page.
     *
     * @param mixed $params
     * @return void
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return;
        }
    }

    /**
     * Display payment failure on version 1.6
     *
     * @return void
     */
    public function hookDisplayTopColumn()
    {
        if (Tools::getValue('typeReturn') == 'failure') {
            return $this->display(__FILE__, 'views/templates/hook/failure.tpl');
        }
    }

    /**
     * Display payment failure on version 1.7
     *
     * @return void
     */
    public function hookDisplayWrapperTop()
    {
        if (Tools::getValue('typeReturn') == 'failure') {
            return $this->display(__FILE__, 'views/templates/hook/failure.tpl');
        }
    }
}
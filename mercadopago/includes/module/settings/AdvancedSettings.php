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

require_once MP_ROOT_URL . '/includes/module/settings/AbstractSettings.php';

class AdvancedSettings extends AbstractSettings
{
    public $online_payments;
    public $offline_payments;

    public function __construct()
    {
        parent::__construct();
        $this->submit = 'submitMercadopagoAdvanced';
        $this->values = $this->getFormValues();
        $this->form = $this->generateForm();
        $this->process = $this->verifyPostProcess();
    }

    /**
     * Generate inputs form
     *
     * @return void
     */
    public function generateForm()
    {
        $title = $this->module->l('Advanced Configuration', 'AdvancedSettings');
        $fields = array(
            array(
                'type' => 'switch',
                'label' => $this->module->l('Activate modal', 'AdvancedSettings'),
                'name' => 'MERCADOPAGO_STANDARD_MODAL',
                'is_bool' => true,
                'desc' => $this->module->l('Select "YES" to enable a modal for checkout. ', 'AdvancedSettings') .
                    $this->module->l('Select "NO" to redirect your customer to Mercado Pago.', 'AdvancedSettings'),
                'values' => array(
                    array(
                        'id' => 'MERCADOPAGO_STANDARD_MODAL_ON',
                        'value' => true,
                        'label' => $this->module->l('Active', 'AdvancedSettings')
                    ),
                    array(
                        'id' => 'MERCADOPAGO_STANDARD_MODAL_OFF',
                        'value' => false,
                        'label' => $this->module->l('Inactive', 'AdvancedSettings')
                    )
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->module->l('Return to the store', 'AdvancedSettings'),
                'name' => 'MERCADOPAGO_AUTO_RETURN',
                'is_bool' => true,
                'desc' => $this->module->l('Do you want your client to come back to ', 'AdvancedSettings') .
                    $this->module->l('the store after finishing the purchase?', 'AdvancedSettings'),
                'values' => array(
                    array(
                        'id' => 'MERCADOPAGO_AUTO_RETURN_ON',
                        'value' => true,
                        'label' => $this->module->l('Active', 'AdvancedSettings')
                    ),
                    array(
                        'id' => 'MERCADOPAGO_AUTO_RETURN_OFF',
                        'value' => false,
                        'label' => $this->module->l('Inactive', 'AdvancedSettings')
                    )
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $this->module->l('Binary Mode', 'AdvancedSettings'),
                'name' => 'MERCADOPAGO_STANDARD_BINARY_MODE',
                'is_bool' => true,
                'desc' => $this->module->l('Accept and reject payments automatically. Do you want us to activate it? ', 'AdvancedSettings'),
                'hint' => $this->module->l('If you activate the binary mode ', 'AdvancedSettings') .
                    $this->module->l('you will not be able to leave pending payments. ', 'AdvancedSettings') .
                    $this->module->l('This can affect the prevention of fraud. ', 'AdvancedSettings') .
                    $this->module->l('Leave it inactive to be protected by our own tool.', 'AdvancedSettings'),
                'values' => array(
                    array(
                        'id' => 'MERCADOPAGO_STANDARD_BINARY_MODE_ON',
                        'value' => true,
                        'label' => $this->module->l('Active', 'AdvancedSettings')
                    ),
                    array(
                        'id' => 'MERCADOPAGO_STANDARD_BINARY_MODE_OFF',
                        'value' => false,
                        'label' => $this->module->l('Inactive', 'AdvancedSettings')
                    )
                ),
            ),
            array(
                'col' => 2,
                'suffix' => 'hours',
                'type' => 'text',
                'name' => 'MERCADOPAGO_EXPIRATION_DATE_TO',
                'label' => $this->module->l('Save payment preferences during ', 'AdvancedSettings'),
                'hint' => $this->module->l('Payment links are generated every time we receive ', 'AdvancedSettings') .
                    $this->module->l('data of a purchase intention of your customers. ', 'AdvancedSettings') .
                    $this->module->l('We keep that information for a period of time not to ', 'AdvancedSettings') .
                    $this->module->l('ask for the data each time you return to the purchase process. ', 'AdvancedSettings') .
                    $this->module->l('Choose when you want us to forget it.', 'AdvancedSettings'),
                'desc' => ' ',
            ),
            array(
                'col' => 2,
                'type' => 'text',
                'name' => 'MERCADOPAGO_SPONSOR_ID',
                'label' => $this->module->l('Sponsor ID', 'AdvancedSettings'),
                'desc' => $this->module->l('With this number we identify all your transactions ', 'AdvancedSettings') .
                    $this->module->l('and we know how many sales we process with your account.', 'AdvancedSettings'),
            ),
        );

        return $this->buildForm($title, $fields);
    }

    /**
     * Save form data
     *
     * @return void
     */
    public function postFormProcess()
    {
        $this->validate = ([
            'MERCADOPAGO_SPONSOR_ID' => 'sponsor_id',
            'MERCADOPAGO_EXPIRATION_DATE_TO' => 'expiration_preference'
        ]);

        parent::postFormProcess();

        Configuration::updateValue('MERCADOPAGO_STANDARD', true);

        $this->sendSettingsInfo();
        MPLog::generate('Standard checkout configuration saved successfully');
    }

    /**
     * Set values for the form inputs
     *
     * @return array
     */
    public function getFormValues()
    {
        $form_values = array(
            'MERCADOPAGO_SPONSOR_ID' => Configuration::get('MERCADOPAGO_SPONSOR_ID'),
            'MERCADOPAGO_AUTO_RETURN' => Configuration::get('MERCADOPAGO_AUTO_RETURN'),
            'MERCADOPAGO_STANDARD_MODAL' => Configuration::get('MERCADOPAGO_STANDARD_MODAL'),
            'MERCADOPAGO_STANDARD_BINARY_MODE' => Configuration::get('MERCADOPAGO_STANDARD_BINARY_MODE'),
            'MERCADOPAGO_EXPIRATION_DATE_TO' => Configuration::get('MERCADOPAGO_EXPIRATION_DATE_TO'),
        );

        return $form_values;
    }

    /**
     * Get installments
     *
     * @param int $max
     * @return void
     */
    public function getInstallments($max)
    {
        $installments = array();
        for ($i = $max; $i > 0; $i--) {
            $installments[] = array('id' => $i, 'name' => $i);
        }

        return $installments;
    }
}

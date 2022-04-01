<?php
/**
 * Project : everpsshoppayment
 * @author Team Ever
 * @copyright Team Ever
 * @license   Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * @link http://team-ever.com
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Everpsshoppayment extends PaymentModule
{
    private $html;
    private $postErrors = array();
    private $postSuccess = array();

    public function __construct()
    {
        $this->name = 'everpsshoppayment';
        $this->tab = 'front_office_features';
        $this->version = '1.1.4';
        $this->author = 'Team Ever';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Ever Shop Payment');
        $this->description = $this->l('Allows you to accept payments in your shop');
        $this->confirmUninstall = $this->l('Do you really want to uninstall this module ?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->isSeven = Tools::version_compare(_PS_VERSION_, '1.7', '>=') ? true : false;
        $this->context->smarty->assign(array(
            'image_dir' => $this->_path.'views/img'
        ));
    }

    public function install()
    {
        if (_PS_VERSION_ >= '1.7') {
            $hookPayment = 'paymentOptions';
        } else {
            $hookPayment = 'Payment';
        }

        return (parent::install()
            && $this->createOrderState()
            && Configuration::updateValue('EVERPSSHOPPAY_ID_CARRIER', 1)
            && Configuration::updateValue('EVERPSSHOPPAY_BLOCK_CARRIER', 0)
            && $this->registerHook('paymentReturn')
            && $this->registerHook($hookPayment));
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->deleteOrderState()
            && Configuration::deleteByName('EVERPSSHOPPAY_ID_CARRIER')
            && Configuration::deleteByName('EVERPSSHOPPAY_BLOCK_CARRIER')
            && Configuration::deleteByName('PS_OS_EVERPSSHOPPAYMENT');
    }

    private function createOrderState()
    {
        $orderState = new OrderState();

        foreach (Language::getLanguages(false) as $lang) {
            $orderState->name[(int)$lang['id_lang']] = $this->l('Pay in shop');
        }
        $orderState->module_name = $this->name;
        $orderState->invoice = false;
        $orderState->shipped = false;
        $orderState->paid = false;
        $orderState->pdf_delivery = false;
        $orderState->pdf_invoice = false;
        $orderState->color = '#9c7240';
        if ($orderState->save()) {
            Configuration::updateValue('PS_OS_EVERPSSHOPPAYMENT', (int)$orderState->id);
            return true;
        }
    }

    private function deleteOrderState()
    {
        $orderState = new OrderState((int)Configuration::get('PS_OS_EVERPSSHOPPAYMENT'));

        if ($orderState->delete()) {
            return true;
        }
    }

    public function getContent()
    {
        $this->html = '';

        if (Tools::isSubmit('submitEverPsShopPaymentConf')) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $this->postProcess();
            }
        }

        if (count($this->postErrors)) {
            foreach ($this->postErrors as $error) {
                $this->html .= $this->displayError($error);
            }
        }

        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/header.tpl');
        $this->html .= $this->renderForm();
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/footer.tpl');

        return $this->html;
    }

    public function postValidation()
    {
        if (Tools::isSubmit('submitEverPsShopPaymentConf')) {
            if (!Tools::getIsset('EVERPSSHOPPAY_ID_CARRIER')
                || !Validate::isUnsignedInt(Tools::getValue('EVERPSSHOPPAY_ID_CARRIER'))
            ) {
                $this->postErrors[] = $this->l('Error : The field "Carrier" is not valid');
            }
            if (!Tools::getIsset('EVERPSSHOPPAY_BLOCK_CARRIER')
                || !Validate::isUnsignedInt(Tools::getValue('EVERPSSHOPPAY_BLOCK_CARRIER'))
            ) {
                $this->postErrors[] = $this->l('Error : The field "Block carrier" is not valid');
            }
        }
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $this->postSuccess[] = $this->l('All settings have been saved');
    }

    protected function getConfigFormValues()
    {
        return array(
            'EVERPSSHOPPAY_ID_CARRIER' => Configuration::get('EVERPSSHOPPAY_ID_CARRIER'),
            'EVERPSSHOPPAY_BLOCK_CARRIER' => Configuration::get('EVERPSSHOPPAY_BLOCK_CARRIER'),

        );
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEverPsShopPaymentConf';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => (int)$this->context->controller->getLanguages(),
            'id_language' => (int)$this->context->language->id,
        );

        return $helper->generateForm($this->getConfigForm());
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $carriers = Carrier::getCarriers((int)$this->context->language->id);
        $form_fields = array();
        $form_fields[] = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Carrier setting'),
                    'icon' => 'icon-download',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Block payment in shop using carrier'),
                        'name' => 'EVERPSSHOPPAY_BLOCK_CARRIER',
                        'is_bool' => true,
                        'desc' => $this->l('Set yes for block on carrier'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Carrier concerned'),
                        'name' => 'EVERPSSHOPPAY_ID_CARRIER',
                        'desc' => $this->l('Will be the only carrier allowed for payments in shop'),
                        'required' => true,
                        'options' => array(
                            'query' => $carriers,
                            'id' => 'id_carrier',
                            'name' => 'name'
                        )
                    ),
                ),
                'submit' => array(
                    'name' => 'submit',
                    'title' => $this->l('Save'),
                ),
            )
        );

        return $form_fields;
    }

    /**
     * Hook payment, PS 1.7 only.
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if ((bool)Configuration::get('EVERPSSHOPPAY_BLOCK_CARRIER')) {
            if ((int)$params['cart']->id_carrier == (int)Configuration::get('EVERPSSHOPPAY_ID_CARRIER')) {
                $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
                $newOption->setModuleName($this->name)
                        ->setCallToActionText($this->l('Pay in our shop'))
                        ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                        ->setAdditionalInformation(
                            $this->fetch('module:everpsshoppayment/views/templates/front/payment_infos.tpl')
                        );

                return array($newOption);
            }
        } else {
            $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
            $newOption->setModuleName($this->name)
                    ->setCallToActionText($this->l('Pay in our shop'))
                    ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                    ->setAdditionalInformation(
                        $this->fetch('module:everpsshoppayment/views/templates/front/payment_infos.tpl')
                    );

            return array($newOption);
        }
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        if ((int)Configuration::get('EVERPSSHOPPAY_BLOCK_CARRIER')) {
            if ($params['cart']->id_carrier == Configuration::get('EVERPSSHOPPAY_ID_CARRIER')) {
                $this->smarty->assign(array(
                    'this_path' => $this->_path,
                    'this_path_bw' => $this->_path,
                    'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
                ));
                return $this->display(__FILE__, 'payment.tpl');
            }
        } else {
            $this->smarty->assign(array(
                'this_path' => $this->_path,
                'this_path_bw' => $this->_path,
                'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
            ));
            return $this->display(__FILE__, 'payment.tpl');
        }
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        $this->smarty->assign(array(
            'shop_name' => Configuration::get('PS_SHOP_NAME'),
            'shop_phone' => Configuration::get(
                'PS_SHOP_PHONE',
                null,
                null,
                (int)$this->context->shop->id
            ),
            'shop_email' => Configuration::get(
                'PS_SHOP_EMAIL',
                null,
                null,
                (int)$this->context->shop->id
            ),
        ));

        if ($this->isSeven) {
            return $this->fetch('module:everpsshoppayment/views/templates/hook/payment_return.tpl');
        } else {
            return $this->display(__FILE__, 'payment_return.tpl');
        }
    }

    public function hookupdateCarrier($params)
    {
        if ((int)($params['id_carrier']) == (int)(Configuration::get('EVERPSSHOPPAY_ID_CARRIER'))) {
            Configuration::updateValue('EVERPSSHOPPAY_ID_CARRIER', (int)($params['carrier']->id));
        }
    }
}

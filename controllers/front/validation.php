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

class EverpsshoppaymentValidationModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::init();
    }

    public function initContent()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0
            || (int)$cart->id_address_delivery == 0
            || (int)$cart->id_address_invoice == 0
            || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        if ((int)$cart->id_carrier != (int)Configuration::get('EVERPSSHOPPAY_ID_CARRIER')
            && (int)Configuration::get('EVERPSSHOPPAY_BLOCK_CARRIER')) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'everpsshoppayment') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->trans('This payment method is not available.', array(), 'Modules.Everpsshoppayment.Shop'));
        }
        parent::initContent();

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        if (!Configuration::get('PS_OS_EVERPSSHOPPAYMENT')) {
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
            }
        }

        $this->module->validateOrder(
            (int)$cart->id,
            Configuration::get('PS_OS_EVERPSSHOPPAYMENT'),
            $total,
            $this->module->displayName,
            null,
            null,
            (int)$currency->id,
            false,
            $customer->secure_key
        );
        Tools::redirect(
            'index.php?controller=order-confirmation&id_cart='
            .(int)$cart->id
            .'&id_module='
            .(int)$this->module->id
            .'&id_order='
            .$this->module->currentOrder
            .'&key='.$customer->secure_key
        );
    }
}

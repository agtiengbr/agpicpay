<?php
require_once _PS_MODULE_DIR_ . 'agcliente/lib/AgPaymentModule.php';

/**
 *
 */
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
class BaseAgPicPay extends AgPaymentModule
{
    protected $hooks = [
        'payment',
        'paymentReturn',
        'paymentOptions',
    ];

    public function __construct()
    {
        $this->name                   = 'agpicpay';
        $this->version                = '1.0.6';
        $this->bootstrap              = true;
        $this->author                 = 'AGTI';
        $this->need_instance          = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '8.99');

        parent::__construct();

        $this->displayName = 'Pagamento via PicPay';
        $this->description = 'Esse módulo permite o pagamento de um pedido via PicPay';

        $this->loadMappings();
    }

    public function resetConfig()
    {
        if (Configuration::hasKey('AGPICPAY_STATUS_CREATED') === false) {
            Configuration::updateValue('AGPICPAY_STATUS_CREATED', 10);
        }
        if (Configuration::hasKey('AGPICPAY_STATUS_EXPIRED') === false) {
            Configuration::updateValue('AGPICPAY_STATUS_EXPIRED', 6);
        }
        if (Configuration::hasKey('AGPICPAY_STATUS_ANALYSIS') === false) {
            Configuration::updateValue('AGPICPAY_STATUS_ANALYSIS', 10);
        }
        if (Configuration::hasKey('AGPICPAY_STATUS_PAID') === false) {
            Configuration::updateValue('AGPICPAY_STATUS_PAID', 2);
        }
        if (Configuration::hasKey('AGPICPAY_STATUS_COMPLETED') === false) {
            Configuration::updateValue('AGPICPAY_STATUS_COMPLETED', 2);
        }
        if (Configuration::hasKey('AGPICPAY_STATUS_REFUNDED') === false) {
            Configuration::updateValue('AGPICPAY_STATUS_REFUNDED', 7);
        }
        if (Configuration::hasKey('AGPICPAY_STATUS_CHARGEBACK') === false) {
            Configuration::updateValue('AGPICPAY_STATUS_CHARGEBACK', 7);
        }

        if (!$this->getCpfMapping()->isMappingEnabled() && Module::isInstalled('agcustomers')) {
            $this->getCpfMapping()->mapsTo('cpf');
        }
    }

    public function getContent()
    {
        return $this->renderConfigForm();
    }

    public function renderConfigForm()
    {
        if (Tools::getIsSet('agpicpay-save')) {
            Configuration::updateValue("PICPAYCODE", Tools::getValue('picpayCode'));
            Configuration::updateValue("AGPICPAY_STATUS_CREATED", Tools::getValue("picpay_status_created"));
            Configuration::updateValue("AGPICPAY_STATUS_EXPIRED", Tools::getValue("picpay_status_expired"));
            Configuration::updateValue("AGPICPAY_STATUS_ANALYSIS", Tools::getValue("picpay_status_analysis"));
            Configuration::updateValue("AGPICPAY_STATUS_PAID", Tools::getValue("picpay_status_paid"));
            Configuration::updateValue("AGPICPAY_STATUS_COMPLETED", Tools::getValue("picpay_status_completed"));
            Configuration::updateValue("AGPICPAY_STATUS_REFUNDED", Tools::getValue("picpay_status_refunded"));
            Configuration::updateValue("AGPICPAY_STATUS_CHARGEBACK", Tools::getValue("picpay_status_chargeback"));
            Configuration::updateValue("AGPICPAY_DISCOUNT", Tools::getValue("picpayDiscount"));

            $this->getCpfMapping()->mapsTo(Tools::getValue('cpf_mapping'));
        }

        $this->context->controller->addJs(array(
            '//cdn.jsdelivr.net/bluebird/3.5.0/bluebird.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/riot/2.6.7/riot+compiler.min.js'
        ));

        $this->context->controller->addCss(array(
            _PS_MODULE_DIR_ . $this->name. '/views/css/configuration.css'
        ));

        $order_states = OrderState::getOrderStates($this->context->language->id);
        $order_states = array_merge([['id_order_state' => -1, 'name' => 'Escolha uma opção']], $order_states);

        $this->context->smarty->assign([
            'picpayCode' => Configuration::get("PICPAYCODE"),
            'picpayDiscount' => Configuration::get("AGPICPAY_DISCOUNT"),
            'ps_version' => _PS_VERSION_,
            'module' => $this,

            'orderStates' => $order_states,
            'picpayStatusCreated' => Configuration::get("AGPICPAY_STATUS_CREATED"),
            'picpayStatusExpired' => Configuration::get("AGPICPAY_STATUS_EXPIRED"),
            'picpayStatusAnalysis' => Configuration::get("AGPICPAY_STATUS_ANALYSIS"),
            'picpayStatusPaid' => Configuration::get("AGPICPAY_STATUS_PAID"),
            'picpayStatusCompleted' => Configuration::get("AGPICPAY_STATUS_COMPLETED"),
            'picpayStatusRefunded' => Configuration::get("AGPICPAY_STATUS_REFUNDED"),
            'picpayStatusChargeback' => Configuration::get("AGPICPAY_STATUS_CHARGEBACK"),
        ]);


        $html = $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/configuration.tpl');
        $html .= $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/ps-tags.tpl');
        $html .= $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/ps-alert.tpl');
        $html .= $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/ps-form.tpl');
        $html .= $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/ps-panel.tpl');
        $html .= $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/ps-table.tpl');
        $html .= $this->display(_PS_MODULE_DIR_ . $this->name, 'views/templates/admin/ps-tabs.tpl');
        return $html;
    }



    public function getExternalPaymentOption($params)
    {
        $discount = Configuration::get("AGPICPAY_DISCOUNT");
        $discountRate = $discount / 100;
        $discount = $this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS) * $discountRate;
        
        $controladorUrl = $this->context->link->getModuleLink($this->name, 'validate');
        $controladorUrl = $controladorUrl;
        $embeded = new PaymentOption();
        $embeded->setCallToActionText('Pagar através do PicPay')
                ->setForm("<form action='$controladorUrl'>O valor do seu pagamento será de " . Tools::displayPrice($this->context->cart->getOrderTotal() - $discount) . "</form>")
                ->setAction($controladorUrl);
        return $embeded;
    }

    // Hooks

    public function hookPaymentOptions($params)
    {
        $payment_options = [
            $this->getExternalPaymentOption($params)
        ];

        return $payment_options;
    }

    public function getCpfMapping()
    {
        return $this->cpf_mapping;
    }

    public function loadMappings()
    {
        $this->cpf_mapping = new AgColumnMapping();

        $this->cpf_mapping->addColumn('dni', 'address.dni');
        $this->cpf_mapping->addColumn('psmodcpf', 'psmodcpf');

        $this->cpf_mapping->setData(array(
            'table_name' => 'customer',
            'configuration_name' => 'agpicpay_cpf_mapping'
        ));
        $this->cpf_mapping->addColumn('djtalbrazilianregister', 'Módulo de Cadastro Brasileiro');
    }

    public function getCustomerData(Customer $customer, Address $address)
    {
        $cpf_mapping         = $this->getCpfMapping();
        if (!$cpf_mapping->isMappingEnabled()) {
            return '';
        }

        if ($cpf_mapping->getMappedField() === 'djtalbrazilianregister') {
            $sql = new DbQuery;
            $sql->from('djtalbrazilianregister')
                ->where('id_customer=' . (int)$customer->id);

            $data = Db::getInstance()->getRow($sql);
            
            $cpf = @$data['cpf'];        
        } elseif ($cpf_mapping->getMappedfield() === 'dni') {
            $sql = new DbQuery;
            $sql->from('address')
                ->select('dni')
                ->where('id_address=' . (int)$address->id);

            $cpf = Db::getInstance()->getValue($sql);
        } elseif ($cpf_mapping->getMappedfield() === 'psmodcpf') {
            $sql = new DbQuery;
            $sql->from('modulo_cpf')
                ->select('documento')
                ->where('id_customer=' . (int)$customer->id)
                ->where('tp_documento="1"');
            $cpf = Db::getInstance()->getValue($sql);
        } else {
            $sql = new DbQuery;
            $sql->from('customer')
                ->where('id_customer=' . (int)$customer->id);

            $data = Db::getInstance()->getRow($sql);
            
            $cpf = @$data[$cpf_mapping->getMappedField()];
        }

        return [
            'cpf' => $cpf
        ];
    }
}

<?php
/**
 *
 */
class AgPicPayValidateModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        parent::initContent();
        if (Tools::getValue("id_cart") != null) {
            return $this->changePaymentStatus(Tools::getValue("id_cart"));
        } else {
            $this->createDiscountForTicket();
            $this->requestPayment();
        }
    }

    public function changePaymentStatus($id_cart)
    {
        Logger::addLog("agpicpay - Recebido webhook para o carrinho de compras {$id_cart}.", 1, null, null, null, true);
        $order = Order::getByCartId($id_cart);
        Logger::addLog("agpicpay - Pedido: {$order->id}.", 1, null, null, null, true);

        $curl = curl_init();
        // Seta algumas opções
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => "https://appws.picpay.com/ecommerce/public/payments/$id_cart/status",
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-picpay-token:'. Configuration::get('PICPAYCODE')
            ]
        ]);
        // Envia a requisição e salva a resposta
        $response = json_decode(curl_exec($curl));
        $order_state = strtoupper($response->status);

        Logger::addLog("agpicpay - Estado da transação: {$response->status}.", 1, null, null, null, true);

        $new_state = (int) Configuration::get("AGPICPAY_STATUS_" . $order_state);
        if ($order->current_state != $new_state) {
            $order->setCurrentState($new_state);
        }

        // Fecha a requisição e limpa a memória
        curl_close($curl);

        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$id_cart.'&id_module='.$this->module->id.'&id_order='.$order->id.'&key='.$this->context->customer->secure_key);
    }

    public function requestPayment()
    {
        $ch = curl_init();
        $customer = $this->context->customer;
        $address = new Address($this->context->cart->id_address_invoice);

        $phone = $address->phone_mobile ?: $address->phone;
        $id_cart = $this->context->cart->id;
        $controladorUrl = $this->context->link->getModuleLink($this->module->name, 'validate');
        $true = false;
        $controladorUrl = $controladorUrl . '?&id_cart=' . $id_cart;

        $customer_data = $this->module->getCustomerData($this->context->customer, $address);

        $data_to_picpay = [
            'referenceId' => $id_cart,
            'callbackUrl' => $controladorUrl,
            'returnUrl' => $controladorUrl,
            'value' => floatval($this->context->cart->getOrderTotal()),
            'buyer' => [
                'firstName' => $customer->firstname,
                'lastName' => $customer->lastname,
                'document' => $customer_data['cpf'],
                'email' => $customer->email,
                'phone' => $phone
            ]
        ];

        if (!$data_to_picpay['buyer']['phone']) {
            $this->errors[] = 'Informe o seu número de telefone.';
            $link = $this->context->link->getPageLink('order', true, null, 'step=3');
            $this->redirectWithNotifications($link);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://appws.picpay.com/ecommerce/public/payments',
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-picpay-token: ' . Configuration::get("PICPAYCODE")
            ],
            CURLOPT_POSTFIELDS => json_encode($data_to_picpay),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
        ]);
        
        $resultado = json_decode(curl_exec($ch));
        curl_close($ch);

        if (@$resultado->paymentUrl) {
            $url = $resultado->paymentUrl;
        } else {
            $this->errors[] = 'Ocorreu um erro ao processar o seu pagamento. Se achar necessário, entre em contato com nossa equipe de atendimento ao cliente.';
            
            Logger::addLog('agpicpay - Erro processando pagamento do cliente ' . $this->context->customer->firstname . ' ' . $this->context->customer->lastname . ' - Retorno do picpay ' . json_encode($resultado), 3, null, null, null, true);

            $this->removeTicketsDiscount();
            $link = $this->context->link->getPageLink('order', true, null, 'step=3');
            $this->redirectWithNotifications($link);
        }

        $this->module->validateOrder(intval($this->context->cart->id), intval('1'), floatval($this->context->cart->getOrderTotal()), 'Pagamento via PicPay');
        Tools::redirect($url);
    }

    protected function createDiscountForTicket()
    {
        $rules = $this->context->cart->getCartRules();

        foreach ($rules as $rule) {
            if ($rule['description'] === 'Desconto PicPay') {
                dump($rule);
                return;
            }
        }

        $cart_rule = new CartRule();

        foreach (Language::getLanguages() as $lang) {
            $cart_rule->name[$lang['id_lang']] = 'Desconto PicPay';
        }

        $cart_rule->id_customer = $this->context->cart->id_customer;
        $cart_rule->date_from = date('Y-m-d H:i:s');
        $cart_rule->date_to = date('Y-m-d H:i:s', strtotime("+2 days",strtotime(date('Y-m-d'))));
        $cart_rule->description = 'Desconto PicPay';
        $cart_rule->quantity = 1;
        $cart_rule->quantity_per_user = 1;
        $cart_rule->priority = 1;
        $cart_rule->partial_use = 1;
        $cart_rule->code = md5('Desconto PicPay' .$this->context->cart->id_customer . date('Y-m-d H:i:s'));

        $cart_rule->minimum_amount = 0;
        $cart_rule->minimum_amount_tax = 0;
        $cart_rule->minimum_amount_currency = 1;
        $cart_rule->minimum_amount_shipping = 0;
        $cart_rule->country_restriction = 0;
        $cart_rule->carrier_restriction = 0;
        $cart_rule->group_restriction = 0;
        $cart_rule->cart_rule_restriction = 0;
        $cart_rule->product_restriction = 0;
        $cart_rule->shop_restriction = 0;
        $cart_rule->free_shipping = 0;

        $cart_rule->reduction_percent = Configuration::get('AGPICPAY_DISCOUNT');

        $cart_rule->reduction_tax = 1;
        $cart_rule->reduction_currency = $this->context->currency->id;
        $cart_rule->reduction_product = 0;

        $cart_rule->gift_product = 0;
        $cart_rule->gift_product_attribute = 0;
        $cart_rule->highlight = 0;
        $cart_rule->active = 1;

        $cart_rule->add();
   
        $this->context->cart->addCartRule($cart_rule->id);
        
        $this->context->cart->save();

    }

    protected function removeTicketsDiscount()
    {
        $rules = $this->context->cart->getCartRules();

        foreach ($rules as $rule) {
            if ($rule['description'] === 'Desconto PicPay') {
                $this->context->cart->removeCartRule($rule['id_cart_rule']);
            }
        }
    }
}

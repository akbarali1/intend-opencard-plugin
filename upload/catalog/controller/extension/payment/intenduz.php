<?php

class ControllerExtensionPaymentIntenduz extends Controller
{

    public function index()
    {

        $this->load->language('extension/payment/intenduz');
        $this->load->model('checkout/order');

        $order_id = $this->session->data['order_id'];

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['entry_duration'] = $this->language->get('entry_duration');
        $data['action']         = 'https://pay.intend.uz/';

        $data['api_key']      = $this->config->get('payment_intenduz_api_key');
        $data['secret_key']   = $this->config->get('payment_intenduz_secret_key');
        $data['redirect_url'] = HTTP_SERVER;

        $this->load->model('account/order');
        $orders   = $this->model_account_order->getOrderProducts($order_id);
        $products = [];
        foreach ($orders as $order) {
            $result['id']       = $order['product_id'];
            $result['price']    = $order['price'];
            $result['quantity'] = $order['quantity'];
            $result['name']     = $order['name'];
            $products[]         = $result;
        }

        $data['orders']   = $products;
        $data['order_id'] = $order_id;

        return $this->load->view('extension/payment/intenduz', $data);
    }


    public function prepare()
    {

        $errors  = [];
        $raw     = file_get_contents('php://input');
        $data    = json_decode($raw, true);
        $isError = false;
        if (!isset($data['ref_id'])) {
            $isError          = true;
            $errors['ref_id'] = "ref_id is required";
        }
        $apiKey = $this->config->get('payment_intenduz_api_key');
        if (!isset($data['api_key'])) {
            $isError           = true;
            $errors['api_key'] = "api_key is required";
        }
        if (isset($data['api_key']) && $apiKey !== $data['api_key']) {
            $isError           = true;
            $errors['api_key'] = "The API key you sent is not the same as the API key installed in Pugin";
        }
        if (!isset($data['order_id'])) {
            $isError            = true;
            $errors['order_id'] = "order_id is required";
        }
        if (!isset($data['status'])) {
            $isError          = true;
            $errors['status'] = "status is required";
        }
        if ($isError) {
            $this->response->addHeader('Content-Type: application/json');
            http_response_code(422);

            return $this->response->setOutput(json_encode([
                "status" => false,
                "errors" => $errors,
            ]));

        }
        $this->load->model('account/order');
        $this->db->query("
           INSERT INTO oc_intenduz_ipn(ref_id, status, order_id) VALUES ('".$data['ref_id']."',".$data['status'].",".$data['order_id'].");
            ");
        if ($data['status'] == 2) {
            $this->db->query('
            update '.DB_PREFIX.'order set order_status_id=5 where order_id='.$data['order_id'].'
            ');
        }

        $this->response->addHeader('Content-Type: application/json');

        return $this->response->setOutput(json_encode([
            "status"  => true,
            "message" => "success",
        ]));
    }


    public function pluginInfo()
    {
        $this->response->addHeader('Content-Type: application/json');

        $apiKey = $this->config->get("payment_intenduz_api_key");
        $secretKey = $this->config->get('payment_intenduz_secret_key');
        $this->load->model('setting/store');
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = 'INTEND'");
        $version = $query->row['version'];
//        $this->load->model('setting/modification');var_dump(1);exit();
//        $version = $this->model_setting_modification->getModificationByCode('INTEND');
        $apiKeyBody = [
            "status" => true,
            "message" => "Not empty",
        ];
        $secretKeyBody = [
            "status" => true,
            "message" => "Not empty",
        ];
        if (!$apiKey || empty($apiKey) || strlen($apiKey) < 10){
            $apiKeyBody = [
                "status" => false,
                "message" => "Api key empty",
            ];
        }
        if (!$secretKey || empty($secretKey) || strlen($secretKey) < 10){
            $secretKeyBody = [
                "status" => false,
                "message" => "Secret key empty",
            ];
        }
        return $this->response->setOutput(json_encode([
            "pluginStatus"  => $this->config->get('payment_intenduz_status') ? true : false,
            "apiKey" => $apiKeyBody,
            "secretKey" => $secretKeyBody,
            "callBackUrl" => $this->url->link("/index.php?route=extension/payment/intenduz/prepare"),
            "version" => $version,
        ]));
    }
}
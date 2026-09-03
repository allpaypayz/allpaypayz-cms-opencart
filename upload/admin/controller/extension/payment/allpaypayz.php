<?php

class ControllerExtensionPaymentAllpaypayz extends Controller
{
    /** @var array<int, string> */
    private array $error = [];

    public function index(): void
    {
        $this->load->language('extension/payment/allpaypayz');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_allpaypayz', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect(
                $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
            );
            return;
        }

        $data['heading_title'] = $this->language->get('heading_title');
        foreach (['entry_api_key', 'entry_sign_key', 'entry_base_url', 'entry_payment_method',
                  'entry_status', 'entry_order_status_success', 'entry_order_status_failed',
                  'entry_sort_order', 'button_save', 'button_cancel'] as $k) {
            $data[$k] = $this->language->get($k);
        }
        $data['error_warning'] = $this->error['warning'] ?? '';

        $data['action'] = $this->url->link(
            'extension/payment/allpaypayz',
            'user_token=' . $this->session->data['user_token'],
            true,
        );
        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=payment',
            true,
        );

        $defaults = [
            'payment_allpaypayz_api_key'              => '',
            'payment_allpaypayz_sign_key'             => '',
            'payment_allpaypayz_base_url'             => 'https://api4.allpaypayz.com',
            'payment_allpaypayz_payment_method'       => 'card',
            'payment_allpaypayz_status'               => '1',
            'payment_allpaypayz_order_status_success' => 5,
            'payment_allpaypayz_order_status_failed'  => 10,
            'payment_allpaypayz_sort_order'           => 0,
        ];
        foreach ($defaults as $key => $default) {
            $data[$key] = isset($this->request->post[$key])
                ? $this->request->post[$key]
                : ($this->config->get($key) ?? $default);
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/allpaypayz', $data));
    }

    public function install(): void
    {
        // Register the callback URL with the order status table for visibility.
    }

    public function uninstall(): void
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('payment_allpaypayz');
    }

    private function validate(): bool
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/allpaypayz')) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }
        if (empty($this->request->post['payment_allpaypayz_api_key'])) {
            $this->error['warning'] = $this->language->get('error_api_key');
            return false;
        }
        return true;
    }
}

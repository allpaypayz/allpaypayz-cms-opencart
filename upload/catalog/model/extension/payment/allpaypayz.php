<?php

class ModelExtensionPaymentAllpaypayz extends Model
{
    /** @return array<string, mixed>|array{} */
    public function getMethod(): array
    {
        $this->load->language('extension/payment/allpaypayz');
        if (!$this->config->get('payment_allpaypayz_status')) {
            return [];
        }
        return [
            'code'       => 'allpaypayz',
            'title'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_allpaypayz_sort_order'),
        ];
    }
}

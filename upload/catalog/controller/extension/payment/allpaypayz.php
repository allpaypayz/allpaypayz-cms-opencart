<?php

use Allpaypayz\Exception\AllpaypayzException;
use Allpaypayz\Exception\WebhookException;
use Allpaypayz\Allpaypayz;
use Allpaypayz\Webhooks;

class ControllerExtensionPaymentAllpaypayz extends Controller
{
    public function index(): string
    {
        $this->load->language('extension/payment/allpaypayz');
        return $this->load->view('extension/payment/allpaypayz');
    }

    /**
     * AJAX endpoint hit after the customer confirms the order. Creates a
     * redirect payment in Allpaypayz and returns the checkout URL.
     */
    public function confirm(): void
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/allpaypayz');

        $orderId = (int) ($this->session->data['order_id'] ?? 0);
        $order = $this->model_checkout_order->getOrder($orderId);
        if (!$order) {
            $this->jsonError('order_not_found');
            return;
        }

        $autoload = $this->autoloadPath();
        if (is_file($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(Allpaypayz::class)) {
            $this->jsonError('sdk_missing');
            return;
        }

        try {
            $client = new Allpaypayz(
                apiKey: (string) $this->config->get('payment_allpaypayz_api_key'),
                baseUrl: (string) $this->config->get('payment_allpaypayz_base_url'),
            );
            $payment = $client->payments->createRedirect([
                'merchant_reference' => 'OC-' . $orderId,
                'amount' => [
                    'amount_minor' => (int) round((float) $order['total'] * 100),
                    'currency'     => $order['currency_code'],
                ],
                'description'    => 'OpenCart order #' . $orderId,
                'payment_method' => (string) $this->config->get('payment_allpaypayz_payment_method'),
                'customer' => [
                    'name'  => trim(($order['firstname'] ?? '') . ' ' . ($order['lastname'] ?? '')),
                    'email' => $order['email'] ?? '',
                    'phone' => $order['telephone'] ?? '',
                ],
                'urls' => [
                    'success' => $this->url->link('checkout/success', '', true),
                    'error'   => $this->url->link('checkout/failure', '', true),
                    'callback' => $this->url->link('extension/payment/allpaypayz/webhook', '', true),
                ],
                'extra_data' => [
                    'opencart_order_id' => (string) $orderId,
                ],
            ]);
        } catch (AllpaypayzException $e) {
            $this->jsonError($e->errorCode);
            return;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode([
            'redirect' => $payment['checkout_url'] ?? null,
        ]));
    }

    /** Public webhook receiver. Configure URL in Allpaypayz. */
    public function webhook(): void
    {
        $autoload = $this->autoloadPath();
        if (is_file($autoload)) {
            require_once $autoload;
        }
        $rawBody = file_get_contents('php://input') ?: '';
        $sigHeader = $this->request->server['HTTP_CALLBACK_SIGNATURE'] ?? '';
        $signKey = (string) $this->config->get('payment_allpaypayz_sign_key');
        if ($signKey === '') {
            $this->response->addHeader('HTTP/1.1 500 sign_key_unconfigured');
            return;
        }
        try {
            $event = Webhooks::verify(
                rawBody: $rawBody,
                signatureHeader: $sigHeader,
                signKey: $signKey,
            );
        } catch (WebhookException $e) {
            $this->response->addHeader('HTTP/1.1 400 ' . $e->errorCode);
            return;
        }
        $this->applyEvent($event);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput('{}');
    }

    /** @param array<string, mixed> $event */
    private function applyEvent(array $event): void
    {
        $resource = $event['resource'] ?? null;
        $reference = is_array($resource) ? ($resource['merchant_reference'] ?? null) : null;
        if (!is_string($reference) || !preg_match('/^OC-(\d+)$/', $reference, $m)) {
            return;
        }
        $orderId = (int) $m[1];
        $type = (string) ($event['type'] ?? '');
        $statusOk = (int) $this->config->get('payment_allpaypayz_order_status_success');
        $statusFail = (int) $this->config->get('payment_allpaypayz_order_status_failed');

        $this->load->model('checkout/order');
        if (in_array($type, ['payment.succeeded', 'order.completed'], true)) {
            $this->model_checkout_order->addOrderHistory($orderId, $statusOk, 'Allpaypayz: paid', true);
            return;
        }
        if (in_array($type, ['payment.failed', 'payment.cancelled', 'order.cancelled', 'order.expired'], true)) {
            $this->model_checkout_order->addOrderHistory($orderId, $statusFail, 'Allpaypayz: ' . $type, true);
            return;
        }
    }

    private function autoloadPath(): string
    {
        return DIR_SYSTEM . 'library/allpaypayz/vendor/autoload.php';
    }

    private function jsonError(string $code): void
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['error' => $code]));
    }
}

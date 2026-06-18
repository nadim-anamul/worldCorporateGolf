<?php
/**
 * SSLCommerz Payment Gateway Service Class
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

class SSLCommerz
{
    private string $storeId;
    private string $storePassword;
    private bool $isSandbox;
    private string $apiDomain;

    public function __construct()
    {
        $this->storeId = defined('SSL_STORE_ID') ? SSL_STORE_ID : '';
        $this->storePassword = defined('SSL_STORE_PASSWORD') ? SSL_STORE_PASSWORD : '';
        $this->isSandbox = defined('SSL_IS_SANDBOX') ? SSL_IS_SANDBOX : true;
        
        $this->apiDomain = $this->isSandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    /**
     * Initiate a hosted payment session.
     *
     * @param array $params Form variables (amount, tran_id, customer details)
     * @return string Redirect URL to SSLCommerz checkout page
     * @throws RuntimeException
     */
    public function initiatePayment(array $params): string
    {
        $apiUrl = $this->apiDomain . '/gwprocess/v4/api.php';
        $base = rtrim(APP_BASE_URL, '/');

        $payload = array_merge([
            'store_id'     => $this->storeId,
            'store_passwd' => $this->storePassword,
            'success_url'  => $base . '/payment/success.php',
            'fail_url'     => $base . '/payment/fail.php',
            'cancel_url'   => $base . '/payment/cancel.php',
            'ipn_url'      => $base . '/payment/ipn.php',
        ], $params);

        $response = $this->callApi($apiUrl, $payload);
        $data = json_decode($response, true);

        if (!$data) {
            throw new RuntimeException('SSLCommerz API returned invalid JSON.');
        }

        if (($data['status'] ?? '') === 'SUCCESS' && !empty($data['GatewayPageURL'])) {
            return $data['GatewayPageURL'];
        }

        $reason = $data['failedreason'] ?? 'Unknown gateway error.';
        if (strpos($reason, 'Store Credential') !== false) {
            $reason = 'Store Credentials invalid. Check your .env config values.';
        }
        throw new RuntimeException('Payment initiation failed: ' . $reason);
    }

    /**
     * Validate the transaction status via validation endpoint
     *
     * @param string $valId Validation ID
     * @param string $tranId Transaction ID
     * @param float $expectedAmount Amount in BDT / Event Fee
     * @param string $currency Expected currency (BDT)
     * @return bool
     */
    public function validatePayment(string $valId, string $tranId, float $expectedAmount, string $currency = 'BDT'): bool
    {
        $apiUrl = $this->apiDomain . '/validator/api/validationserverAPI.php';
        
        $query = http_build_query([
            'val_id'       => $valId,
            'store_id'     => $this->storeId,
            'store_passwd' => $this->storePassword,
            'v'            => '1',
            'format'       => 'json'
        ]);

        $response = $this->callApi($apiUrl . '?' . $query, [], false);
        $data = json_decode($response, true);

        if (!$data) {
            error_log('[SSLCommerz Validation Error] Bad JSON response.');
            return false;
        }

        $status = strtoupper($data['status'] ?? '');
        if ($status !== 'VALID' && $status !== 'VALIDATED') {
            error_log('[SSLCommerz Validation Error] Transaction status: ' . $status);
            return false;
        }

        if (trim($data['tran_id'] ?? '') !== trim($tranId)) {
            error_log('[SSLCommerz Validation Error] Transaction ID mismatch.');
            return false;
        }

        $returnedAmount = (float)($currency === 'BDT' ? ($data['amount'] ?? 0) : ($data['currency_amount'] ?? 0));
        if (abs($returnedAmount - $expectedAmount) >= 1) {
            error_log('[SSLCommerz Validation Error] Amount mismatch. Expected ' . $expectedAmount . ', got ' . $returnedAmount);
            return false;
        }

        return true;
    }

    /**
     * Verify the md5 signature of an IPN callback payload.
     *
     * @param array $postData Payload post variables
     * @return bool
     */
    public function validateIpnHash(array $postData): bool
    {
        if (!isset($postData['verify_sign'], $postData['verify_key'])) {
            return false;
        }

        $keys = explode(',', $postData['verify_key']);
        $newData = [];
        foreach ($keys as $key) {
            if (isset($postData[$key])) {
                $newData[$key] = $postData[$key];
            }
        }
        $newData['store_passwd'] = md5($this->storePassword);
        ksort($newData);

        $hashStr = '';
        foreach ($newData as $k => $v) {
            $hashStr .= $k . '=' . $v . '&';
        }

        return md5(rtrim($hashStr, '&')) === $postData['verify_sign'];
    }

    /**
     * Execute HTTP request using cURL
     */
    private function callApi(string $url, array $fields = [], bool $isPost = true): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($this->isSandbox) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }

        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Gateway connection failed: ' . $err);
        }

        if ($code !== 200) {
            throw new RuntimeException('Gateway HTTP error code: ' . $code);
        }

        return $response;
    }
}

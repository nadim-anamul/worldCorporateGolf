<?php
/**
 * Configurable SMS Dispatcher Gateway
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

class SMSGateway
{
    /**
     * Dispatch SMS to a contact number.
     *
     * @param string $phone Customer phone number
     * @param string $playerName Customer name
     * @param string $teeTime Selected schedule group details
     * @return array Status and response
     */
    public static function send(string $phone, string $playerName, string $teeTime, string $eventName = EVENT_NAME): array
    {
        // Check if configuration exists
        if (defined('SMS_API_URL') && SMS_API_URL === '') {
            return ['status' => 'error', 'message' => 'SMS Gateway is not configured.'];
        }

        // Sanitize phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if ($phone === '') {
            return ['status' => 'error', 'message' => 'Invalid phone number.'];
        }

        // Build message from template
        $template = defined('SMS_MESSAGE_TEMPLATE') ? SMS_MESSAGE_TEMPLATE : '';
        if ($template === '') {
            $template = "Dear %PLAYER_NAME%, your %EVENT_NAME% registration is confirmed. Preferred time: %TEE_TIME%. Please arrive 30 minutes early. - GolfHouse";
        }

        $message = str_replace(
            ['%PLAYER_NAME%', '%EVENT_NAME%', '%TEE_TIME%'],
            [$playerName, $eventName, $teeTime],
            $template
        );

        $data = [
            'to'      => $phone,
            'message' => $message,
            'token'   => SMS_API_TOKEN
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, SMS_API_URL);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                error_log('[SMS Gateway Error] cURL error: ' . $err);
                return ['status' => 'error', 'message' => 'Failed to reach SMS server.'];
            }

            if ($httpCode !== 200) {
                error_log('[SMS Gateway Error] HTTP status: ' . $httpCode);
                return ['status' => 'error', 'message' => 'SMS gateway returned error status.'];
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If it returned non-JSON, log and return success if output has status/success indicators
                if (stripos($response, 'success') !== false || stripos($response, 'sent') !== false) {
                    return ['status' => 'success', 'raw' => $response];
                }
                error_log('[SMS Gateway Error] Non-JSON response: ' . $response);
                return ['status' => 'error', 'message' => 'SMS API responded with invalid format.', 'raw' => $response];
            }

            return ['status' => 'success', 'data' => $result];
        } catch (Throwable $e) {
            error_log('[SMS Gateway Exception] ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'SMS sending failed.'];
        }
    }
}

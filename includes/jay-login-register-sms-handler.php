<?php
if (!defined('ABSPATH')) exit; 

/**
 * Class JayRelogSmsHandler
 * Handles SMS sending logic for various providers.
 */
class JayRelogSmsHandler {

    /** @var array Plugin settings */
    private $settings = [];

    /** @var JayRelogSmsHandler|null Singleton instance */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->settings = get_option('jay_login_register_settings', []);
    }

    /**
     * Get the singleton instance.
     *
     * @return JayRelogSmsHandler
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sends an OTP SMS using the configured provider.
     * Called via the 'jay_relog_send_otp' filter hook.
     *
     * @param WP_Error|bool $default_result Default value passed by the filter.
     * @param string $mobile_number The recipient's mobile number (e.g., 0912...).
     * @param string|int $otp_code The OTP code to send.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function send_otp($default_result, $mobile_number, $otp_code) {
        $provider = $this->settings['sms_provider'] ?? 'ipanel';
        $normalized_phone = jay_login_register_normalize_numbers($mobile_number); // Assuming helper exists globally

        // Basic validation
        if (empty($normalized_phone) || !preg_match('/^09[0-9]{9}$/', $normalized_phone)) {
             return new WP_Error('invalid_mobile', 'شماره موبایل نامعتبر است.');
        }
        if (empty($otp_code)) {
            return new WP_Error('invalid_otp', 'کد OTP نمی‌تواند خالی باشد.');
        }


        switch ($provider) {
            case 'kavenegar':
                return $this->send_via_kavenegar($normalized_phone, $otp_code);
            case 'smsir':
                return $this->send_via_smsir($normalized_phone, $otp_code);
            case 'raygansms':
                return $this->send_via_raygansms($normalized_phone, $otp_code);
            case 'melipayamak':
                return $this->send_via_melipayamak($normalized_phone, $otp_code);
            case 'farazsms':
                return $this->send_via_farazsms($normalized_phone, $otp_code);
            case 'ipanel':
            case 'modirpayamak':
            case 'tabansms':
            default:
                return $this->send_via_ipanel($normalized_phone, $otp_code, $provider);
        }
    }

    // --- Private Sending Methods for Each Provider ---


    private function send_via_ipanel($mobile_number, $otp_code, $provider) {
         $api_key = $this->settings['ipanel_api_key'] ?? '';
         $pattern_code = $this->settings['ipanel_pattern_code'] ?? '';
         $sender_line = $this->settings['ipanel_sender_line'] ?? '';
         $pattern_variable = $this->settings['ipanel_pattern_variable'] ?? 'code'; // Read variable name

         if ( empty($api_key) || empty($pattern_code) || empty($sender_line) || empty($pattern_variable) ) {
             return new WP_Error('ipanel_config_error', 'لطفاً کلید API، کد پترن، خط ارسال کننده و نام متغیر پترن را برای iPanel/ModirPayamak/TabanSMS در تنظیمات تکمیل کنید.');
         }

         // --- CORRECT Endpoint based on new documentation ---
         $url = "https://edge.ippanel.com/v1/api/send";

         // Prepare numbers in E.164 format for the API
         $recipient_e164 = '+98' . substr($mobile_number, -10);
         $sender_e164 = '+98' . ltrim($sender_line, '0');

         // --- CORRECT Data structure based on "Send Pattern SMS" documentation ---
         $data = [
             'sending_type' => 'pattern',        // Specify pattern sending type
             'from_number'  => $sender_e164,     // Sender number
             'code'         => $pattern_code,    // The pattern code itself
             'recipients'   => [$recipient_e164], // Recipient must be an array with one element
             'params'       => [                 // Parameters object
                 // Use the configured variable name as the key
                 $pattern_variable => (string) $otp_code
             ]
         ];

         // --- CORRECT Headers ---
         $args = [
             'body'      => json_encode($data),
             'headers'   => [
                 'Content-Type'  => 'application/json',
                 'Accept'        => 'application/json',
                 'Authorization' => $api_key // API Key directly in Authorization header
             ],
             'timeout'   => 15,
         ];

         $response = wp_remote_post($url, $args);

         // --- Error Handling (Remains mostly the same, adjusted slightly for clarity) ---
         if (is_wp_error($response)) {
             if (strpos($response->get_error_message(), 'Could not resolve host') !== false) {
                 return new WP_Error('ipanel_host_error', 'خطا در اتصال به سرور iPanel: آدرس API جدید (edge.ippanel.com) یافت نشد.');
             }
             return new WP_Error('ipanel_connection_error', 'خطای اتصال به سرور iPanel: ' . $response->get_error_message());
         }

         $response_code = wp_remote_retrieve_response_code($response);
         $body = wp_remote_retrieve_body($response);
         $result = json_decode($body, true);

         // Check success based on new API structure ('status'/'message' in 'meta')
         if ($response_code >= 200 && $response_code < 300 && isset($result['meta']['status']) && $result['meta']['status'] === true) {
             // Check if message_outbox_ids exists and is not empty for pattern sends
             if (!empty($result['data']['message_outbox_ids'])) {
                return true; // Success
             } else {
                // It's possible the API returns success but no ID if something went wrong internally
                return new WP_Error('ipanel_api_warning', 'پاسخ موفقیت آمیز از iPanel دریافت شد اما شناسه پیامک وجود نداشت.');
             }
         } else {
             // Try to get error message from the new 'meta' structure
             $error_message = $result['meta']['message'] ?? ($result['message'] ?? 'خطای ناشناس از API iPanel.');
             $error_code = $result['meta']['message_code'] ?? $response_code;

             // Check for Authentication Error (401) specifically
             if ($response_code == 401) {
                $error_message = "خطای احراز هویت با API iPanel. لطفاً کلید API را بررسی کنید و مطمئن شوید برای API جدید (edge) معتبر است. ({$error_code})";
             } elseif (isset($result['meta']['status']) && $result['meta']['status'] === false) {
                $error_message = "خطای API iPanel ({$error_code}): " . $error_message;
                // Include validation errors if available
                if (!empty($result['meta']['errors']) && is_array($result['meta']['errors'])) {
                    $validation_errors = [];
                    foreach ($result['meta']['errors'] as $field => $field_errors) {
                        $validation_errors[] = $field . ': ' . implode(', ', $field_errors);
                    }
                    $error_message .= ' جزئیات: ' . implode('; ', $validation_errors);
                }
             } else {
                 $error_message = "خطای غیرمنتظره از API iPanel (کد: {$response_code}). پاسخ: " . esc_html(wp_strip_all_tags($body));
             }
             return new WP_Error('ipanel_api_error', $error_message);
         }
    } 
    
    private function send_via_kavenegar($mobile_number, $otp_code) {
        $api_key = $this->settings['kavenegar_api_key'] ?? '';
        $use_voice = !empty($this->settings['kavenegar_use_voice']) && $this->settings['kavenegar_use_voice'] === 'yes';

        if ($use_voice) {
            $template = $this->settings['kavenegar_voice_template'] ?? '';
            if (empty($template)) {
                return new WP_Error('kavenegar_config_error', 'لطفاً نام قالب صوتی کاوه نگار را در تنظیمات تکمیل کنید.');
            }
        } else {
            $template = $this->settings['kavenegar_template'] ?? '';
            if (empty($template)) {
                return new WP_Error('kavenegar_config_error', 'لطفاً نام قالب پیامکی کاوه نگار را در تنظیمات تکمیل کنید.');
            }
        }

        if (empty($api_key)) {
            return new WP_Error('kavenegar_config_error', 'لطفاً کلید API کاوه نگار را در تنظیمات تکمیل کنید.');
        }

        $url = 'https://api.kavenegar.com/v1/' . $api_key . '/verify/lookup.json';

        $body_params = [
            'receptor' => $mobile_number, // Kavenegar accepts 09... format
            'template' => $template,
            'token'    => $otp_code, // Main token
            // Kavenegar supports token2, token3, etc. if needed by template
            // 'token2'   => '...',
            // 'token3'   => '...',
        ];

        if ($use_voice) {
            $body_params['type'] = 'call';
        }

        $args = ['body' => $body_params, 'timeout' => 15];
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('kavenegar_connection_error', 'خطای اتصال به سرور کاوه نگار: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body);

        if (isset($result->return->status) && $result->return->status == 200) {
            return true;
        } else {
            $error_message = $result->return->message ?? 'خطای ناشناس از API کاوه نگار.';
            $error_code = $result->return->status ?? 'unknown';
            return new WP_Error('kavenegar_api_error', "خطای API کاوه نگار ({$error_code}): " . $error_message);
        }
    }

    private function send_via_smsir($mobile_number, $otp_code) {
         $api_key = $this->settings['smsir_api_key'] ?? '';
         $template_id = $this->settings['smsir_template_id'] ?? '';
         $param_name = $this->settings['smsir_parameter_name'] ?? 'Code'; // Default param name

         if ( empty($api_key) || empty($template_id) ) {
             return new WP_Error('smsir_config_error', 'لطفاً کلید API و شناسه الگو را برای SMS.ir در تنظیمات تکمیل کنید.');
         }

         $url = 'https://api.sms.ir/v1/send/verify';
         $mobile_number_for_api = substr($mobile_number, 1); // Remove leading 0 for 912...

         $data = [
             'mobile' => $mobile_number_for_api,
             'templateId' => (int) $template_id,
             'parameters' => [
                 [
                     'name' => $param_name,
                     'value' => (string) $otp_code, // Ensure OTP is string
                 ]
                 // Add more parameters here if your template requires them
                 // [ 'name' => 'Param2Name', 'value' => 'Param2Value' ]
             ]
         ];

         $args = [
             'body' => json_encode($data),
             'headers' => [
                 'Content-Type' => 'application/json',
                 'Accept' => 'application/json',
                 'x-api-key' => $api_key,
             ],
             'timeout' => 15,
         ];

         $response = wp_remote_post($url, $args);

         if (is_wp_error($response)) {
             return new WP_Error('smsir_connection_error', 'خطای اتصال به سرور SMS.ir: ' . $response->get_error_message());
         }

         $body = wp_remote_retrieve_body($response);
         $result = json_decode($body);

         if (isset($result->status) && $result->status == 1) { // Status 1 means success for SMS.ir
             return true;
         } else {
             $error_message = $result->message ?? 'خطای ناشناس از API SMS.ir.';
             $error_code = $result->status ?? 'unknown';
             return new WP_Error('smsir_api_error', "خطای API SMS.ir ({$error_code}): " . $error_message);
         }
    }

    private function send_via_raygansms($mobile_number, $otp_code) {
        $access_hash = $this->settings['raygansms_access_hash'] ?? '';
        $pattern_id = $this->settings['raygansms_pattern_id'] ?? '';
        $token_name = $this->settings['raygansms_token_name'] ?? 'token1'; // Default token name

        if ( empty($access_hash) || empty($pattern_id) ) {
            return new WP_Error('raygansms_config_error', 'لطفاً کد دسترسی و شناسه الگو را برای RayganSMS در تنظیمات تکمیل کنید.');
        }

        $url = 'https://smspanel.trez.ir/SendPatternCodeWithUrl.ashx';

        $data = [
            'AccessHash' => $access_hash,
            'Mobile' => $mobile_number, // Accepts 09... format
            'PatternId' => $pattern_id,
            $token_name => (string) $otp_code, // Use the configured token name
            // Add other tokens like token2, token3 if needed by the pattern
        ];

        $args = [
            'body' => $data, // Uses x-www-form-urlencoded
            'timeout' => 15,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error('raygansms_connection_error', 'خطای اتصال به سرور RayganSMS: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        // According to docs, success is a numeric value >= 2000
        if (is_numeric($body) && intval($body) >= 2000) {
            return true;
        } else {
            // Try to interpret known error codes or show the raw response
            $error_message = $this->get_raygansms_error_message($body);
            return new WP_Error('raygansms_api_error', "خطای API RayganSMS: " . $error_message);
        }
    }

    private function send_via_melipayamak($mobile_number, $otp_code) {
         $username = $this->settings['melipayamak_username'] ?? '';
         $password = $this->settings['melipayamak_password'] ?? '';
         $body_id  = $this->settings['melipayamak_body_id'] ?? '';

         if ( empty($username) || empty($password) || empty($body_id) ) {
             return new WP_Error('melipayamak_config_error', 'لطفاً نام کاربری، رمز عبور و کد متن را برای ملی پیامک در تنظیمات تکمیل کنید.');
         }

         $url = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';

         $data = [
             'username' => $username,
             'password' => $password,
             'text'     => (string) $otp_code, // The OTP code is the 'text'
             'to'       => $mobile_number, // Accepts 09... format
             'bodyId'   => (int) $body_id,
         ];

         $args = [
             'body'    => http_build_query($data), // Uses x-www-form-urlencoded
             'headers' => [
                 'Content-Type' => 'application/x-www-form-urlencoded',
             ],
             'timeout' => 15,
         ];

         $response = wp_remote_post($url, $args);

         if (is_wp_error($response)) {
             return new WP_Error('melipayamak_connection_error', 'خطای اتصال به سرور ملی پیامک: ' . $response->get_error_message());
         }

         $body = wp_remote_retrieve_body($response);
         $result = json_decode($body, true); // Decode as associative array

         // Check if JSON decoding was successful and RetStatus is 1 (success)
         if ( is_array($result) && isset($result['RetStatus']) && $result['RetStatus'] === 1 ) {
             return true;
         } else {
             // Get the error code (Value) or the raw body if JSON fails
             $error_code = $result['Value'] ?? $body;
             $error_message = $this->get_melipayamak_error_message($error_code); // Use helper method
             return new WP_Error('melipayamak_api_error', "خطای API ملی پیامک: " . $error_message);
         }
    }
    
    private function send_via_farazsms($mobile_number, $otp_code) {
         $api_key = $this->settings['farazsms_api_key'] ?? '';
         $pattern_code = $this->settings['farazsms_pattern_code'] ?? '';
         $sender_line = $this->settings['farazsms_sender_line'] ?? '';
         $pattern_variable = $this->settings['farazsms_pattern_variable'] ?? 'code'; // Read variable name

         if ( empty($api_key) || empty($pattern_code) || empty($sender_line) || empty($pattern_variable) ) {
             return new WP_Error('ipanel_config_error', 'لطفاً کلید API، کد پترن، خط ارسال کننده و نام متغیر پترن را برای iPanel/ModirPayamak/TabanSMS در تنظیمات تکمیل کنید.');
         }

         // --- CORRECT Endpoint based on new documentation ---
         $url = "https://edge.ippanel.com/v1/api/send";

         // Prepare numbers in E.164 format for the API
         $recipient_e164 = '+98' . substr($mobile_number, -10);
         $sender_e164 = '+98' . ltrim($sender_line, '0');

         // --- CORRECT Data structure based on "Send Pattern SMS" documentation ---
         $data = [
             'sending_type' => 'pattern',        // Specify pattern sending type
             'from_number'  => $sender_e164,     // Sender number
             'code'         => $pattern_code,    // The pattern code itself
             'recipients'   => [$recipient_e164], // Recipient must be an array with one element
             'params'       => [                 // Parameters object
                 // Use the configured variable name as the key
                 $pattern_variable => (string) $otp_code
             ]
         ];

         // --- CORRECT Headers ---
         $args = [
             'body'      => json_encode($data),
             'headers'   => [
                 'Content-Type'  => 'application/json',
                 'Accept'        => 'application/json',
                 'Authorization' => $api_key // API Key directly in Authorization header
             ],
             'timeout'   => 15,
         ];

         $response = wp_remote_post($url, $args);

         // --- Error Handling (Remains mostly the same, adjusted slightly for clarity) ---
         if (is_wp_error($response)) {
             if (strpos($response->get_error_message(), 'Could not resolve host') !== false) {
                 return new WP_Error('ipanel_host_error', 'خطا در اتصال به سرور iPanel: آدرس API جدید (edge.ippanel.com) یافت نشد.');
             }
             return new WP_Error('ipanel_connection_error', 'خطای اتصال به سرور iPanel: ' . $response->get_error_message());
         }

         $response_code = wp_remote_retrieve_response_code($response);
         $body = wp_remote_retrieve_body($response);
         $result = json_decode($body, true);

         // Check success based on new API structure ('status'/'message' in 'meta')
         if ($response_code >= 200 && $response_code < 300 && isset($result['meta']['status']) && $result['meta']['status'] === true) {
             // Check if message_outbox_ids exists and is not empty for pattern sends
             if (!empty($result['data']['message_outbox_ids'])) {
                return true; // Success
             } else {
                // It's possible the API returns success but no ID if something went wrong internally
                return new WP_Error('ipanel_api_warning', 'پاسخ موفقیت آمیز از iPanel دریافت شد اما شناسه پیامک وجود نداشت.');
             }
         } else {
             // Try to get error message from the new 'meta' structure
             $error_message = $result['meta']['message'] ?? ($result['message'] ?? 'خطای ناشناس از API iPanel.');
             $error_code = $result['meta']['message_code'] ?? $response_code;

             // Check for Authentication Error (401) specifically
             if ($response_code == 401) {
                $error_message = "خطای احراز هویت با API iPanel. لطفاً کلید API را بررسی کنید و مطمئن شوید برای API جدید (edge) معتبر است. ({$error_code})";
             } elseif (isset($result['meta']['status']) && $result['meta']['status'] === false) {
                $error_message = "خطای API iPanel ({$error_code}): " . $error_message;
                // Include validation errors if available
                if (!empty($result['meta']['errors']) && is_array($result['meta']['errors'])) {
                    $validation_errors = [];
                    foreach ($result['meta']['errors'] as $field => $field_errors) {
                        $validation_errors[] = $field . ': ' . implode(', ', $field_errors);
                    }
                    $error_message .= ' جزئیات: ' . implode('; ', $validation_errors);
                }
             } else {
                 $error_message = "خطای غیرمنتظره از API iPanel (کد: {$response_code}). پاسخ: " . esc_html(wp_strip_all_tags($body));
             }
             return new WP_Error('ipanel_api_error', $error_message);
         }  
        }
    
    /**
     * Get error message for Melipayamak API based on code.
     * (Moved from helpers.php)
     *
     * @param string|int $code Error code.
     * @return string Error message.
     */
    private function get_melipayamak_error_message( $code ) {
         $errors = [
             '0'  => 'نام کاربری یا رمزعبور صحیح نمی‌باشد',
             '2'  => 'اعتبار کافی نمی‌باشد',
             '6'  => 'سامانه درحال بروزرسانی می‌باشد',
             '7'  => 'متن حاوی کلمه فیلتر شده می‌باشد',
             '10' => 'کاربر موردنظر فعال نمی‌باشد',
             '11' => 'ارسال نشده',
             '12' => 'مدارک کاربر کامل نمی‌باشد',
             '16' => 'شماره گیرنده ای یافت نشد',
             '17' => 'متن پیامک خالی می باشد',
             '18' => 'شماره گیرنده نامعتبر است',
             '-1' => 'دسترسی برای استفاده از این وبسرویس غیرفعال است',
             '-2' => 'محدودیت تعداد شماره (فقط یک شماره مجاز است)',
             '-4' => 'کد متن (Body ID) ارسالی صحیح نمی‌باشد و یا تایید نشده است',
             '-5' => 'متن ارسالی با متغیرهای الگو همخوانی ندارد',
             '-6' => 'خطای داخلی رخ داده است',
             // Add more known codes if needed
         ];
         $code_str = (string) $code; // Ensure it's a string for array key lookup
         return $errors[$code_str] ?? 'خطای تعریف نشده با کد ' . esc_html($code);
    }

    /**
     * Get error message for RayganSMS API based on response body.
     *
     * @param string $response_body Raw response body.
     * @return string Error message.
     */
    private function get_raygansms_error_message( $response_body ) {
        // Add known error codes/strings for RayganSMS here if available
        $known_errors = [
            '-1' => 'کد دسترسی نامعتبر',
            '-2' => 'شناسه الگو نامعتبر',
            // Add more...
        ];
         $response_body_str = (string) $response_body;
        if (isset($known_errors[$response_body_str])) {
            return $known_errors[$response_body_str];
        }
        return !empty($response_body) ? esc_html($response_body) : 'خطای ناشناس از API RayganSMS.';
    }


} // End Class JayRelogSmsHandler

/**
 * Get the singleton instance of the Sms Handler.
 *
 * @return JayRelogSmsHandler
 */
function jay_relog_sms_handler() {
    return JayRelogSmsHandler::get_instance();
}

<?php
if (!defined('ABSPATH')) exit; 

/**
 * Class JayRelogCaptchaHandler
 * Handles captcha display and verification logic.
 */
class JayRelogCaptchaHandler {

    /** @var array Captcha settings */
    private $settings = [];

    /** @var JayRelogCaptchaHandler|null Singleton instance */
    private static $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->settings = get_option('jay_login_register_settings', []);
    }

    /**
     * Get the singleton instance.
     *
     * @return JayRelogCaptchaHandler
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the active captcha type.
     *
     * @return string 'none', 'math', 'honeypot', or 'recaptcha_v3'.
     */
    public function get_captcha_type() {
        return $this->settings['captcha_type'] ?? 'none';
    }

    /**
     * Display the appropriate captcha field based on settings.
     * Called via the 'jay_relog_display_captcha' action hook.
     */
    public function display_field() {
        $type = $this->get_captcha_type();

        switch ($type) {
            case 'math':
                $this->display_math_captcha();
                break;
            case 'honeypot':
                $this->display_honeypot_captcha();
                break;
            case 'recaptcha_v3':
                $this->display_recaptcha_v3_placeholder();
                break;
            // case 'none':
            default:
                // No field needed for 'none'
                break;
        }
    }

    /**
     * Verify the captcha submission.
     * Called via the 'jay_relog_verify_captcha' action hook wrapper.
     *
     * @param array $post_data The $_POST data.
     * @param string $nonce_action The expected nonce action.
     * @param string $nonce_name The expected nonce field name.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function verify_submission(array $post_data, string $nonce_action, string $nonce_name) {
        // 1. Verify Nonce first 
        if (!isset($post_data[$nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($post_data[$nonce_name])), $nonce_action)) {
            return new WP_Error('nonce_error', 'خطای امنیتی: درخواست نامعتبر است.');
        }

        // 2. Verify Captcha based on type
        $type = $this->get_captcha_type();
        $result = true; // Assume success initially

        switch ($type) {
            case 'math':
                $result = $this->verify_math_captcha($post_data);
                break;
            case 'honeypot':
                $result = $this->verify_honeypot_captcha($post_data);
                break;
            case 'recaptcha_v3':
                $result = $this->verify_recaptcha_v3($post_data);
                break;
            // case 'none':
            default:
                // Always true if captcha is disabled
                break;
        }

        return $result;
    }

    // --- Private Helper Methods for Display ---

    private function display_math_captcha() {
        $question = $this->generate_math_captcha(); // Generate and store answer in session
        ?>
        <div class="jay-login-register-captcha-field" id="math-captcha-wrapper">
             <label for="jay_login_register_math_captcha"><?php echo esc_html($question); ?></label>
             <input type="text" name="jay_login_register_math_captcha" class="jay-login-register-input" inputmode="numeric" required autocomplete="off">
         </div>
        <?php
    }

    private function display_honeypot_captcha() {
        ?>
        <div class="jay-login-register-honeypot-field" style="opacity:0; position:absolute; top:0; left:0; height:0; width:0; z-index: -1;">
            <label for="user_email_confirm_hp">لطفاً این فیلد را خالی بگذارید</label>
            <input type="text" name="user_email_confirm_hp" id="user_email_confirm_hp" value="" autocomplete="off" tabindex="-1">
        </div>
        <input type="hidden" name="form_load_time_hp" value="<?php echo esc_attr( time() ); ?>">
        <?php
        // Note: Renamed fields slightly (_hp suffix) to avoid potential conflicts if old code remains somewhere.
    }

    private function display_recaptcha_v3_placeholder() {
        // JavaScript will populate this field
        echo '<input type="hidden" name="recaptcha_v3_token" id="recaptcha_v3_token" value="">';
    }

    // --- Private Helper Methods for Verification ---

    private function verify_math_captcha(array $post_data) {
        if (!session_id()) {
            @session_start();
        }

        if (!isset($_SESSION['jay_login_register_math_captcha_answer']) || !isset($post_data['jay_login_register_math_captcha'])) {
            return new WP_Error('math_captcha_missing', 'پاسخ کپچا نامعتبر است. لطفاً صفحه را رفرش کنید.');
        }

        $correct_answer = absint($_SESSION['jay_login_register_math_captcha_answer']);
        $user_answer = isset($post_data['jay_login_register_math_captcha']) ? absint(jay_login_register_normalize_numbers($post_data['jay_login_register_math_captcha'])) : '';
        $user_ip = jay_login_register_get_user_ip(); // Assuming this helper function still exists globally

        unset($_SESSION['jay_login_register_math_captcha_answer']); // Consume the answer

        if ($user_answer !== $correct_answer) {
             // --- Lockout Logic ---
             $max_retries = intval($this->settings['otp_max_retries'] ?? 3); // Use captcha settings if available, else OTP's
             $lockout_duration = intval($this->settings['otp_lockout_duration'] ?? 15);
             $math_block_transient = 'jay_login_register_math_block_' . $user_ip;
             $math_fail_count_transient = 'jay_login_register_math_fail_count_' . $user_ip;

             // Check if already blocked
             if (get_transient($math_block_transient)) {
                 $expiration_time = get_option('_transient_timeout_' . $math_block_transient);
                 $remaining_seconds = $expiration_time ? max(0, $expiration_time - time()) : 0;
                 return new WP_Error('math_captcha_locked', "شما به دلیل تلاش‌های ناموفق زیاد، به طور موقت مسدود شده‌اید.", ['lockout_timer' => $remaining_seconds]);
             }

             $fail_count = (int) get_transient($math_fail_count_transient) + 1;

             if ($fail_count >= $max_retries) {
                 set_transient($math_block_transient, 'blocked', $lockout_duration * MINUTE_IN_SECONDS);
                 delete_transient($math_fail_count_transient);
                 $expiration_time = time() + ($lockout_duration * MINUTE_IN_SECONDS);
                 $remaining_seconds = max(0, $expiration_time - time());
                 return new WP_Error('math_captcha_locked', "شما به دلیل تلاش‌های ناموفق زیاد، به طور موقت مسدود شده‌اید.", ['lockout_timer' => $remaining_seconds]);
             } else {
                 set_transient($math_fail_count_transient, $fail_count, $lockout_duration * MINUTE_IN_SECONDS);
                 $remaining_tries = $max_retries - $fail_count;
                 $new_question = $this->generate_math_captcha(); // Generate a new question
                 // Need a way to pass the new question back to JS via WP_Error data
                 return new WP_Error('math_captcha_incorrect', "پاسخ سوال ریاضی اشتباه است. شما {$remaining_tries} تلاش دیگر دارید.", ['new_math_question' => $new_question]);
             }
             // --- End Lockout Logic ---
        }

        // Success, clear fail count
        delete_transient('jay_login_register_math_fail_count_' . $user_ip);
        return true;
    }

    private function verify_honeypot_captcha(array $post_data) {
        // Check the honeypot field
        if (!empty($post_data['user_email_confirm_hp'])) {
            return new WP_Error('honeypot_filled', 'خطای امنیتی: تشخیص ربات.');
        }

        // Check the time trap
        $min_time = 3; // Minimum time in seconds
        if (isset($post_data['form_load_time_hp'])) {
            $load_time = absint($post_data['form_load_time_hp']);
            if ((time() - $load_time) < $min_time) {
                return new WP_Error('honeypot_too_fast', 'خطای امنیتی: تشخیص ربات.');
            }
        } else {
            // If the time field is missing, it might indicate tampering
            return new WP_Error('honeypot_no_time', 'خطای امنیتی: فرم ناقص.');
        }

        return true;
    }

    private function verify_recaptcha_v3(array $post_data) {
        $secret_key = $this->settings['recaptcha_secret_key'] ?? '';
        $token = isset($post_data['recaptcha_v3_token']) ? sanitize_text_field(wp_unslash($post_data['recaptcha_v3_token'])) : '';

        if (empty($secret_key) || empty($token)) {
            return new WP_Error('recaptcha_config_error', 'پیکربندی کپچا در سایت ناقص است. لطفاً به مدیر اطلاع دهید.');
        }

        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $response = wp_remote_post($verify_url, [
            'body' => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => jay_login_register_get_user_ip() // Assuming helper exists
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('recaptcha_connection_error', 'خطا در ارتباط با سرور کپچا: ' . $response->get_error_message());
        }

        $result = json_decode(wp_remote_retrieve_body($response));

        if (!$result || !isset($result->success)) {
             return new WP_Error('recaptcha_invalid_response', 'پاسخ نامعتبر از سرور کپچا دریافت شد.');
        }

        // Check success and score (score threshold can be adjusted)
        if (!$result->success || $result->score < 0.5) {
            // You might want to log $result->{'error-codes'} here for debugging
            return new WP_Error('recaptcha_verification_failed', 'اعتبارسنجی کپچا ناموفق بود. به نظر می‌رسد شما یک ربات باشید.');
        }

        return true;
    }

    // --- Math Captcha Generation (Moved from helpers) ---

    /**
     * Generates a math captcha question and stores the answer in the session.
     *
     * @return string The math question (e.g., "5 + 3 = ?").
     */
    public function generate_math_captcha() {
        if ( ! session_id() ) {
            @session_start();
        }

        $num1 = wp_rand(1, 9);
        $num2 = wp_rand(1, 9);
        $operators = ['+', '-', '*', '/'];
        $operator = $operators[array_rand($operators)];
        $answer = 0;

        switch ($operator) {
            case '+':
                $answer = $num1 + $num2;
                break;
            case '*':
                // Limit numbers for multiplication to keep answer reasonable
                $num1 = wp_rand(1, 5);
                $num2 = wp_rand(1, 5);
                $answer = $num1 * $num2;
                break;
            case '-':
                // Ensure result isn't negative
                if ($num1 < $num2) {
                    list($num1, $num2) = [$num2, $num1]; // Swap numbers
                }
                $answer = $num1 - $num2;
                break;
            case '/':
                // Ensure integer division
                $answer = wp_rand(2, 5); // The result
                $num2   = wp_rand(2, 5); // The divisor
                $num1   = $answer * $num2; // Calculate the dividend
                break;
        }

        $_SESSION['jay_login_register_math_captcha_answer'] = $answer;

        // Use multiplication/division symbols for display
        $display_operator = $operator;
        if ($operator === '*') {
            $display_operator = '×';
        } elseif ($operator === '/') {
            $display_operator = '÷';
        }

        // Return the question string
        return "{$num1} {$display_operator} {$num2} = ؟";
    }
} // End Class JayRelogCaptchaHandler

/**
 * Get the singleton instance of the Captcha Handler.
 * Helper function for easier access.
 *
 * @return JayRelogCaptchaHandler
 */
function jay_relog_captcha_handler() {
    return JayRelogCaptchaHandler::get_instance();
}

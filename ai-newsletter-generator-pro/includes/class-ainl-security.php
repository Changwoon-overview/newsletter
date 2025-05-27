<?php
/**
 * 보안 및 권한 관리 클래스
 * 플러그인의 보안을 강화하고 사용자 권한을 관리합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Security {
    
    /**
     * 허용된 사용자 역할 및 권한
     */
    const ALLOWED_CAPABILITIES = array(
        'manage_options',           // 관리자 전용
        'edit_posts',              // 편집자 이상
        'publish_posts'            // 작성자 이상
    );
    
    /**
     * 보안 액션별 필요 권한
     */
    const ACTION_CAPABILITIES = array(
        'manage_settings' => 'manage_options',
        'manage_campaigns' => 'edit_posts',
        'manage_subscribers' => 'edit_posts',
        'manage_templates' => 'edit_posts',
        'view_statistics' => 'edit_posts',
        'send_newsletters' => 'publish_posts',
        'export_data' => 'manage_options',
        'import_data' => 'edit_posts'
    );
    
    /**
     * 생성자 - 보안 훅 초기화
     */
    public function __construct() {
        add_action('init', array($this, 'init_security'));
        add_action('admin_init', array($this, 'admin_security_check'));
        add_filter('wp_die_handler', array($this, 'custom_die_handler'));
    }
    
    /**
     * 보안 시스템 초기화
     */
    public function init_security() {
        // CSRF 보호를 위한 nonce 필드 자동 추가
        add_action('wp_ajax_ainl_action', array($this, 'verify_ajax_nonce'));
        add_action('wp_ajax_nopriv_ainl_action', array($this, 'deny_unauthorized_ajax'));
        
        // 파일 업로드 보안
        add_filter('upload_mimes', array($this, 'restrict_upload_mimes'));
        add_filter('wp_handle_upload_prefilter', array($this, 'validate_file_upload'));
    }
    
    /**
     * 관리자 보안 체크
     */
    public function admin_security_check() {
        // 관리자 페이지에서만 실행
        if (!is_admin()) {
            return;
        }
        
        // 플러그인 페이지 접근 권한 체크
        if (isset($_GET['page']) && strpos($_GET['page'], 'ai-newsletter') === 0) {
            if (!$this->check_user_capability('edit_posts')) {
                wp_die(__('이 페이지에 접근할 권한이 없습니다.', 'ai-newsletter-generator-pro'));
            }
        }
    }
    
    /**
     * 사용자 권한 확인
     */
    public static function check_user_capability($capability) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        return current_user_can($capability);
    }
    
    /**
     * 액션별 권한 확인
     */
    public static function check_action_capability($action) {
        if (!isset(self::ACTION_CAPABILITIES[$action])) {
            return false;
        }
        
        $required_capability = self::ACTION_CAPABILITIES[$action];
        return self::check_user_capability($required_capability);
    }
    
    /**
     * Nonce 생성
     */
    public static function create_nonce($action) {
        return wp_create_nonce('ainl_' . $action);
    }
    
    /**
     * Nonce 검증
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, 'ainl_' . $action);
    }
    
    /**
     * AJAX Nonce 검증
     */
    public function verify_ajax_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ainl_admin_nonce')) {
            wp_die(__('보안 검증에 실패했습니다.', 'ai-newsletter-generator-pro'));
        }
    }
    
    /**
     * 비인증 AJAX 요청 차단
     */
    public function deny_unauthorized_ajax() {
        wp_die(__('로그인이 필요합니다.', 'ai-newsletter-generator-pro'));
    }
    
    /**
     * 입력값 정리 및 검증
     */
    public static function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            
            case 'url':
                return esc_url_raw($input);
            
            case 'int':
                return absint($input);
            
            case 'float':
                return floatval($input);
            
            case 'html':
                return wp_kses_post($input);
            
            case 'textarea':
                return sanitize_textarea_field($input);
            
            case 'key':
                return sanitize_key($input);
            
            case 'slug':
                return sanitize_title($input);
            
            case 'array':
                if (!is_array($input)) {
                    return array();
                }
                return array_map('sanitize_text_field', $input);
            
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * SQL 쿼리 보안 검증
     */
    public static function prepare_query($query, $args = array()) {
        global $wpdb;
        
        if (empty($args)) {
            return $query;
        }
        
        return $wpdb->prepare($query, $args);
    }
    
    /**
     * 출력값 이스케이프
     */
    public static function escape_output($output, $context = 'html') {
        switch ($context) {
            case 'attr':
                return esc_attr($output);
            
            case 'url':
                return esc_url($output);
            
            case 'js':
                return esc_js($output);
            
            case 'textarea':
                return esc_textarea($output);
            
            case 'html':
            default:
                return esc_html($output);
        }
    }
    
    /**
     * API 키 암호화 저장
     */
    public static function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // WordPress의 AUTH_KEY를 사용한 간단한 암호화
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'ainl_default_key';
        $encrypted = base64_encode($api_key . '|' . $key);
        
        return $encrypted;
    }
    
    /**
     * API 키 복호화
     */
    public static function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }
        
        $key = defined('AUTH_KEY') ? AUTH_KEY : 'ainl_default_key';
        $decoded = base64_decode($encrypted_key);
        
        if ($decoded === false) {
            return '';
        }
        
        $parts = explode('|', $decoded);
        if (count($parts) !== 2 || $parts[1] !== $key) {
            return '';
        }
        
        return $parts[0];
    }
    
    /**
     * 파일 업로드 MIME 타입 제한
     */
    public function restrict_upload_mimes($mimes) {
        // 플러그인 관련 업로드에서만 제한
        if (isset($_POST['action']) && strpos($_POST['action'], 'ainl_') === 0) {
            // CSV 파일만 허용
            return array(
                'csv' => 'text/csv',
                'txt' => 'text/plain'
            );
        }
        
        return $mimes;
    }
    
    /**
     * 파일 업로드 검증
     */
    public function validate_file_upload($file) {
        // 플러그인 관련 업로드에서만 검증
        if (isset($_POST['action']) && strpos($_POST['action'], 'ainl_') === 0) {
            // 파일 크기 제한 (5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $file['error'] = __('파일 크기가 너무 큽니다. (최대 5MB)', 'ai-newsletter-generator-pro');
                return $file;
            }
            
            // 파일 내용 검증
            $file_content = file_get_contents($file['tmp_name']);
            if ($this->contains_malicious_content($file_content)) {
                $file['error'] = __('악성 코드가 감지되었습니다.', 'ai-newsletter-generator-pro');
                return $file;
            }
        }
        
        return $file;
    }
    
    /**
     * 악성 코드 패턴 검사
     */
    private function contains_malicious_content($content) {
        $malicious_patterns = array(
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/onclick=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i'
        );
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 사용자 세션 보안 강화
     */
    public static function secure_user_session() {
        // 세션 하이재킹 방지
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 세션 ID 재생성
        if (!isset($_SESSION['ainl_session_started'])) {
            session_regenerate_id(true);
            $_SESSION['ainl_session_started'] = time();
        }
        
        // 세션 타임아웃 체크 (30분)
        if (isset($_SESSION['ainl_last_activity']) && 
            (time() - $_SESSION['ainl_last_activity'] > 1800)) {
            session_destroy();
            return false;
        }
        
        $_SESSION['ainl_last_activity'] = time();
        return true;
    }
    
    /**
     * 로그 기록
     */
    public static function log_security_event($event, $details = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_ip' => self::get_user_ip(),
            'event' => $event,
            'details' => $details
        );
        
        error_log('[AINL Security] ' . json_encode($log_entry));
    }
    
    /**
     * 사용자 IP 주소 가져오기
     */
    public static function get_user_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * 커스텀 에러 핸들러
     */
    public function custom_die_handler($handler) {
        // 플러그인 관련 에러에서만 커스텀 핸들러 사용
        if (isset($_GET['page']) && strpos($_GET['page'], 'ai-newsletter') === 0) {
            return array($this, 'security_die_handler');
        }
        
        return $handler;
    }
    
    /**
     * 보안 에러 핸들러
     */
    public function security_die_handler($message, $title = '', $args = array()) {
        // 보안 이벤트 로그 기록
        self::log_security_event('access_denied', array(
            'message' => $message,
            'page' => isset($_GET['page']) ? $_GET['page'] : '',
            'referer' => wp_get_referer()
        ));
        
        // 기본 WordPress die 핸들러 호출
        _default_wp_die_handler($message, $title, $args);
    }
    
    /**
     * 보안 헤더 설정
     */
    public static function set_security_headers() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }
    
    /**
     * 관리자 페이지 보안 체크
     */
    public static function admin_page_security_check($required_capability = 'edit_posts') {
        // 로그인 체크
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(admin_url()));
            exit;
        }
        
        // 권한 체크
        if (!current_user_can($required_capability)) {
            wp_die(__('이 페이지에 접근할 권한이 없습니다.', 'ai-newsletter-generator-pro'));
        }
        
        // Nonce 체크 (POST 요청인 경우)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ainl_admin_action')) {
                wp_die(__('보안 검증에 실패했습니다.', 'ai-newsletter-generator-pro'));
            }
        }
        
        // 보안 헤더 설정
        self::set_security_headers();
    }
} 
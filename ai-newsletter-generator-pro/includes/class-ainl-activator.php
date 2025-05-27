<?php
/**
 * 플러그인 활성화 처리 클래스
 * 플러그인이 활성화될 때 필요한 초기 설정을 담당합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Activator {
    
    /**
     * 플러그인 활성화 시 실행되는 메인 메서드
     * 데이터베이스 테이블 생성, 기본 옵션 설정 등을 수행합니다.
     */
    public static function activate() {
        // 최소 요구사항 체크
        self::check_requirements();
        
        // 기본 옵션 설정
        self::set_default_options();
        
        // 데이터베이스 테이블 생성
        AINL_Database::create_tables();
        
        // 기본 데이터 삽입
        self::insert_default_data();
        
        // 플러그인 활성화 로그
        self::log_activation();
    }
    
    /**
     * 최소 요구사항 체크
     * WordPress 버전, PHP 버전 등을 확인합니다.
     */
    private static function check_requirements() {
        global $wp_version;
        
        // WordPress 버전 체크
        if (version_compare($wp_version, '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('AI Newsletter Generator Pro requires WordPress 5.0 or higher.', 'ai-newsletter-generator-pro'));
        }
        
        // PHP 버전 체크
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('AI Newsletter Generator Pro requires PHP 7.4 or higher.', 'ai-newsletter-generator-pro'));
        }
        
        // 필수 PHP 확장 체크
        $required_extensions = array('curl', 'json', 'mbstring');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die(sprintf(__('AI Newsletter Generator Pro requires PHP %s extension.', 'ai-newsletter-generator-pro'), $extension));
            }
        }
    }
    
    /**
     * 기본 옵션 설정
     * 플러그인의 기본 설정값들을 데이터베이스에 저장합니다.
     */
    private static function set_default_options() {
        $default_settings = array(
            'general' => array(
                'plugin_name' => 'AI Newsletter Generator Pro',
                'sender_name' => get_bloginfo('name'),
                'sender_email' => get_option('admin_email'),
                'reply_to_email' => get_option('admin_email'),
            ),
            'ai' => array(
                'provider' => 'openai',
                'api_key' => '',
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ),
            'email' => array(
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'batch_size' => 50,
                'send_delay' => 1,
            ),
            'content' => array(
                'post_types' => array('post'),
                'categories' => array(),
                'tags' => array(),
                'date_range' => 7,
                'max_posts' => 10,
                'include_featured_image' => true,
            ),
            'templates' => array(
                'default_template' => 'basic',
                'custom_css' => '',
            ),
            'advanced' => array(
                'debug_mode' => false,
                'log_level' => 'error',
                'cache_duration' => 3600,
            )
        );
        
        // 기존 설정이 없는 경우에만 기본값 설정
        if (!get_option('ainl_settings')) {
            update_option('ainl_settings', $default_settings);
        }
        
        // 플러그인 메타 정보 저장
        update_option('ainl_plugin_activated_time', current_time('timestamp'));
        update_option('ainl_plugin_version', AINL_PLUGIN_VERSION);
    }
    
    /**
     * 기본 데이터 삽입
     * 기본 템플릿, 카테고리 등의 초기 데이터를 생성합니다.
     */
    private static function insert_default_data() {
        // 기본 이메일 템플릿 생성 (작업 6에서 구현 예정)
        // self::create_default_templates();
        
        // 기본 구독자 카테고리 생성 (작업 7에서 구현 예정)
        // self::create_default_categories();
    }
    
    /**
     * 활성화 로그 기록
     * 플러그인 활성화 정보를 로그에 기록합니다.
     */
    private static function log_activation() {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'action' => 'plugin_activated',
            'version' => AINL_PLUGIN_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'user_id' => get_current_user_id(),
        );
        
        // 활성화 로그를 옵션에 저장 (간단한 로깅)
        $existing_logs = get_option('ainl_activation_logs', array());
        $existing_logs[] = $log_data;
        
        // 최근 10개 로그만 유지
        if (count($existing_logs) > 10) {
            $existing_logs = array_slice($existing_logs, -10);
        }
        
        update_option('ainl_activation_logs', $existing_logs);
    }
    
    /**
     * 플러그인 업데이트 처리
     * 버전이 변경된 경우 필요한 업데이트 작업을 수행합니다.
     */
    public static function maybe_upgrade() {
        $current_version = get_option('ainl_plugin_version', '0.0.0');
        
        if (version_compare($current_version, AINL_PLUGIN_VERSION, '<')) {
            // 버전별 업그레이드 처리
            self::upgrade_database($current_version);
            
            // 새 버전 저장
            update_option('ainl_plugin_version', AINL_PLUGIN_VERSION);
        }
    }
    
    /**
     * 데이터베이스 업그레이드 처리
     * 버전별로 필요한 데이터베이스 변경사항을 적용합니다.
     */
    private static function upgrade_database($from_version) {
        // 버전별 업그레이드 로직
        // 예: if (version_compare($from_version, '1.1.0', '<')) { ... }
        
        // 현재는 초기 버전이므로 업그레이드 로직 없음
    }
} 
<?php
/**
 * Plugin Name: AI Newsletter Generator Pro
 * Plugin URI: https://example.com/ai-newsletter-generator-pro
 * Description: WordPress 게시물을 AI가 분석하여 자동으로 뉴스레터를 생성하고 발송하는 통합 솔루션입니다.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-newsletter-generator-pro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// 직접 접근 방지 - 보안 강화 (WordPress 환경 체크 강화)
if (!defined('ABSPATH')) {
    // WordPress 환경이 아닌 경우 즉시 종료
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not allowed.');
}

// WordPress 트러블슈팅 문서 권장사항: Headers already sent 오류 방지
if (headers_sent()) {
    error_log('AINL Plugin: Headers already sent, plugin loading aborted.');
    return;
}

// WordPress 함수 가용성 체크 (critical error 방지)
if (!function_exists('plugin_dir_path') || !function_exists('plugin_dir_url') || !function_exists('plugin_basename')) {
    error_log('AINL Plugin: WordPress plugin functions not available, loading aborted.');
    return;
}

// PHP 메모리 제한 증가 - 500 오류 방지
if (function_exists('ini_set')) {
    @ini_set('memory_limit', '512M');
    @ini_set('max_execution_time', 300);
}

// 플러그인 상수 정의 (WordPress 함수 가용성 확인 후)
if (!defined('AINL_PLUGIN_FILE')) {
    define('AINL_PLUGIN_FILE', __FILE__);
}
if (!defined('AINL_PLUGIN_DIR')) {
    define('AINL_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('AINL_PLUGIN_URL')) {
    define('AINL_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('AINL_PLUGIN_VERSION')) {
    define('AINL_PLUGIN_VERSION', '1.0.0');
}
if (!defined('AINL_PLUGIN_BASENAME')) {
    define('AINL_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * AI Newsletter Generator Pro 메인 클래스
 * 플러그인의 초기화와 전체적인 관리를 담당합니다.
 * WordPress 트러블슈팅 권장사항 적용: 안전한 초기화
 */
class AI_Newsletter_Generator_Pro {
    
    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;
    
    /**
     * 플러그인 로딩 상태 추적
     */
    private $is_loaded = false;
    
    /**
     * 싱글톤 패턴으로 인스턴스 반환
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 생성자 - 플러그인 초기화
     * WordPress 트러블슈팅 권장: 안전한 초기화
     */
    private function __construct() {
        try {
            // WordPress 환경 재확인
            if (!$this->is_wordpress_environment()) {
                error_log('AINL Plugin: Invalid WordPress environment in constructor');
                return;
            }
            
            $this->init_hooks();
            $this->load_dependencies();
            $this->is_loaded = true;
            
        } catch (Exception $e) {
            error_log('AINL Plugin Constructor Error: ' . $e->getMessage());
            $this->is_loaded = false;
        }
    }
    
    /**
     * WordPress 환경 체크
     * 
     * @return bool WordPress 환경 여부
     */
    private function is_wordpress_environment() {
        return defined('ABSPATH') && 
               function_exists('add_action') && 
               function_exists('register_activation_hook') &&
               function_exists('plugin_dir_path');
    }
    
    /**
     * WordPress 훅 초기화
     * 모든 WordPress 함수 호출을 안전하게 체크
     */
    private function init_hooks() {
        // WordPress 함수 가용성 체크
        if (!function_exists('register_activation_hook') || 
            !function_exists('register_deactivation_hook') || 
            !function_exists('add_action') || 
            !function_exists('add_filter')) {
            error_log('AINL Plugin: WordPress hook functions not available');
            return;
        }
        
        try {
            // 플러그인 활성화/비활성화 훅
            register_activation_hook(AINL_PLUGIN_FILE, array($this, 'activate'));
            register_deactivation_hook(AINL_PLUGIN_FILE, array($this, 'deactivate'));
            
            // 플러그인 삭제는 별도 uninstall.php 파일에서 처리 (WordPress 트러블슈팅 권장)
            // register_uninstall_hook은 제거하고 uninstall.php 사용
            
            // 플러그인 로드 후 초기화 (WordPress가 완전히 로드된 후)
            add_action('plugins_loaded', array($this, 'init'), 10);
            
            // 크론 스케줄 추가
            add_filter('cron_schedules', array($this, 'add_cron_schedules'));
            
            // 관리자 메뉴 직접 추가 (admin_init보다 빠르게)
            if (function_exists('is_admin') && is_admin()) {
                add_action('admin_menu', array($this, 'add_admin_menu'));
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
                add_action('admin_init', array($this, 'admin_init'));
            }
            
        } catch (Exception $e) {
            error_log('AINL Plugin Hook Init Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 의존성 로드 (오토로더)
     */
    private function load_dependencies() {
        try {
            // 자동 로더 실행
            spl_autoload_register(array($this, 'autoloader'));
            
            // 필요한 파일들 포함
            $required_files = array(
                'includes/class-admin.php',
                'includes/class-generator.php',
                'includes/class-scheduler.php',
                'includes/class-email.php'
            );
            
            foreach ($required_files as $file) {
                $file_path = AINL_PLUGIN_DIR . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                } else {
                    error_log('AINL Plugin: Required file missing - ' . $file_path);
                }
            }
        } catch (Exception $e) {
            error_log('AINL Plugin Dependencies Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 자동 로더
     */
    public function autoloader($class_name) {
        if (strpos($class_name, 'AINL_') !== 0) {
            return;
        }
        
        $file_name = 'class-' . strtolower(str_replace(array('AINL_', '_'), array('', '-'), $class_name)) . '.php';
        $file_path = AINL_PLUGIN_DIR . 'includes/' . $file_name;
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    /**
     * 플러그인 초기화 (WordPress가 완전히 로드된 후)
     */
    public function init() {
        try {
            // WordPress가 완전히 로드되었는지 확인
            if (!function_exists('is_admin') || !function_exists('current_user_can')) {
                error_log('AINL Plugin: WordPress admin functions not available in init');
                return;
            }
            
            // 국제화 로드
            if (function_exists('load_plugin_textdomain')) {
                load_plugin_textdomain('ai-newsletter-generator-pro', false, dirname(AINL_PLUGIN_BASENAME) . '/languages');
            }
            
            // 관리자 페이지 초기화
            if (is_admin()) {
                $this->init_admin();
            }
            
            // 크론 작업 스케줄링
            $this->schedule_newsletter_generation();
            
        } catch (Exception $e) {
            error_log('AINL Plugin Init Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 관리자 초기화
     */
    public function admin_init() {
        try {
            // WordPress 관리자 함수 가용성 체크
            if (!function_exists('current_user_can')) {
                error_log('AINL Plugin: WordPress admin functions not available');
                return;
            }
            
            // 기타 관리자 초기화 작업 (메뉴는 이미 init_hooks에서 처리됨)
            // 설정 등록, 필드 등록 등 추가 관리자 초기화 작업
            
        } catch (Exception $e) {
            error_log('AINL Plugin Admin Init Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 관리자 초기화 (별도 메서드)
     */
    private function init_admin() {
        try {
            if (class_exists('AINL_Admin')) {
                new AINL_Admin();
            }
        } catch (Exception $e) {
            error_log('AINL Plugin Admin Class Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 관리자 메뉴 추가 (메인 메뉴에 표시)
     */
    public function add_admin_menu() {
        if (!function_exists('add_menu_page') || !function_exists('current_user_can')) {
            return;
        }
        
        try {
            // 메인 메뉴에 AI Newsletter 메뉴 추가
            add_menu_page(
                'AI Newsletter Generator Pro',           // 페이지 제목
                'AI Newsletter',                         // 메뉴 제목
                'manage_options',                        // 필요 권한
                'ai-newsletter-generator-pro',           // 메뉴 슬러그
                array($this, 'admin_page'),             // 콜백 함수
                'dashicons-email-alt',                   // 아이콘 (이메일 아이콘)
                30                                       // 메뉴 위치 (댓글 다음)
            );
            
            // 하위 메뉴 추가 (설정)
            if (function_exists('add_submenu_page')) {
                add_submenu_page(
                    'ai-newsletter-generator-pro',      // 부모 메뉴 슬러그
                    'Newsletter Settings',               // 페이지 제목
                    'Settings',                          // 메뉴 제목
                    'manage_options',                    // 필요 권한
                    'ai-newsletter-settings',            // 메뉴 슬러그
                    array($this, 'settings_page')       // 콜백 함수
                );
            }
            
        } catch (Exception $e) {
            error_log('AINL Plugin Menu Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 관리자 스크립트 로드
     */
    public function enqueue_admin_scripts($hook) {
        if (!function_exists('wp_enqueue_script') || !function_exists('wp_enqueue_style')) {
            return;
        }
        
        // AI Newsletter 메뉴 페이지들에서만 스크립트 로드
        if ($hook !== 'toplevel_page_ai-newsletter-generator-pro' && 
            $hook !== 'ai-newsletter_page_ai-newsletter-settings') {
            return;
        }
        
        try {
            wp_enqueue_script(
                'ainl-admin-js',
                AINL_PLUGIN_URL . 'assets/admin.js',
                array('jquery'),
                AINL_PLUGIN_VERSION,
                true
            );
            
            wp_enqueue_style(
                'ainl-admin-css',
                AINL_PLUGIN_URL . 'assets/admin.css',
                array(),
                AINL_PLUGIN_VERSION
            );
        } catch (Exception $e) {
            error_log('AINL Plugin Scripts Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 관리자 메인 페이지 렌더링
     */
    public function admin_page() {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        echo '<div class="wrap">';
        echo '<h1>AI Newsletter Generator Pro</h1>';
        echo '<div class="notice notice-info"><p><strong>환영합니다!</strong> AI Newsletter Generator Pro 플러그인이 성공적으로 활성화되었습니다.</p></div>';
        echo '<div class="card">';
        echo '<h2>플러그인 정보</h2>';
        echo '<p>이 플러그인은 WordPress 게시물을 AI가 분석하여 자동으로 뉴스레터를 생성하고 발송하는 통합 솔루션입니다.</p>';
        echo '<h3>주요 기능:</h3>';
        echo '<ul>';
        echo '<li>AI 기반 뉴스레터 자동 생성</li>';
        echo '<li>구독자 관리 시스템</li>';
        echo '<li>이메일 템플릿 관리</li>';
        echo '<li>발송 스케줄링</li>';
        echo '<li>성과 분석 및 통계</li>';
        echo '</ul>';
        echo '<p><a href="' . admin_url('admin.php?page=ai-newsletter-settings') . '" class="button button-primary">설정으로 이동</a></p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * 설정 페이지 렌더링
     */
    public function settings_page() {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        echo '<div class="wrap">';
        echo '<h1>AI Newsletter Settings</h1>';
        echo '<div class="card">';
        echo '<h2>기본 설정</h2>';
        echo '<form method="post" action="options.php">';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">발송자 이름</th>';
        echo '<td><input type="text" name="ainl_email_from_name" value="' . get_option('ainl_email_from_name', get_bloginfo('name')) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">발송자 이메일</th>';
        echo '<td><input type="email" name="ainl_email_from_email" value="' . get_option('ainl_email_from_email', get_bloginfo('admin_email')) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">뉴스레터 발송 주기</th>';
        echo '<td>';
        echo '<select name="ainl_newsletter_frequency">';
        $frequency = get_option('ainl_newsletter_frequency', 'weekly');
        echo '<option value="weekly"' . selected($frequency, 'weekly', false) . '>주간</option>';
        echo '<option value="monthly"' . selected($frequency, 'monthly', false) . '>월간</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" name="submit" class="button button-primary" value="설정 저장" /></p>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * 크론 스케줄 추가
     */
    public function add_cron_schedules($schedules) {
        try {
            $schedules['weekly'] = array(
                'interval' => 604800, // 7일 = 604800초
                'display'  => __('매주', 'ai-newsletter-generator-pro')
            );
            
            $schedules['monthly'] = array(
                'interval' => 2592000, // 30일 = 2592000초
                'display'  => __('매월', 'ai-newsletter-generator-pro')
            );
            
            return $schedules;
        } catch (Exception $e) {
            error_log('AINL Plugin Cron Schedules Error: ' . $e->getMessage());
            return $schedules;
        }
    }
    
    /**
     * 뉴스레터 생성 스케줄링
     */
    private function schedule_newsletter_generation() {
        try {
            if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
                error_log('AINL Plugin: WordPress cron functions not available');
                return;
            }
            
            if (!wp_next_scheduled('ainl_generate_newsletter')) {
                wp_schedule_event(time(), 'weekly', 'ainl_generate_newsletter');
            }
            
            // 크론 액션 추가
            if (function_exists('add_action')) {
                add_action('ainl_generate_newsletter', array($this, 'generate_newsletter_cron'));
            }
            
        } catch (Exception $e) {
            error_log('AINL Plugin Schedule Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 크론으로 뉴스레터 생성
     */
    public function generate_newsletter_cron() {
        try {
            if (class_exists('AINL_Generator')) {
                $generator = new AINL_Generator();
                $generator->generate_and_send_newsletter();
            }
        } catch (Exception $e) {
            error_log('AINL Plugin Cron Generation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 플러그인 활성화
     */
    public function activate() {
        try {
            // WordPress 함수 가용성 체크
            if (!function_exists('flush_rewrite_rules')) {
                error_log('AINL Plugin: WordPress rewrite functions not available in activation');
                return;
            }
            
            // 데이터베이스 테이블 생성
            $this->create_tables();
            
            // 기본 옵션 설정
            $this->set_default_options();
            
            // 리라이트 규칙 플러시
            flush_rewrite_rules();
            
            error_log('AINL Plugin: Successfully activated');
            
        } catch (Exception $e) {
            error_log('AINL Plugin Activation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 플러그인 비활성화
     */
    public function deactivate() {
        try {
            // WordPress 함수 가용성 체크
            if (!function_exists('wp_clear_scheduled_hook') || !function_exists('flush_rewrite_rules')) {
                error_log('AINL Plugin: WordPress functions not available in deactivation');
                return;
            }
            
            // 크론 작업 제거
            wp_clear_scheduled_hook('ainl_generate_newsletter');
            
            // 리라이트 규칙 플러시
            flush_rewrite_rules();
            
            error_log('AINL Plugin: Successfully deactivated');
            
        } catch (Exception $e) {
            error_log('AINL Plugin Deactivation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 데이터베이스 테이블 생성
     */
    private function create_tables() {
        global $wpdb;
        
        if (!$wpdb) {
            error_log('AINL Plugin: WordPress database not available');
            return;
        }
        
        try {
            // 뉴스레터 구독자 테이블
            $subscribers_table = $wpdb->prefix . 'ainl_subscribers';
            $sql_subscribers = "CREATE TABLE IF NOT EXISTS $subscribers_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                name varchar(255) DEFAULT '',
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY email (email),
                KEY status (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            // 뉴스레터 캠페인 테이블
            $campaigns_table = $wpdb->prefix . 'ainl_campaigns';
            $sql_campaigns = "CREATE TABLE IF NOT EXISTS $campaigns_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                content longtext,
                status varchar(20) DEFAULT 'draft',
                sent_at datetime NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY status (status)
            ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            if (function_exists('require_once')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                if (function_exists('dbDelta')) {
                    dbDelta($sql_subscribers);
                    dbDelta($sql_campaigns);
                }
            }
            
        } catch (Exception $e) {
            error_log('AINL Plugin Database Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 기본 옵션 설정
     */
    private function set_default_options() {
        try {
            if (!function_exists('add_option')) {
                error_log('AINL Plugin: WordPress option functions not available');
                return;
            }
            
            // 기본 설정값들
            add_option('ainl_plugin_version', AINL_PLUGIN_VERSION);
            add_option('ainl_plugin_activated', true);
            add_option('ainl_email_from_name', get_bloginfo('name'));
            add_option('ainl_email_from_email', get_bloginfo('admin_email'));
            add_option('ainl_newsletter_frequency', 'weekly');
            add_option('ainl_max_posts_per_newsletter', 5);
            add_option('ainl_ai_model', 'gpt-3.5-turbo');
            add_option('ainl_template_style', 'modern');
            
        } catch (Exception $e) {
            error_log('AINL Plugin Options Error: ' . $e->getMessage());
        }
    }
}

/**
 * 플러그인 인스턴스 시작
 * WordPress 트러블슈팅 문서 권장: 안전한 초기화
 */
function ainl_get_instance() {
    return AI_Newsletter_Generator_Pro::get_instance();
}

// WordPress 환경에서만 실행 (보안 강화)
if (defined('ABSPATH') && function_exists('add_action')) {
    // 플러그인 시작
    ainl_get_instance();
} 
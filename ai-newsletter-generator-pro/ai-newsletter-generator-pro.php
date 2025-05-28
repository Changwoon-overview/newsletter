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

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

// 플러그인 상수 정의
define('AINL_PLUGIN_FILE', __FILE__);
define('AINL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AINL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AINL_PLUGIN_VERSION', '1.0.0');
define('AINL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * AI Newsletter Generator Pro 메인 클래스
 * 플러그인의 초기화와 전체적인 관리를 담당합니다.
 */
class AI_Newsletter_Generator_Pro {
    
    /**
     * 싱글톤 인스턴스
     */
    private static $instance = null;
    
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
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * WordPress 훅 초기화
     */
    private function init_hooks() {
        // 플러그인 활성화/비활성화 훅
        register_activation_hook(AINL_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(AINL_PLUGIN_FILE, array($this, 'deactivate'));
        
        // 플러그인 삭제 훅
        register_uninstall_hook(AINL_PLUGIN_FILE, array('AI_Newsletter_Generator_Pro', 'uninstall'));
        
        // 플러그인 로드 후 초기화
        add_action('plugins_loaded', array($this, 'init'));
        
        // 크론 스케줄 추가
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // 관리자 초기화
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
        }
    }
    
    /**
     * 의존성 파일 로드
     */
    private function load_dependencies() {
        // 오토로더 설정
        spl_autoload_register(array($this, 'autoload'));
        
        // 핵심 클래스 로드
        require_once AINL_PLUGIN_DIR . 'includes/class-ainl-activator.php';
        require_once AINL_PLUGIN_DIR . 'includes/class-ainl-deactivator.php';
        require_once AINL_PLUGIN_DIR . 'includes/class-ainl-database.php';
    }
    
    /**
     * 클래스 오토로더
     * 클래스명을 기반으로 파일을 자동 로드합니다.
     */
    public function autoload($class_name) {
        // 플러그인 클래스만 처리
        if (strpos($class_name, 'AINL_') !== 0) {
            return;
        }
        
        // 클래스명을 파일명으로 변환
        $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        
        // 여러 디렉토리에서 파일 검색
        $search_paths = array(
            AINL_PLUGIN_DIR . 'includes/',
            AINL_PLUGIN_DIR . 'admin/',
            AINL_PLUGIN_DIR . 'public/'
        );
        
        foreach ($search_paths as $path) {
            $file_path = $path . $file_name;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }
    
    /**
     * 플러그인 초기화
     */
    public function init() {
        // 텍스트 도메인 로드
        load_plugin_textdomain(
            'ai-newsletter-generator-pro',
            false,
            dirname(AINL_PLUGIN_BASENAME) . '/languages'
        );
        
        // 플러그인이 활성화된 경우에만 실행
        if (get_option('ainl_plugin_activated')) {
            $this->init_plugin_components();
        }
    }
    
    /**
     * 관리자 초기화
     */
    public function admin_init() {
        // 관리자 관련 초기화 작업
        if (current_user_can('manage_options')) {
            // 관리자 메뉴 및 페이지 초기화는 다음 작업에서 구현
        }
    }
    
    /**
     * 플러그인 컴포넌트 초기화
     */
    private function init_plugin_components() {
        // 보안 시스템 초기화 (최우선)
        new AINL_Security();
        
        // 데이터베이스 관리자 초기화
        new AINL_Database();
        
        // 구독자 관리자 초기화
        new AINL_Subscriber_Manager();
        
        // 이메일 매니저 초기화
        new AINL_Email_Manager();
        
        // 템플릿 관리자 초기화
        new AINL_Template_Manager();
        
        // 캠페인 관리자 초기화
        new AINL_Campaign_Manager();
        
        // AI 엔진 초기화
        AINL_AI_Engine::get_instance();
        
        // 관리자 인터페이스 초기화
        if (is_admin()) {
            new AINL_Admin();
            new AINL_Settings();
        }
        
        // 각 컴포넌트들은 후속 작업에서 구현될 예정
        // - AI 엔진
        // - 이메일 시스템 등
    }
    
    /**
     * 사용자 정의 크론 스케줄 추가
     * 이메일 큐 처리를 위한 매분 실행 스케줄을 추가합니다.
     * 
     * @param array $schedules 기존 크론 스케줄 배열
     * @return array 수정된 크론 스케줄 배열
     */
    public function add_cron_schedules($schedules) {
        // 매분 실행 스케줄 추가
        $schedules['every_minute'] = array(
            'interval' => 60, // 60초 = 1분
            'display'  => __('Every Minute', 'ai-newsletter-generator-pro')
        );
        
        // 5분마다 실행 스케줄 추가
        $schedules['every_five_minutes'] = array(
            'interval' => 300, // 300초 = 5분
            'display'  => __('Every 5 Minutes', 'ai-newsletter-generator-pro')
        );
        
        // 15분마다 실행 스케줄 추가
        $schedules['every_fifteen_minutes'] = array(
            'interval' => 900, // 900초 = 15분
            'display'  => __('Every 15 Minutes', 'ai-newsletter-generator-pro')
        );
        
        return $schedules;
    }
    
    /**
     * 플러그인 활성화 시 실행
     */
    public function activate() {
        // 활성화 처리 클래스 실행
        AINL_Activator::activate();
        
        // 활성화 플래그 설정
        update_option('ainl_plugin_activated', true);
        
        // 버전 정보 저장
        update_option('ainl_plugin_version', AINL_PLUGIN_VERSION);
    }
    
    /**
     * 플러그인 비활성화 시 실행
     */
    public function deactivate() {
        // 비활성화 처리 클래스 실행
        AINL_Deactivator::deactivate();
        
        // 활성화 플래그 제거
        delete_option('ainl_plugin_activated');
    }
    
    /**
     * 플러그인 삭제 시 실행 (정적 메서드)
     */
    public static function uninstall() {
        // 모든 플러그인 데이터 정리
        // 이 부분은 나중에 구현될 예정
        
        // 옵션 삭제
        delete_option('ainl_plugin_activated');
        delete_option('ainl_plugin_version');
        delete_option('ainl_settings');
    }
}

/**
 * 플러그인 인스턴스 시작
 */
function ainl_get_instance() {
    return AI_Newsletter_Generator_Pro::get_instance();
}

// 플러그인 시작
ainl_get_instance(); 
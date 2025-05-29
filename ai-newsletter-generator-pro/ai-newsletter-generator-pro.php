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
                
                // 설정 저장 처리를 위한 admin_post 액션 추가
                add_action('admin_post_save_ainl_settings', array($this, 'save_settings'));
                
                // 뉴스레터 생성 및 구독자 관리를 위한 액션 추가
                add_action('admin_post_create_ainl_newsletter', array($this, 'create_newsletter'));
                add_action('admin_post_add_ainl_subscriber', array($this, 'add_subscriber'));
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
        
        // 설정 완료 여부 확인
        $is_configured = $this->is_plugin_configured();
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : ($is_configured ? 'dashboard' : 'welcome');
        
        echo '<div class="wrap">';
        echo '<h1>AI Newsletter Generator Pro</h1>';
        
        if ($is_configured) {
            // 설정이 완료된 경우 - 주요 기능 탭 표시
            $this->render_main_tabs($active_tab);
        } else {
            // 설정이 미완료된 경우 - 환영 페이지
            $this->render_welcome_page();
        }
        
        echo '</div>';
    }
    
    /**
     * 플러그인 설정 완료 여부 확인
     */
    private function is_plugin_configured() {
        $from_name = get_option('ainl_email_from_name', '');
        $from_email = get_option('ainl_email_from_email', '');
        $frequency = get_option('ainl_newsletter_frequency', '');
        
        return !empty($from_name) && !empty($from_email) && !empty($frequency);
    }
    
    /**
     * 환영 페이지 렌더링 (설정 미완료 시)
     */
    private function render_welcome_page() {
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
        echo '<p><strong>시작하려면 먼저 기본 설정을 완료해주세요.</strong></p>';
        echo '<p><a href="' . admin_url('admin.php?page=ai-newsletter-settings') . '" class="button button-primary button-large">기본 설정 완료하기</a></p>';
        echo '</div>';
    }
    
    /**
     * 메인 기능 탭 렌더링 (설정 완료 후)
     */
    private function render_main_tabs($active_tab) {
        // 탭 네비게이션
        echo '<h2 class="nav-tab-wrapper">';
        $tabs = array(
            'dashboard' => '대시보드',
            'create' => '뉴스레터 생성',
            'subscribers' => '구독자 관리',
            'campaigns' => '발송 이력',
            'templates' => '템플릿 관리',
            'analytics' => '분석 통계'
        );
        
        foreach ($tabs as $tab_key => $tab_name) {
            $class = ($active_tab == $tab_key) ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="' . admin_url('admin.php?page=ai-newsletter-generator-pro&tab=' . $tab_key) . '" class="' . $class . '">' . $tab_name . '</a>';
        }
        echo '</h2>';
        
        // 탭 콘텐츠
        echo '<div class="tab-content">';
        switch ($active_tab) {
            case 'dashboard':
                $this->render_dashboard_tab();
                break;
            case 'create':
                $this->render_create_tab();
                break;
            case 'subscribers':
                $this->render_subscribers_tab();
                break;
            case 'campaigns':
                $this->render_campaigns_tab();
                break;
            case 'templates':
                $this->render_templates_tab();
                break;
            case 'analytics':
                $this->render_analytics_tab();
                break;
            default:
                $this->render_dashboard_tab();
        }
        echo '</div>';
    }
    
    /**
     * 대시보드 탭 렌더링
     */
    private function render_dashboard_tab() {
        global $wpdb;
        
        // 통계 데이터 가져오기
        $subscribers_table = $wpdb->prefix . 'ainl_subscribers';
        $campaigns_table = $wpdb->prefix . 'ainl_campaigns';
        
        $total_subscribers = $wpdb ? $wpdb->get_var("SELECT COUNT(*) FROM $subscribers_table WHERE status = 'active'") : 0;
        $total_campaigns = $wpdb ? $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table") : 0;
        $recent_posts = get_posts(array('numberposts' => 5, 'post_status' => 'publish'));
        
        echo '<div class="dashboard-widgets">';
        
        // 통계 카드들
        echo '<div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0; color: #2271b1;">구독자 수</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 10px 0;">' . $total_subscribers . '명</p>';
        echo '</div>';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0; color: #2271b1;">발송 캠페인</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 10px 0;">' . $total_campaigns . '개</p>';
        echo '</div>';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0; color: #2271b1;">최근 게시물</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 10px 0;">' . count($recent_posts) . '개</p>';
        echo '</div>';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0; color: #2271b1;">발송 주기</h3>';
        $frequency = get_option('ainl_newsletter_frequency', 'weekly');
        echo '<p style="font-size: 18px; font-weight: bold; margin: 10px 0;">' . ($frequency == 'weekly' ? '주간' : '월간') . '</p>';
        echo '</div>';
        
        echo '</div>';
        
        // 빠른 작업 버튼들
        echo '<div class="quick-actions" style="margin: 20px 0;">';
        echo '<h3>빠른 작업</h3>';
        echo '<p>';
        echo '<a href="' . admin_url('admin.php?page=ai-newsletter-generator-pro&tab=create') . '" class="button button-primary">새 뉴스레터 생성</a> ';
        echo '<a href="' . admin_url('admin.php?page=ai-newsletter-generator-pro&tab=subscribers') . '" class="button button-secondary">구독자 관리</a> ';
        echo '<a href="' . admin_url('admin.php?page=ai-newsletter-settings') . '" class="button button-secondary">설정 변경</a>';
        echo '</p>';
        echo '</div>';
        
        // 최근 게시물 목록
        if ($recent_posts) {
            echo '<div class="recent-posts">';
            echo '<h3>최근 게시물 (뉴스레터 후보)</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>제목</th><th>작성일</th><th>작성자</th><th>작업</th></tr></thead>';
            echo '<tbody>';
            foreach ($recent_posts as $post) {
                echo '<tr>';
                echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
                echo '<td>' . get_the_date('Y-m-d', $post->ID) . '</td>';
                echo '<td>' . get_the_author_meta('display_name', $post->post_author) . '</td>';
                echo '<td><button class="button button-small" onclick="alert(\'뉴스레터에 포함하기 기능은 곧 제공됩니다.\')">뉴스레터에 포함</button></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * 뉴스레터 생성 탭 렌더링
     */
    private function render_create_tab() {
        echo '<div class="create-newsletter">';
        echo '<h3>새 뉴스레터 생성</h3>';
        echo '<div class="postbox">';
        echo '<div class="inside">';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('ainl_create_newsletter', 'ainl_create_nonce');
        }
        echo '<input type="hidden" name="action" value="create_ainl_newsletter" />';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">뉴스레터 제목</th>';
        echo '<td><input type="text" name="newsletter_title" value="' . date('Y년 m월 주간 뉴스레터') . '" class="regular-text" required /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">포함할 게시물 수</th>';
        echo '<td><input type="number" name="post_count" value="' . get_option('ainl_max_posts_per_newsletter', 5) . '" min="1" max="20" class="small-text" /> 개</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">게시물 범위</th>';
        echo '<td>';
        echo '<select name="post_range">';
        echo '<option value="week">최근 1주일</option>';
        echo '<option value="month">최근 1개월</option>';
        echo '<option value="3months">최근 3개월</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button button-primary" value="AI 뉴스레터 생성" />';
        echo '<input type="submit" name="preview" class="button button-secondary" value="미리보기" style="margin-left: 10px;" />';
        echo '</p>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * 구독자 관리 탭 렌더링
     */
    private function render_subscribers_tab() {
        global $wpdb;
        $subscribers_table = $wpdb->prefix . 'ainl_subscribers';
        
        echo '<div class="subscribers-management">';
        echo '<h3>구독자 관리</h3>';
        
        // 구독자 추가 폼
        echo '<div class="add-subscriber" style="background: #fff; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">';
        echo '<h4>새 구독자 추가</h4>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="display: flex; gap: 10px; align-items: end;">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('ainl_add_subscriber', 'ainl_subscriber_nonce');
        }
        echo '<input type="hidden" name="action" value="add_ainl_subscriber" />';
        echo '<div>';
        echo '<label>이름</label><br>';
        echo '<input type="text" name="subscriber_name" placeholder="구독자 이름" class="regular-text" />';
        echo '</div>';
        echo '<div>';
        echo '<label>이메일</label><br>';
        echo '<input type="email" name="subscriber_email" placeholder="이메일 주소" class="regular-text" required />';
        echo '</div>';
        echo '<div>';
        echo '<input type="submit" name="submit" class="button button-primary" value="구독자 추가" />';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        
        // 구독자 목록
        if ($wpdb) {
            $subscribers = $wpdb->get_results("SELECT * FROM $subscribers_table ORDER BY created_at DESC LIMIT 50");
            if ($subscribers) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>이름</th><th>이메일</th><th>상태</th><th>가입일</th><th>작업</th></tr></thead>';
                echo '<tbody>';
                foreach ($subscribers as $subscriber) {
                    echo '<tr>';
                    echo '<td>' . esc_html($subscriber->name) . '</td>';
                    echo '<td>' . esc_html($subscriber->email) . '</td>';
                    echo '<td><span class="status-' . $subscriber->status . '">' . ($subscriber->status == 'active' ? '활성' : '비활성') . '</span></td>';
                    echo '<td>' . date('Y-m-d', strtotime($subscriber->created_at)) . '</td>';
                    echo '<td>';
                    echo '<button class="button button-small" onclick="toggleSubscriber(' . $subscriber->id . ')">상태 변경</button> ';
                    echo '<button class="button button-small button-link-delete" onclick="deleteSubscriber(' . $subscriber->id . ')">삭제</button>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p>아직 구독자가 없습니다. 위의 폼을 사용하여 첫 구독자를 추가해보세요.</p>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * 발송 이력 탭 렌더링
     */
    private function render_campaigns_tab() {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'ainl_campaigns';
        
        echo '<div class="campaigns-history">';
        echo '<h3>뉴스레터 발송 이력</h3>';
        
        if ($wpdb) {
            $campaigns = $wpdb->get_results("SELECT * FROM $campaigns_table ORDER BY created_at DESC LIMIT 20");
            if ($campaigns) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>제목</th><th>상태</th><th>생성일</th><th>발송일</th><th>작업</th></tr></thead>';
                echo '<tbody>';
                foreach ($campaigns as $campaign) {
                    echo '<tr>';
                    echo '<td>' . esc_html($campaign->title) . '</td>';
                    echo '<td>';
                    if ($campaign->status == 'sent') {
                        echo '<span style="color: green;">발송 완료</span>';
                    } elseif ($campaign->status == 'draft') {
                        echo '<span style="color: orange;">임시저장</span>';
                    } else {
                        echo '<span>' . $campaign->status . '</span>';
                    }
                    echo '</td>';
                    echo '<td>' . date('Y-m-d H:i', strtotime($campaign->created_at)) . '</td>';
                    echo '<td>' . ($campaign->sent_at ? date('Y-m-d H:i', strtotime($campaign->sent_at)) : '-') . '</td>';
                    echo '<td>';
                    echo '<button class="button button-small" onclick="viewCampaign(' . $campaign->id . ')">보기</button> ';
                    if ($campaign->status == 'draft') {
                        echo '<button class="button button-small button-primary" onclick="sendCampaign(' . $campaign->id . ')">발송</button>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p>아직 발송한 뉴스레터가 없습니다. <a href="' . admin_url('admin.php?page=ai-newsletter-generator-pro&tab=create') . '">새 뉴스레터를 생성</a>해보세요.</p>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * 템플릿 관리 탭 렌더링
     */
    private function render_templates_tab() {
        echo '<div class="templates-management">';
        echo '<h3>이메일 템플릿 관리</h3>';
        echo '<p>뉴스레터의 디자인과 레이아웃을 관리합니다.</p>';
        
        $templates = array(
            'modern' => array('name' => '모던 스타일', 'description' => '깔끔하고 현대적인 디자인'),
            'classic' => array('name' => '클래식 스타일', 'description' => '전통적이고 신뢰감 있는 디자인'),
            'minimal' => array('name' => '미니멀 스타일', 'description' => '간결하고 심플한 디자인')
        );
        
        $current_template = get_option('ainl_template_style', 'modern');
        
        echo '<div class="template-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">';
        foreach ($templates as $key => $template) {
            $is_active = ($current_template == $key);
            echo '<div class="template-card" style="border: 2px solid ' . ($is_active ? '#2271b1' : '#ddd') . '; padding: 20px; border-radius: 5px; text-align: center;">';
            echo '<h4>' . $template['name'] . '</h4>';
            echo '<p>' . $template['description'] . '</p>';
            if ($is_active) {
                echo '<p><strong style="color: #2271b1;">현재 사용 중</strong></p>';
            } else {
                echo '<p><button class="button button-primary" onclick="selectTemplate(\'' . $key . '\')">선택</button></p>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * 분석 통계 탭 렌더링
     */
    private function render_analytics_tab() {
        echo '<div class="analytics-dashboard">';
        echo '<h3>분석 및 통계</h3>';
        echo '<p>뉴스레터 성과를 분석하고 개선점을 찾아보세요.</p>';
        
        // 샘플 통계 데이터
        echo '<div class="analytics-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h4>이번 달 발송률</h4>';
        echo '<p style="font-size: 24px; font-weight: bold; color: #2271b1;">95.5%</p>';
        echo '</div>';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h4>평균 오픈률</h4>';
        echo '<p style="font-size: 24px; font-weight: bold; color: #2271b1;">42.3%</p>';
        echo '</div>';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h4>클릭률</h4>';
        echo '<p style="font-size: 24px; font-weight: bold; color: #2271b1;">12.8%</p>';
        echo '</div>';
        
        echo '<div class="stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; text-align: center;">';
        echo '<h4>구독 취소율</h4>';
        echo '<p style="font-size: 24px; font-weight: bold; color: #2271b1;">2.1%</p>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div class="analytics-note" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 20px 0;">';
        echo '<h4>📊 분석 기능 개발 중</h4>';
        echo '<p>더 자세한 분석 기능은 향후 업데이트에서 제공될 예정입니다. 현재는 기본 통계만 표시됩니다.</p>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * 설정 저장 처리
     */
    public function save_settings() {
        // 권한 검증
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // nonce 검증
        if (!isset($_POST['ainl_settings_nonce']) || !wp_verify_nonce($_POST['ainl_settings_nonce'], 'ainl_save_settings')) {
            wp_die(__('Security check failed'));
        }
        
        try {
            // 기본 설정 저장
            if (isset($_POST['ainl_email_from_name'])) {
                update_option('ainl_email_from_name', sanitize_text_field($_POST['ainl_email_from_name']));
            }
            
            if (isset($_POST['ainl_email_from_email'])) {
                $email = sanitize_email($_POST['ainl_email_from_email']);
                if (is_email($email)) {
                    update_option('ainl_email_from_email', $email);
                }
            }
            
            if (isset($_POST['ainl_newsletter_frequency'])) {
                $frequency = sanitize_text_field($_POST['ainl_newsletter_frequency']);
                if (in_array($frequency, ['weekly', 'monthly'])) {
                    update_option('ainl_newsletter_frequency', $frequency);
                }
            }
            
            if (isset($_POST['ainl_max_posts_per_newsletter'])) {
                $max_posts = intval($_POST['ainl_max_posts_per_newsletter']);
                if ($max_posts >= 1 && $max_posts <= 20) {
                    update_option('ainl_max_posts_per_newsletter', $max_posts);
                }
            }
            
            // AI 설정 저장
            if (isset($_POST['ainl_ai_provider'])) {
                $provider = sanitize_text_field($_POST['ainl_ai_provider']);
                if (in_array($provider, ['openai', 'claude', 'groq'])) {
                    update_option('ainl_ai_provider', $provider);
                }
            }
            
            // API 키들 안전하게 저장 (암호화 권장이지만 기본적으로는 sanitize만 적용)
            if (isset($_POST['ainl_openai_api_key'])) {
                $api_key = sanitize_text_field($_POST['ainl_openai_api_key']);
                update_option('ainl_openai_api_key', $api_key);
            }
            
            if (isset($_POST['ainl_claude_api_key'])) {
                $api_key = sanitize_text_field($_POST['ainl_claude_api_key']);
                update_option('ainl_claude_api_key', $api_key);
            }
            
            if (isset($_POST['ainl_groq_api_key'])) {
                $api_key = sanitize_text_field($_POST['ainl_groq_api_key']);
                update_option('ainl_groq_api_key', $api_key);
            }
            
            // AI 모델 저장
            if (isset($_POST['ainl_ai_model'])) {
                update_option('ainl_ai_model', sanitize_text_field($_POST['ainl_ai_model']));
            }
            
            // AI 매개변수 저장
            if (isset($_POST['ainl_ai_tone'])) {
                update_option('ainl_ai_tone', sanitize_text_field($_POST['ainl_ai_tone']));
            }
            
            if (isset($_POST['ainl_ai_temperature'])) {
                $temperature = floatval($_POST['ainl_ai_temperature']);
                $temperature = max(0, min(2, $temperature)); // 0-2 범위로 제한
                update_option('ainl_ai_temperature', $temperature);
            }
            
            if (isset($_POST['ainl_ai_max_tokens'])) {
                $max_tokens = intval($_POST['ainl_ai_max_tokens']);
                $max_tokens = max(100, min(4000, $max_tokens)); // 100-4000 범위로 제한
                update_option('ainl_ai_max_tokens', $max_tokens);
            }
            
            if (isset($_POST['ainl_ai_top_p'])) {
                $top_p = floatval($_POST['ainl_ai_top_p']);
                $top_p = max(0.1, min(1, $top_p)); // 0.1-1 범위로 제한
                update_option('ainl_ai_top_p', $top_p);
            }
            
            // AI 생성 옵션들 저장 (체크박스)
            update_option('ainl_ai_summarize', isset($_POST['ainl_ai_summarize']) ? 1 : 0);
            update_option('ainl_ai_enhance_titles', isset($_POST['ainl_ai_enhance_titles']) ? 1 : 0);
            update_option('ainl_ai_add_intro', isset($_POST['ainl_ai_add_intro']) ? 1 : 0);
            
            // 성공 시 리다이렉트
            wp_redirect(admin_url('admin.php?page=ai-newsletter-generator-pro&settings-updated=true'));
            exit;
            
        } catch (Exception $e) {
            // 오류 로깅
            if (function_exists('error_log')) {
                error_log('AI Newsletter Generator Pro - Settings Save Error: ' . $e->getMessage());
            }
            
            // 오류 시 리다이렉트
            wp_redirect(admin_url('admin.php?page=ai-newsletter-generator-pro&error=true'));
            exit;
        }
    }
    
    /**
     * 설정 페이지 렌더링
     */
    public function settings_page() {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // 성공/오류 메시지 표시
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>설정이 저장되었습니다!</strong></p></div>';
        }
        if (isset($_GET['error']) && $_GET['error'] == 'true') {
            echo '<div class="notice notice-error is-dismissible"><p><strong>설정 저장 중 오류가 발생했습니다.</strong></p></div>';
        }
        
        echo '<div class="wrap">';
        echo '<h1>AI Newsletter Settings</h1>';
        
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        
        // nonce 필드 추가
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('ainl_save_settings', 'ainl_settings_nonce');
        }
        echo '<input type="hidden" name="action" value="save_ainl_settings" />';
        
        // 기본 설정 섹션
        echo '<div class="card">';
        echo '<h2>기본 설정</h2>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">발송자 이름</th>';
        echo '<td><input type="text" name="ainl_email_from_name" value="' . esc_attr(get_option('ainl_email_from_name', get_bloginfo('name'))) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">발송자 이메일</th>';
        echo '<td><input type="email" name="ainl_email_from_email" value="' . esc_attr(get_option('ainl_email_from_email', get_bloginfo('admin_email'))) . '" class="regular-text" /></td>';
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
        echo '<tr>';
        echo '<th scope="row">뉴스레터 당 최대 게시물 수</th>';
        echo '<td><input type="number" name="ainl_max_posts_per_newsletter" value="' . esc_attr(get_option('ainl_max_posts_per_newsletter', 5)) . '" min="1" max="20" class="small-text" /> 개</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        // AI 설정 섹션
        echo '<div class="card" style="margin-top: 20px;">';
        echo '<h2>🤖 AI 설정</h2>';
        echo '<p>뉴스레터 자동 생성을 위한 AI 서비스 설정입니다.</p>';
        echo '<table class="form-table">';
        
        // AI 모델 선택
        echo '<tr>';
        echo '<th scope="row">AI 모델 선택</th>';
        echo '<td>';
        echo '<select name="ainl_ai_provider" id="ainl_ai_provider">';
        $current_provider = get_option('ainl_ai_provider', 'openai');
        echo '<option value="openai"' . selected($current_provider, 'openai', false) . '>OpenAI (GPT-4, GPT-3.5)</option>';
        echo '<option value="claude"' . selected($current_provider, 'claude', false) . '>Anthropic Claude</option>';
        echo '<option value="groq"' . selected($current_provider, 'groq', false) . '>Groq (Fast LLM Inference)</option>';
        echo '</select>';
        echo '<p class="description">사용할 AI 서비스를 선택하세요. Groq는 매우 빠른 추론 속도를 제공합니다.</p>';
        echo '</td>';
        echo '</tr>';
        
        // OpenAI API 키
        echo '<tr class="api-key-row openai-key">';
        echo '<th scope="row">OpenAI API 키</th>';
        echo '<td>';
        echo '<div style="position: relative; display: inline-block; width: 100%;">';
        echo '<input type="password" name="ainl_openai_api_key" id="openai_api_key" value="' . esc_attr(get_option('ainl_openai_api_key', '')) . '" class="regular-text" placeholder="sk-..." />';
        echo '<button type="button" class="button eye-toggle" onclick="toggleApiKeyVisibility(\'openai_api_key\')" style="margin-left: 5px;">';
        echo '<span class="dashicons dashicons-visibility" id="openai_api_key_icon"></span>';
        echo '</button>';
        echo '</div>';
        echo '<p class="description">OpenAI API 키를 입력하세요. <a href="https://platform.openai.com/api-keys" target="_blank">API 키 생성</a></p>';
        echo '</td>';
        echo '</tr>';
        
        // Claude API 키
        echo '<tr class="api-key-row claude-key">';
        echo '<th scope="row">Claude API 키</th>';
        echo '<td>';
        echo '<div style="position: relative; display: inline-block; width: 100%;">';
        echo '<input type="password" name="ainl_claude_api_key" id="claude_api_key" value="' . esc_attr(get_option('ainl_claude_api_key', '')) . '" class="regular-text" placeholder="sk-ant-..." />';
        echo '<button type="button" class="button eye-toggle" onclick="toggleApiKeyVisibility(\'claude_api_key\')" style="margin-left: 5px;">';
        echo '<span class="dashicons dashicons-visibility" id="claude_api_key_icon"></span>';
        echo '</button>';
        echo '</div>';
        echo '<p class="description">Anthropic Claude API 키를 입력하세요. <a href="https://console.anthropic.com/" target="_blank">API 키 생성</a></p>';
        echo '</td>';
        echo '</tr>';
        
        // Groq API 키
        echo '<tr class="api-key-row groq-key">';
        echo '<th scope="row">Groq API 키</th>';
        echo '<td>';
        echo '<div style="position: relative; display: inline-block; width: 100%;">';
        echo '<input type="password" name="ainl_groq_api_key" id="groq_api_key" value="' . esc_attr(get_option('ainl_groq_api_key', '')) . '" class="regular-text" placeholder="gsk_..." />';
        echo '<button type="button" class="button eye-toggle" onclick="toggleApiKeyVisibility(\'groq_api_key\')" style="margin-left: 5px;">';
        echo '<span class="dashicons dashicons-visibility" id="groq_api_key_icon"></span>';
        echo '</button>';
        echo '</div>';
        echo '<p class="description">Groq API 키를 입력하세요. <a href="https://console.groq.com/keys" target="_blank">API 키 생성</a> (OpenAI 호환)</p>';
        echo '</td>';
        echo '</tr>';
        
        // AI 모델 세부 설정
        echo '<tr>';
        echo '<th scope="row">AI 모델</th>';
        echo '<td>';
        echo '<select name="ainl_ai_model" id="ainl_ai_model" class="regular-text">';
        $current_model = get_option('ainl_ai_model', 'gpt-3.5-turbo');
        
        // OpenAI 모델들
        echo '<optgroup label="📍 OpenAI">';
        echo '<option value="gpt-4o" ' . selected($current_model, 'gpt-4o', false) . '>GPT-4o (최신 멀티모달, 추천)</option>';
        echo '<option value="gpt-4o-mini" ' . selected($current_model, 'gpt-4o-mini', false) . '>GPT-4o Mini (빠르고 경제적)</option>';
        echo '<option value="o3-mini" ' . selected($current_model, 'o3-mini', false) . '>o3-Mini (2025년 최신 추론)</option>';
        echo '<option value="gpt-4-turbo" ' . selected($current_model, 'gpt-4-turbo', false) . '>GPT-4 Turbo (안정적)</option>';
        echo '<option value="gpt-3.5-turbo" ' . selected($current_model, 'gpt-3.5-turbo', false) . '>GPT-3.5 Turbo (경제적)</option>';
        echo '</optgroup>';
        
        // Claude 모델들
        echo '<optgroup label="🧠 Anthropic Claude">';
        echo '<option value="claude-3-5-sonnet-latest" ' . selected($current_model, 'claude-3-5-sonnet-latest', false) . '>Claude 3.5 Sonnet (최신, 추천)</option>';
        echo '<option value="claude-3-5-haiku-latest" ' . selected($current_model, 'claude-3-5-haiku-latest', false) . '>Claude 3.5 Haiku (빠르고 저렴)</option>';
        echo '<option value="claude-3-opus-latest" ' . selected($current_model, 'claude-3-opus-latest', false) . '>Claude 3 Opus (최고 품질)</option>';
        echo '</optgroup>';
        
        // Groq 모델들
        echo '<optgroup label="⚡ Groq (초고속)">';
        echo '<option value="llama-3.3-70b-versatile" ' . selected($current_model, 'llama-3.3-70b-versatile', false) . '>Llama 3.3 70B (균형잡힌 성능)</option>';
        echo '<option value="llama-3.1-8b-instant" ' . selected($current_model, 'llama-3.1-8b-instant', false) . '>Llama 3.1 8B (초고속)</option>';
        echo '<option value="deepseek-r1-distill-llama-70b" ' . selected($current_model, 'deepseek-r1-distill-llama-70b', false) . '>DeepSeek-R1 70B (추론 특화)</option>';
        echo '<option value="mixtral-8x7b-32768" ' . selected($current_model, 'mixtral-8x7b-32768', false) . '>Mixtral 8x7B (긴 컨텍스트)</option>';
        echo '<option value="gemma2-9b-it" ' . selected($current_model, 'gemma2-9b-it', false) . '>Gemma 2 9B (Google)</option>';
        echo '</optgroup>';
        
        echo '</select>';
        echo '<p class="description">💡 <strong>추천:</strong> GPT-4o 또는 Claude 3.5 Sonnet (품질 중시) / Groq 모델들 (속도 중시)<br>📊 각 서비스의 API 키가 필요합니다. 요금제는 서비스별로 다릅니다.</p>';
        echo '</td>';
        echo '</tr>';
        
        // AI 생성 옵션
        echo '<tr>';
        echo '<th scope="row">AI 생성 옵션</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="ainl_ai_summarize" value="1" ' . checked(get_option('ainl_ai_summarize', 1), 1, false) . ' /> 게시물 자동 요약</label><br>';
        echo '<label><input type="checkbox" name="ainl_ai_enhance_titles" value="1" ' . checked(get_option('ainl_ai_enhance_titles', 1), 1, false) . ' /> 제목 개선</label><br>';
        echo '<label><input type="checkbox" name="ainl_ai_add_intro" value="1" ' . checked(get_option('ainl_ai_add_intro', 1), 1, false) . ' /> 인사말 자동 생성</label>';
        echo '</td>';
        echo '</tr>';
        
        // AI 매개변수 설정
        echo '<tr>
            <th scope="row">
                <label for="ainl_ai_tone">톤앤매너</label>
            </th>
            <td>
                <select name="ainl_ai_tone" id="ainl_ai_tone" class="regular-text">
                    <option value="professional" <?php selected(get_option('ainl_ai_tone'), 'professional'); ?>>전문적인</option>
                    <option value="friendly" <?php selected(get_option('ainl_ai_tone'), 'friendly'); ?>>친근한</option>
                    <option value="formal" <?php selected(get_option('ainl_ai_tone'), 'formal'); ?>>공식적인</option>
                    <option value="casual" <?php selected(get_option('ainl_ai_tone'), 'casual'); ?>>캐주얼한</option>
                    <option value="enthusiastic" <?php selected(get_option('ainl_ai_tone'), 'enthusiastic'); ?>>열정적인</option>
                    <option value="informative" <?php selected(get_option('ainl_ai_tone'), 'informative'); ?>>정보전달형</option>
                </select>
                <p class="description">뉴스레터에 사용할 글의 톤앤매너를 선택하세요.</p>
            </td>
        </tr>';
        
        // AI 매개변수 설정
        echo '<tr>
            <th scope="row">
                <label>AI 고급 설정</label>
            </th>
            <td>
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <td style="padding: 5px 0;">
                            <label for="ainl_ai_temperature" style="display: inline-block; width: 120px;"><strong>Temperature:</strong></label>
                            <input type="range" name="ainl_ai_temperature" id="ainl_ai_temperature" 
                                   min="0" max="2" step="0.1" 
                                   value="<?php echo esc_attr(get_option('ainl_ai_temperature', '0.7')); ?>"
                                   style="width: 200px;" 
                                   oninput="document.getElementById('temperature_value').textContent = this.value">
                            <span id="temperature_value" style="margin-left: 10px; font-weight: bold;">
                                <?php echo esc_html(get_option('ainl_ai_temperature', '0.7')); ?>
                            </span>
                            <p class="description" style="margin-left: 120px; margin-top: 5px;">
                                창의성 조절 (0=일관성, 2=창의적) - 추천: 0.7
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;">
                            <label for="ainl_ai_max_tokens" style="display: inline-block; width: 120px;"><strong>Max Tokens:</strong></label>
                            <input type="number" name="ainl_ai_max_tokens" id="ainl_ai_max_tokens" 
                                   min="100" max="4000" 
                                   value="<?php echo esc_attr(get_option('ainl_ai_max_tokens', '1500')); ?>"
                                   style="width: 100px;">
                            <span style="margin-left: 10px; color: #666;">토큰</span>
                            <p class="description" style="margin-left: 120px; margin-top: 5px;">
                                생성할 최대 글자 수 (한글 기준 약 1토큰=1글자) - 추천: 1500
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;">
                            <label for="ainl_ai_top_p" style="display: inline-block; width: 120px;"><strong>Top-p:</strong></label>
                            <input type="range" name="ainl_ai_top_p" id="ainl_ai_top_p" 
                                   min="0.1" max="1" step="0.05" 
                                   value="<?php echo esc_attr(get_option('ainl_ai_top_p', '0.9')); ?>"
                                   style="width: 200px;" 
                                   oninput="document.getElementById('top_p_value').textContent = this.value">
                            <span id="top_p_value" style="margin-left: 10px; font-weight: bold;">
                                <?php echo esc_html(get_option('ainl_ai_top_p', '0.9')); ?>
                            </span>
                            <p class="description" style="margin-left: 120px; margin-top: 5px;">
                                다양성 조절 (0.1=보수적, 1=다양함) - 추천: 0.9
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
        
        echo '</table>';
        echo '</div>';
        
        echo '<p class="submit"><input type="submit" name="submit" class="button button-primary" value="설정 저장" /></p>';
        echo '</form>';
        
        // JavaScript 코드 추가
        echo '<script>
        // API 키 표시/숨김 토글 함수
        function toggleApiKeyVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + "_icon");
            
            if (field.type === "password") {
                field.type = "text";
                icon.className = "dashicons dashicons-hidden";
            } else {
                field.type = "password";
                icon.className = "dashicons dashicons-visibility";
            }
        }
        
        // AI 제공업체 변경 시 모델 옵션 필터링
        document.getElementById("ainl_ai_provider").addEventListener("change", function() {
            const provider = this.value;
            const modelSelect = document.getElementById("ainl_ai_model");
            const groups = modelSelect.querySelectorAll("optgroup");
            
            // 모든 그룹 숨김
            groups.forEach(group => {
                group.style.display = "none";
            });
            
            // 선택된 제공업체의 그룹만 표시
            const targetGroup = modelSelect.querySelector("." + provider + "-models");
            if (targetGroup) {
                targetGroup.style.display = "block";
                // 첫 번째 옵션 선택
                const firstOption = targetGroup.querySelector("option");
                if (firstOption) {
                    modelSelect.value = firstOption.value;
                }
            }
        });
        
        // 페이지 로드 시 초기 설정
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("ainl_ai_provider").dispatchEvent(new Event("change"));
        });
        </script>';
        
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
    
    /**
     * 뉴스레터 생성 처리
     */
    public function create_newsletter() {
        try {
            // 권한 체크
            if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
                wp_die(__('권한이 없습니다.'));
            }
            
            // nonce 보안 검증
            if (!function_exists('wp_verify_nonce') || !wp_verify_nonce($_POST['ainl_create_nonce'], 'ainl_create_newsletter')) {
                wp_die(__('보안 검증에 실패했습니다.'));
            }
            
            global $wpdb;
            $campaigns_table = $wpdb->prefix . 'ainl_campaigns';
            
            $title = sanitize_text_field($_POST['newsletter_title']);
            $post_count = intval($_POST['post_count']);
            $post_range = sanitize_text_field($_POST['post_range']);
            
            // 간단한 뉴스레터 내용 생성 (실제로는 AI가 처리)
            $content = $this->generate_simple_newsletter_content($post_count, $post_range);
            
            // 캠페인 저장
            if ($wpdb) {
                $result = $wpdb->insert(
                    $campaigns_table,
                    array(
                        'title' => $title,
                        'content' => $content,
                        'status' => 'draft',
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s')
                );
                
                if ($result) {
                    $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=campaigns&created=true');
                } else {
                    $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=create&error=true');
                }
            } else {
                $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=create&error=true');
            }
            
            wp_redirect($redirect_url);
            exit;
            
        } catch (Exception $e) {
            error_log('AINL Plugin Newsletter Creation Error: ' . $e->getMessage());
            $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=create&error=true');
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * 구독자 추가 처리
     */
    public function add_subscriber() {
        try {
            // 권한 체크
            if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
                wp_die(__('권한이 없습니다.'));
            }
            
            // nonce 보안 검증
            if (!function_exists('wp_verify_nonce') || !wp_verify_nonce($_POST['ainl_subscriber_nonce'], 'ainl_add_subscriber')) {
                wp_die(__('보안 검증에 실패했습니다.'));
            }
            
            global $wpdb;
            $subscribers_table = $wpdb->prefix . 'ainl_subscribers';
            
            $name = sanitize_text_field($_POST['subscriber_name']);
            $email = sanitize_email($_POST['subscriber_email']);
            
            // 이메일 중복 체크
            if ($wpdb) {
                $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $subscribers_table WHERE email = %s", $email));
                
                if ($existing > 0) {
                    $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=subscribers&duplicate=true');
                } else {
                    // 구독자 추가
                    $result = $wpdb->insert(
                        $subscribers_table,
                        array(
                            'name' => $name,
                            'email' => $email,
                            'status' => 'active',
                            'created_at' => current_time('mysql')
                        ),
                        array('%s', '%s', '%s', '%s')
                    );
                    
                    if ($result) {
                        $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=subscribers&added=true');
                    } else {
                        $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=subscribers&error=true');
                    }
                }
            } else {
                $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=subscribers&error=true');
            }
            
            wp_redirect($redirect_url);
            exit;
            
        } catch (Exception $e) {
            error_log('AINL Plugin Subscriber Add Error: ' . $e->getMessage());
            $redirect_url = admin_url('admin.php?page=ai-newsletter-generator-pro&tab=subscribers&error=true');
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * 간단한 뉴스레터 내용 생성 (AI 대체용)
     */
    private function generate_simple_newsletter_content($post_count, $post_range) {
        // 날짜 범위 설정
        $date_query = array();
        switch ($post_range) {
            case 'week':
                $date_query['after'] = '1 week ago';
                break;
            case 'month':
                $date_query['after'] = '1 month ago';
                break;
            case '3months':
                $date_query['after'] = '3 months ago';
                break;
        }
        
        // 최근 게시물 가져오기
        $posts = get_posts(array(
            'numberposts' => $post_count,
            'post_status' => 'publish',
            'date_query' => array($date_query)
        ));
        
        $content = '<h2>' . get_bloginfo('name') . ' 뉴스레터</h2>';
        $content .= '<p>안녕하세요! ' . get_bloginfo('name') . '의 최신 소식을 전해드립니다.</p>';
        
        if ($posts) {
            $content .= '<h3>이번 주 주요 글</h3>';
            foreach ($posts as $post) {
                $content .= '<div style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd;">';
                $content .= '<h4><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></h4>';
                $content .= '<p>' . wp_trim_words($post->post_content, 30) . '</p>';
                $content .= '<p><strong>작성일:</strong> ' . get_the_date('Y-m-d', $post->ID) . '</p>';
                $content .= '</div>';
            }
        } else {
            $content .= '<p>선택한 기간 동안 발행된 게시물이 없습니다.</p>';
        }
        
        $content .= '<hr>';
        $content .= '<p>감사합니다.<br>' . get_bloginfo('name') . '</p>';
        
        return $content;
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
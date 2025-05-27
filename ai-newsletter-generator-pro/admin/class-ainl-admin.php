<?php
/**
 * 관리자 인터페이스 클래스
 * WordPress 관리자 메뉴와 페이지를 관리합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Admin {
    
    /**
     * 생성자 - 관리자 훅 초기화
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    /**
     * 관리자 메뉴 추가
     * 메인 메뉴와 서브메뉴를 생성합니다.
     */
    public function add_admin_menu() {
        // 메인 메뉴 추가
        add_menu_page(
            'AI Newsletter Generator Pro',           // 페이지 제목
            'AI Newsletter',                         // 메뉴 제목
            'manage_options',                        // 권한
            'ai-newsletter',                         // 메뉴 슬러그
            array($this, 'dashboard_page'),          // 콜백 함수
            'dashicons-email-alt',                   // 아이콘
            30                                       // 메뉴 위치
        );
        
        // 대시보드 서브메뉴 (메인 메뉴와 동일)
        add_submenu_page(
            'ai-newsletter',
            '대시보드',
            '대시보드',
            'manage_options',
            'ai-newsletter',
            array($this, 'dashboard_page')
        );
        
        // 캠페인 관리 서브메뉴
        add_submenu_page(
            'ai-newsletter',
            '캠페인 관리',
            '캠페인',
            'manage_options',
            'ai-newsletter-campaigns',
            array($this, 'campaigns_page')
        );
        
        // 구독자 관리 서브메뉴
        add_submenu_page(
            'ai-newsletter',
            '구독자 관리',
            '구독자',
            'manage_options',
            'ai-newsletter-subscribers',
            array($this, 'subscribers_page')
        );
        
        // 템플릿 관리 서브메뉴
        add_submenu_page(
            'ai-newsletter',
            '템플릿 관리',
            '템플릿',
            'manage_options',
            'ai-newsletter-templates',
            array($this, 'templates_page')
        );
        
        // 통계 서브메뉴
        add_submenu_page(
            'ai-newsletter',
            '통계 및 분석',
            '통계',
            'manage_options',
            'ai-newsletter-statistics',
            array($this, 'statistics_page')
        );
        
        // 구독 폼 서브메뉴
        add_submenu_page(
            'ai-newsletter',
            '구독 폼',
            '구독 폼',
            'manage_options',
            'ai-newsletter-forms',
            array($this, 'forms_page')
        );
        
        // 설정 서브메뉴
        add_submenu_page(
            'ai-newsletter',
            '설정',
            '설정',
            'manage_options',
            'ai-newsletter-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * 관리자 스크립트 및 스타일 로드
     */
    public function enqueue_admin_scripts($hook) {
        // AI Newsletter 페이지에서만 로드
        if (strpos($hook, 'ai-newsletter') === false) {
            return;
        }
        
        // CSS 파일 로드
        wp_enqueue_style(
            'ainl-admin-style',
            AINL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AINL_PLUGIN_VERSION
        );
        
        // JavaScript 파일 로드
        wp_enqueue_script(
            'ainl-admin-script',
            AINL_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AINL_PLUGIN_VERSION,
            true
        );
        
        // AJAX 설정을 위한 데이터 전달
        wp_localize_script('ainl-admin-script', 'ainl_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ainl_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('정말로 삭제하시겠습니까?', 'ai-newsletter-generator-pro'),
                'saving' => __('저장 중...', 'ai-newsletter-generator-pro'),
                'saved' => __('저장되었습니다.', 'ai-newsletter-generator-pro'),
                'error' => __('오류가 발생했습니다.', 'ai-newsletter-generator-pro')
            )
        ));
    }
    
    /**
     * 관리자 초기화
     */
    public function admin_init() {
        // 권한 체크
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 설정 등록 (작업 9에서 구현 예정)
        // $this->register_settings();
    }
    
    /**
     * 대시보드 페이지
     */
    public function dashboard_page() {
        // 보안 체크
        AINL_Security::admin_page_security_check('edit_posts');
        
        $this->render_page_header('대시보드', '플러그인 개요 및 주요 통계');
        ?>
        <div class="ainl-dashboard">
            <div class="ainl-dashboard-widgets">
                <!-- 통계 위젯들 -->
                <div class="ainl-widget">
                    <h3>총 구독자</h3>
                    <div class="ainl-stat-number"><?php echo $this->get_total_subscribers(); ?></div>
                </div>
                
                <div class="ainl-widget">
                    <h3>총 캠페인</h3>
                    <div class="ainl-stat-number"><?php echo $this->get_total_campaigns(); ?></div>
                </div>
                
                <div class="ainl-widget">
                    <h3>이번 달 발송</h3>
                    <div class="ainl-stat-number"><?php echo $this->get_monthly_sends(); ?></div>
                </div>
                
                <div class="ainl-widget">
                    <h3>평균 오픈율</h3>
                    <div class="ainl-stat-number"><?php echo $this->get_average_open_rate(); ?>%</div>
                </div>
            </div>
            
            <div class="ainl-dashboard-actions">
                <h3>빠른 작업</h3>
                <div class="ainl-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=ai-newsletter-campaigns&action=new'); ?>" class="button button-primary">
                        새 캠페인 만들기
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=ai-newsletter-subscribers&action=import'); ?>" class="button">
                        구독자 가져오기
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=ai-newsletter-settings'); ?>" class="button">
                        설정 관리
                    </a>
                </div>
            </div>
            
            <div class="ainl-recent-activity">
                <h3>최근 활동</h3>
                <?php $this->render_recent_activity(); ?>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 캠페인 관리 페이지
     */
    public function campaigns_page() {
        // 보안 체크
        AINL_Security::admin_page_security_check('edit_posts');
        
        $this->render_page_header('캠페인 관리', '뉴스레터 캠페인 생성 및 관리');
        ?>
        <div class="ainl-campaigns">
            <div class="ainl-page-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-newsletter-campaigns&action=new'); ?>" class="button button-primary">
                    새 캠페인 추가
                </a>
            </div>
            
            <div class="ainl-campaigns-list">
                <p>캠페인 목록이 여기에 표시됩니다. (작업 11에서 구현 예정)</p>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 구독자 관리 페이지
     */
    public function subscribers_page() {
        // 보안 체크
        AINL_Security::admin_page_security_check('edit_posts');
        
        $this->render_page_header('구독자 관리', '구독자 목록 및 관리');
        ?>
        <div class="ainl-subscribers">
            <div class="ainl-page-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-newsletter-subscribers&action=new'); ?>" class="button button-primary">
                    구독자 추가
                </a>
                <a href="<?php echo admin_url('admin.php?page=ai-newsletter-subscribers&action=import'); ?>" class="button">
                    CSV 가져오기
                </a>
            </div>
            
            <div class="ainl-subscribers-list">
                <p>구독자 목록이 여기에 표시됩니다. (작업 7에서 구현 예정)</p>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 템플릿 관리 페이지
     */
    public function templates_page() {
        // 보안 체크
        AINL_Security::admin_page_security_check('edit_posts');
        
        $this->render_page_header('템플릿 관리', '이메일 템플릿 생성 및 편집');
        ?>
        <div class="ainl-templates">
            <div class="ainl-page-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-newsletter-templates&action=new'); ?>" class="button button-primary">
                    새 템플릿 추가
                </a>
            </div>
            
            <div class="ainl-templates-list">
                <p>템플릿 목록이 여기에 표시됩니다. (작업 6에서 구현 예정)</p>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 통계 페이지
     */
    public function statistics_page() {
        // 보안 체크
        AINL_Security::admin_page_security_check('edit_posts');
        
        $this->render_page_header('통계 및 분석', '캠페인 성과 및 구독자 분석');
        ?>
        <div class="ainl-statistics">
            <div class="ainl-stats-overview">
                <p>통계 차트와 분석이 여기에 표시됩니다. (작업 12에서 구현 예정)</p>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 구독 폼 페이지
     */
    public function forms_page() {
        // 보안 체크
        AINL_Security::admin_page_security_check('edit_posts');
        
        $this->render_page_header('구독 폼', '웹사이트 구독 폼 생성 및 관리');
        ?>
        <div class="ainl-forms">
            <div class="ainl-page-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-newsletter-forms&action=new'); ?>" class="button button-primary">
                    새 폼 만들기
                </a>
            </div>
            
            <div class="ainl-forms-list">
                <p>구독 폼 목록이 여기에 표시됩니다. (작업 10에서 구현 예정)</p>
            </div>
        </div>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 설정 페이지
     */
    public function settings_page() {
        // 보안 체크
        AINL_Security::admin_page_security_check('manage_options');
        
        $this->render_page_header('설정', '플러그인 기본 설정 및 구성');
        
        // 설정 저장 메시지 표시
        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>설정이 저장되었습니다.</p></div>';
        }
        ?>
        <div class="ainl-settings">
            <form method="post" action="options.php">
                <?php
                settings_fields('ainl_settings_group');
                do_settings_sections('ainl_settings');
                submit_button('설정 저장');
                ?>
            </form>
            
            <?php
            // 보안 테스트 실행
            if (isset($_GET['test_security']) && $_GET['test_security'] === '1') {
                $test_results = AINL_Security_Test::run_all_tests();
                AINL_Security_Test::display_test_results($test_results);
            } else {
                echo '<div class="ainl-test-section">';
                echo '<h3>보안 테스트</h3>';
                echo '<p>플러그인의 보안 시스템이 올바르게 작동하는지 확인할 수 있습니다.</p>';
                echo '<a href="' . admin_url('admin.php?page=ai-newsletter-settings&test_security=1') . '" class="button">보안 테스트 실행</a>';
                echo '</div>';
            }
            
            // 설정 테스트 실행
            if (isset($_GET['test_settings']) && $_GET['test_settings'] === '1') {
                $test_results = AINL_Settings_Test::run_all_tests();
                AINL_Settings_Test::display_test_results($test_results);
            } else {
                echo '<div class="ainl-test-section">';
                echo '<h3>설정 테스트</h3>';
                echo '<p>설정 시스템이 올바르게 작동하는지 확인할 수 있습니다.</p>';
                echo '<a href="' . admin_url('admin.php?page=ai-newsletter-settings&test_settings=1') . '" class="button">설정 테스트 실행</a>';
                echo '</div>';
            }
            
            // 게시물 수집 테스트 실행
            if (isset($_GET['test_post_collector']) && $_GET['test_post_collector'] === '1') {
                $test_results = AINL_Post_Collector_Test::run_all_tests();
                AINL_Post_Collector_Test::display_test_results($test_results);
            } else {
                echo '<div class="ainl-test-section">';
                echo '<h3>게시물 수집 테스트</h3>';
                echo '<p>WordPress 게시물 수집 시스템이 올바르게 작동하는지 확인할 수 있습니다.</p>';
                echo '<a href="' . admin_url('admin.php?page=ai-newsletter-settings&test_post_collector=1') . '" class="button">게시물 수집 테스트 실행</a>';
                echo '</div>';
            }
            ?>
            
            <div class="ainl-settings-help">
                <h3>도움말</h3>
                <div class="ainl-help-section">
                    <h4>AI API 키 설정</h4>
                    <p>OpenAI API 키는 <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI 플랫폼</a>에서 발급받을 수 있습니다.</p>
                    <p>Claude API 키는 <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>에서 발급받을 수 있습니다.</p>
                </div>
                
                <div class="ainl-help-section">
                    <h4>SMTP 설정</h4>
                    <p><strong>Gmail 사용 시:</strong></p>
                    <ul>
                        <li>SMTP 호스트: smtp.gmail.com</li>
                        <li>SMTP 포트: 587</li>
                        <li>암호화: TLS</li>
                        <li>앱 비밀번호를 사용하세요 (2단계 인증 필요)</li>
                    </ul>
                </div>
                
                <div class="ainl-help-section">
                    <h4>콘텐츠 설정</h4>
                    <p>날짜 범위는 뉴스레터 생성 시 포함할 게시물의 기간을 설정합니다.</p>
                    <p>최대 게시물 수는 한 번의 뉴스레터에 포함될 게시물의 최대 개수입니다.</p>
                </div>
            </div>
        </div>
        
        <style>
        .ainl-test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f0f8ff;
            border: 1px solid #007cba;
            border-radius: 4px;
        }
        
        .ainl-test-section h3 {
            margin: 0 0 10px 0;
            color: #007cba;
        }
        
        .ainl-settings-help {
            margin-top: 30px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
        }
        
        .ainl-help-section {
            margin-bottom: 20px;
        }
        
        .ainl-help-section h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .ainl-help-section ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .ainl-help-section li {
            margin-bottom: 5px;
        }
        </style>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 페이지 헤더 렌더링
     */
    private function render_page_header($title, $description = '') {
        ?>
        <div class="wrap ainl-admin-page">
            <h1 class="ainl-page-title">
                <span class="ainl-icon"></span>
                <?php echo esc_html($title); ?>
            </h1>
            <?php if ($description): ?>
                <p class="ainl-page-description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        <?php
    }
    
    /**
     * 페이지 푸터 렌더링
     */
    private function render_page_footer() {
        ?>
        </div>
        <?php
    }
    
    /**
     * 총 구독자 수 조회
     */
    private function get_total_subscribers() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ainl_subscribers WHERE status = 'active'");
    }
    
    /**
     * 총 캠페인 수 조회
     */
    private function get_total_campaigns() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ainl_campaigns");
    }
    
    /**
     * 이번 달 발송 수 조회
     */
    private function get_monthly_sends() {
        global $wpdb;
        $start_of_month = date('Y-m-01 00:00:00');
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_sent) FROM {$wpdb->prefix}ainl_campaigns WHERE sent_at >= %s",
            $start_of_month
        ));
    }
    
    /**
     * 평균 오픈율 조회
     */
    private function get_average_open_rate() {
        global $wpdb;
        $result = $wpdb->get_row("
            SELECT 
                SUM(total_opens) as total_opens,
                SUM(total_sent) as total_sent
            FROM {$wpdb->prefix}ainl_campaigns 
            WHERE total_sent > 0
        ");
        
        if ($result && $result->total_sent > 0) {
            return round(($result->total_opens / $result->total_sent) * 100, 1);
        }
        
        return 0;
    }
    
    /**
     * 최근 활동 렌더링
     */
    private function render_recent_activity() {
        global $wpdb;
        
        $recent_campaigns = $wpdb->get_results("
            SELECT name, status, created_at 
            FROM {$wpdb->prefix}ainl_campaigns 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        
        if ($recent_campaigns) {
            echo '<ul class="ainl-activity-list">';
            foreach ($recent_campaigns as $campaign) {
                echo '<li>';
                echo '<strong>' . esc_html($campaign->name) . '</strong> ';
                echo '<span class="status status-' . esc_attr($campaign->status) . '">' . esc_html($campaign->status) . '</span> ';
                echo '<span class="date">' . esc_html(date('Y-m-d H:i', strtotime($campaign->created_at))) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>아직 활동이 없습니다.</p>';
        }
    }
} 
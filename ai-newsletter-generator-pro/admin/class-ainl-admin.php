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
        add_action('wp_ajax_ainl_test_templates', array($this, 'ajax_test_templates'));
        add_action('wp_ajax_ainl_get_template_preview', array($this, 'ajax_get_template_preview'));
        add_action('wp_ajax_ainl_add_subscriber', array($this, 'ajax_add_subscriber'));
        add_action('wp_ajax_ainl_update_subscriber', array($this, 'ajax_update_subscriber'));
        add_action('wp_ajax_ainl_delete_subscriber', array($this, 'ajax_delete_subscriber'));
        add_action('wp_ajax_ainl_bulk_action_subscribers', array($this, 'ajax_bulk_action_subscribers'));
        add_action('wp_ajax_ainl_import_subscribers', array($this, 'ajax_import_subscribers'));
        add_action('wp_ajax_ainl_export_subscribers', array($this, 'ajax_export_subscribers'));
        add_action('wp_ajax_ainl_test_smtp', array($this, 'ajax_test_smtp'));
        add_action('wp_ajax_ainl_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_ainl_clear_email_queue', array($this, 'ajax_clear_email_queue'));
        add_action('wp_ajax_ainl_process_email_queue', array($this, 'ajax_process_email_queue'));
        add_action('wp_ajax_ainl_refresh_queue_status', array($this, 'ajax_refresh_queue_status'));
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
        if (strpos($hook, 'ai-newsletter') === false && strpos($hook, 'ainl-') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
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
            array('jquery', 'jquery-ui-dialog'),
            AINL_PLUGIN_VERSION,
            true
        );
        
        // AJAX 설정을 위한 데이터 전달
        wp_localize_script('ainl-admin-script', 'ainl_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ainl_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('정말로 삭제하시겠습니까?', 'ai-newsletter-generator-pro'),
                'bulk_confirm_delete' => __('선택한 구독자들을 정말로 삭제하시겠습니까?', 'ai-newsletter-generator-pro'),
                'processing' => __('처리 중...', 'ai-newsletter-generator-pro'),
                'error' => __('오류가 발생했습니다.', 'ai-newsletter-generator-pro'),
                'success' => __('성공적으로 처리되었습니다.', 'ai-newsletter-generator-pro')
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
        
        // 액션 처리
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $template_id = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : '';
        
        switch ($action) {
            case 'preview':
                $this->render_template_preview($template_id);
                break;
            case 'test':
                $this->render_template_test();
                break;
            default:
                $this->render_templates_list();
                break;
        }
    }
    
    /**
     * 템플릿 목록 렌더링
     */
    private function render_templates_list() {
        $this->render_page_header('템플릿 관리', '이메일 템플릿 생성 및 편집');
        
        // 템플릿 매니저 인스턴스 생성
        $template_manager = new AINL_Template_Manager();
        $templates = $template_manager->get_default_templates();
        ?>
        <div class="ainl-templates">
            <div class="ainl-page-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-newsletter-templates&action=test'); ?>" class="button button-secondary">
                    템플릿 시스템 테스트
                </a>
            </div>
            
            <div class="ainl-templates-grid">
                <?php foreach ($templates as $key => $template): ?>
                <div class="ainl-template-card">
                    <div class="ainl-template-preview">
                        <iframe src="<?php echo admin_url('admin.php?page=ai-newsletter-templates&action=preview&template=' . $key); ?>" 
                                width="100%" height="300" frameborder="0"></iframe>
                    </div>
                    <div class="ainl-template-info">
                        <h3><?php echo esc_html($template['name']); ?></h3>
                        <p><?php echo esc_html($template['description']); ?></p>
                        <div class="ainl-template-actions">
                            <a href="<?php echo admin_url('admin.php?page=ai-newsletter-templates&action=preview&template=' . $key); ?>" 
                               class="button button-secondary" target="_blank">미리보기</a>
                            <button class="button button-primary ainl-select-template" data-template="<?php echo esc_attr($key); ?>">
                                선택
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="ainl-template-variables">
                <h3>사용 가능한 템플릿 변수</h3>
                <div class="ainl-variables-list">
                    <?php 
                    $variables = $template_manager->get_available_variables();
                    foreach ($variables as $variable): 
                    ?>
                    <code><?php echo esc_html($variable); ?></code>
                    <?php endforeach; ?>
                </div>
                <p class="description">
                    위 변수들을 사용하여 커스텀 템플릿을 만들 수 있습니다. 
                    각 변수는 뉴스레터 생성 시 실제 데이터로 치환됩니다.
                </p>
            </div>
        </div>
        
        <style>
        .ainl-templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .ainl-template-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        .ainl-template-preview {
            height: 300px;
            overflow: hidden;
        }
        .ainl-template-info {
            padding: 15px;
        }
        .ainl-template-info h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .ainl-template-info p {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 14px;
        }
        .ainl-template-actions {
            display: flex;
            gap: 10px;
        }
        .ainl-variables-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        .ainl-variables-list code {
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        </style>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 템플릿 미리보기 렌더링
     */
    private function render_template_preview($template_id) {
        if (empty($template_id)) {
            wp_die('템플릿 ID가 필요합니다.');
        }
        
        $template_manager = new AINL_Template_Manager();
        $preview_html = $template_manager->generate_preview($template_id);
        
        if ($preview_html === false) {
            wp_die('유효하지 않은 템플릿 ID입니다.');
        }
        
        // HTML 헤더 설정
        header('Content-Type: text/html; charset=UTF-8');
        
        // 미리보기 HTML 출력
        echo $preview_html;
        exit;
    }
    
    /**
     * 템플릿 시스템 테스트 페이지 렌더링
     */
    private function render_template_test() {
        $this->render_page_header('템플릿 시스템 테스트', '템플릿 시스템의 모든 기능을 검증합니다');
        
        // 테스트 실행
        $test_results = AINL_Template_Test::run_all_tests();
        
        // 테스트 결과 표시
        AINL_Template_Test::display_test_results($test_results);
        
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
        
        // 설정 저장 처리
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['ainl_settings_nonce'], 'ainl_settings_save')) {
            $this->save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>설정이 저장되었습니다.</p></div>';
        }
        
        // 현재 설정 로드
        $settings = get_option('ainl_settings', array());
        
        // 이메일 매니저 인스턴스
        $email_manager = AINL_Email_Manager::get_instance();
        $queue_status = $email_manager->get_queue_status();
        ?>
        <div class="ainl-settings">
            <!-- 탭 네비게이션 -->
            <div class="ainl-tabs">
                <ul class="ainl-tab-nav">
                    <li><a href="#general" class="ainl-tab-link active">일반 설정</a></li>
                    <li><a href="#smtp" class="ainl-tab-link">SMTP 설정</a></li>
                    <li><a href="#email-queue" class="ainl-tab-link">이메일 큐</a></li>
                    <li><a href="#tests" class="ainl-tab-link">테스트</a></li>
                </ul>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('ainl_settings_save', 'ainl_settings_nonce'); ?>
                
                <!-- 일반 설정 탭 -->
                <div id="general" class="ainl-tab-content active">
                    <h3>AI API 설정</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">OpenAI API 키</th>
                            <td>
                                <input type="password" name="openai_api_key" value="<?php echo esc_attr(isset($settings['openai_api_key']) ? $settings['openai_api_key'] : ''); ?>" class="regular-text" />
                                <p class="description">OpenAI GPT API 키를 입력하세요.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Claude API 키</th>
                            <td>
                                <input type="password" name="claude_api_key" value="<?php echo esc_attr(isset($settings['claude_api_key']) ? $settings['claude_api_key'] : ''); ?>" class="regular-text" />
                                <p class="description">Anthropic Claude API 키를 입력하세요.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>콘텐츠 설정</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">날짜 범위 (일)</th>
                            <td>
                                <input type="number" name="content_date_range" value="<?php echo esc_attr(isset($settings['content_date_range']) ? $settings['content_date_range'] : '7'); ?>" min="1" max="30" />
                                <p class="description">뉴스레터에 포함할 게시물의 날짜 범위를 설정합니다.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">최대 게시물 수</th>
                            <td>
                                <input type="number" name="max_posts" value="<?php echo esc_attr(isset($settings['max_posts']) ? $settings['max_posts'] : '10'); ?>" min="1" max="50" />
                                <p class="description">한 번의 뉴스레터에 포함될 최대 게시물 수입니다.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- SMTP 설정 탭 -->
                <div id="smtp" class="ainl-tab-content">
                    <h3>SMTP 서버 설정</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">SMTP 호스트</th>
                            <td>
                                <input type="text" name="smtp_host" value="<?php echo esc_attr(isset($settings['smtp_host']) ? $settings['smtp_host'] : ''); ?>" class="regular-text" />
                                <p class="description">SMTP 서버 주소 (예: smtp.gmail.com)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">SMTP 포트</th>
                            <td>
                                <input type="number" name="smtp_port" value="<?php echo esc_attr(isset($settings['smtp_port']) ? $settings['smtp_port'] : '587'); ?>" min="1" max="65535" />
                                <p class="description">SMTP 포트 번호 (일반적으로 587 또는 465)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">암호화</th>
                            <td>
                                <select name="smtp_encryption">
                                    <option value="tls" <?php selected(isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls', 'tls'); ?>>TLS</option>
                                    <option value="ssl" <?php selected(isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls', 'ssl'); ?>>SSL</option>
                                    <option value="none" <?php selected(isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls', 'none'); ?>>없음</option>
                                </select>
                                <p class="description">SMTP 암호화 방식</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">사용자명</th>
                            <td>
                                <input type="text" name="smtp_username" value="<?php echo esc_attr(isset($settings['smtp_username']) ? $settings['smtp_username'] : ''); ?>" class="regular-text" />
                                <p class="description">SMTP 인증 사용자명 (보통 이메일 주소)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">비밀번호</th>
                            <td>
                                <input type="password" name="smtp_password" value="<?php echo esc_attr(isset($settings['smtp_password']) ? $settings['smtp_password'] : ''); ?>" class="regular-text" />
                                <p class="description">SMTP 인증 비밀번호</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>발신자 정보</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">발신자 이름</th>
                            <td>
                                <input type="text" name="from_name" value="<?php echo esc_attr(isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name')); ?>" class="regular-text" />
                                <p class="description">이메일 발신자로 표시될 이름</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">발신자 이메일</th>
                            <td>
                                <input type="email" name="from_email" value="<?php echo esc_attr(isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email')); ?>" class="regular-text" />
                                <p class="description">이메일 발신자 주소</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>발송 설정</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">발송 속도 제한</th>
                            <td>
                                <input type="number" name="email_rate_limit" value="<?php echo esc_attr(isset($settings['email_rate_limit']) ? $settings['email_rate_limit'] : '5'); ?>" min="1" max="100" />
                                <span>이메일/초</span>
                                <p class="description">초당 발송할 이메일 수 (서버 부하 방지)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">배치 크기</th>
                            <td>
                                <input type="number" name="email_batch_size" value="<?php echo esc_attr(isset($settings['email_batch_size']) ? $settings['email_batch_size'] : '50'); ?>" min="1" max="500" />
                                <span>이메일/배치</span>
                                <p class="description">한 번에 처리할 이메일 수</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">최대 재시도 횟수</th>
                            <td>
                                <input type="number" name="email_max_attempts" value="<?php echo esc_attr(isset($settings['email_max_attempts']) ? $settings['email_max_attempts'] : '3'); ?>" min="1" max="10" />
                                <span>회</span>
                                <p class="description">발송 실패 시 재시도할 최대 횟수</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="ainl-smtp-test">
                        <h3>SMTP 연결 테스트</h3>
                        <p>SMTP 설정이 올바른지 확인하세요.</p>
                        <button type="button" id="test-smtp" class="button">SMTP 연결 테스트</button>
                        <button type="button" id="send-test-email" class="button">테스트 이메일 발송</button>
                        <input type="email" id="test-email" placeholder="테스트 이메일 주소" class="regular-text" style="margin-left: 10px;" />
                        <div id="smtp-test-result" style="margin-top: 10px;"></div>
                    </div>
                </div>
                
                <!-- 이메일 큐 탭 -->
                <div id="email-queue" class="ainl-tab-content">
                    <h3>이메일 큐 상태</h3>
                    <div class="ainl-queue-stats">
                        <div class="ainl-stat-card">
                            <h4>대기 중</h4>
                            <div class="ainl-stat-number pending"><?php echo $queue_status['pending']; ?></div>
                        </div>
                        <div class="ainl-stat-card">
                            <h4>발송 중</h4>
                            <div class="ainl-stat-number sending"><?php echo $queue_status['sending']; ?></div>
                        </div>
                        <div class="ainl-stat-card">
                            <h4>발송 완료</h4>
                            <div class="ainl-stat-number sent"><?php echo $queue_status['sent']; ?></div>
                        </div>
                        <div class="ainl-stat-card">
                            <h4>발송 실패</h4>
                            <div class="ainl-stat-number failed"><?php echo $queue_status['failed']; ?></div>
                        </div>
                        <div class="ainl-stat-card">
                            <h4>전체</h4>
                            <div class="ainl-stat-number total"><?php echo $queue_status['total']; ?></div>
                        </div>
                    </div>
                    
                    <div class="ainl-queue-actions">
                        <h3>큐 관리</h3>
                        <p>이메일 큐를 관리하고 정리할 수 있습니다.</p>
                        <button type="button" id="process-queue" class="button">큐 즉시 처리</button>
                        <button type="button" id="clear-queue" class="button">완료된 항목 정리</button>
                        <button type="button" id="refresh-queue" class="button">상태 새로고침</button>
                    </div>
                    
                    <div class="ainl-queue-help">
                        <h4>이메일 큐 정보</h4>
                        <ul>
                            <li><strong>대기 중:</strong> 발송을 기다리는 이메일</li>
                            <li><strong>발송 중:</strong> 현재 발송 처리 중인 이메일</li>
                            <li><strong>발송 완료:</strong> 성공적으로 발송된 이메일</li>
                            <li><strong>발송 실패:</strong> 최대 재시도 후에도 실패한 이메일</li>
                        </ul>
                        <p>이메일 큐는 자동으로 1분마다 처리됩니다. 수동으로 즉시 처리하려면 "큐 즉시 처리" 버튼을 클릭하세요.</p>
                    </div>
                </div>
                
                <!-- 테스트 탭 -->
                <div id="tests" class="ainl-tab-content">
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
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button-primary" value="설정 저장" />
                </p>
            </form>
            
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
        .ainl-tabs {
            margin-bottom: 20px;
        }
        
        .ainl-tab-nav {
            list-style: none;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #ccc;
        }
        
        .ainl-tab-nav li {
            display: inline-block;
            margin: 0;
        }
        
        .ainl-tab-link {
            display: block;
            padding: 10px 20px;
            text-decoration: none;
            border: 1px solid transparent;
            border-bottom: none;
            background: #f1f1f1;
            color: #333;
        }
        
        .ainl-tab-link.active {
            background: #fff;
            border-color: #ccc;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
        }
        
        .ainl-tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .ainl-tab-content.active {
            display: block;
        }
        
        .ainl-queue-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .ainl-stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            min-width: 120px;
            flex: 1;
        }
        
        .ainl-stat-card h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        
        .ainl-stat-number {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .ainl-stat-number.pending { color: #f56500; }
        .ainl-stat-number.sending { color: #0073aa; }
        .ainl-stat-number.sent { color: #46b450; }
        .ainl-stat-number.failed { color: #dc3232; }
        .ainl-stat-number.total { color: #333; }
        
        .ainl-queue-actions {
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .ainl-queue-actions button {
            margin-right: 10px;
        }
        
        .ainl-queue-help {
            margin: 20px 0;
            padding: 15px;
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
        }
        
        .ainl-smtp-test {
            margin: 30px 0;
            padding: 20px;
            background: #f0f8ff;
            border: 1px solid #007cba;
            border-radius: 4px;
        }
        
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
        
        #smtp-test-result {
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        #smtp-test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        #smtp-test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        </style>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 설정 저장
     */
    private function save_settings() {
        $settings = array();
        
        // 일반 설정
        $settings['openai_api_key'] = sanitize_text_field($_POST['openai_api_key']);
        $settings['claude_api_key'] = sanitize_text_field($_POST['claude_api_key']);
        $settings['content_date_range'] = intval($_POST['content_date_range']);
        $settings['max_posts'] = intval($_POST['max_posts']);
        
        // SMTP 설정
        $settings['smtp_host'] = sanitize_text_field($_POST['smtp_host']);
        $settings['smtp_port'] = intval($_POST['smtp_port']);
        $settings['smtp_encryption'] = sanitize_text_field($_POST['smtp_encryption']);
        $settings['smtp_username'] = sanitize_text_field($_POST['smtp_username']);
        $settings['smtp_password'] = sanitize_text_field($_POST['smtp_password']);
        $settings['from_name'] = sanitize_text_field($_POST['from_name']);
        $settings['from_email'] = sanitize_email($_POST['from_email']);
        
        // 발송 설정
        $settings['email_rate_limit'] = intval($_POST['email_rate_limit']);
        $settings['email_batch_size'] = intval($_POST['email_batch_size']);
        $settings['email_max_attempts'] = intval($_POST['email_max_attempts']);
        
        update_option('ainl_settings', $settings);
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
    
    /**
     * AJAX: 템플릿 테스트
     */
    public function ajax_test_templates() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $template_test = new AINL_Template_Test();
        $results = $template_test->run_all_tests();
        
        wp_send_json_success(array('results' => $results));
    }
    
    /**
     * AJAX: 템플릿 미리보기
     */
    public function ajax_get_template_preview() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $template_manager = new AINL_Template_Manager();
        
        // 샘플 데이터로 템플릿 렌더링
        $sample_data = array(
            'site_name' => get_bloginfo('name'),
            'newsletter_title' => '샘플 뉴스레터',
            'newsletter_date' => date('Y년 m월 d일'),
            'posts_content' => '<div class="post-item"><h3>샘플 포스트 제목</h3><p>이것은 샘플 포스트 내용입니다...</p></div>'
        );
        
        $html = $template_manager->render_template($template_id, $sample_data);
        
        if ($html) {
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error('템플릿을 렌더링할 수 없습니다.');
        }
    }
    
    /**
     * AJAX: 구독자 추가
     */
    public function ajax_add_subscriber() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $subscriber_manager = new AINL_Subscriber_Manager();
        
        $data = array(
            'email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'status' => sanitize_text_field($_POST['status']),
            'tags' => sanitize_text_field($_POST['tags']),
            'source' => 'admin'
        );
        
        $result = $subscriber_manager->create_subscriber($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '구독자가 성공적으로 추가되었습니다.',
                'subscriber_id' => $result
            ));
        }
    }
    
    /**
     * AJAX: 구독자 업데이트
     */
    public function ajax_update_subscriber() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $subscriber_manager = new AINL_Subscriber_Manager();
        $subscriber_id = intval($_POST['subscriber_id']);
        
        $data = array(
            'email' => sanitize_email($_POST['email']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'status' => sanitize_text_field($_POST['status']),
            'tags' => sanitize_text_field($_POST['tags'])
        );
        
        $result = $subscriber_manager->update_subscriber($subscriber_id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '구독자 정보가 성공적으로 업데이트되었습니다.'
            ));
        }
    }
    
    /**
     * AJAX: 구독자 삭제
     */
    public function ajax_delete_subscriber() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $subscriber_manager = new AINL_Subscriber_Manager();
        $subscriber_id = intval($_POST['subscriber_id']);
        
        $result = $subscriber_manager->delete_subscriber($subscriber_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '구독자가 성공적으로 삭제되었습니다.'
            ));
        }
    }
    
    /**
     * AJAX: 구독자 대량 작업
     */
    public function ajax_bulk_action_subscribers() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $subscriber_manager = new AINL_Subscriber_Manager();
        $action = sanitize_text_field($_POST['bulk_action']);
        $subscriber_ids = array_map('intval', $_POST['subscriber_ids']);
        
        if (empty($subscriber_ids)) {
            wp_send_json_error('선택된 구독자가 없습니다.');
        }
        
        switch ($action) {
            case 'delete':
                $result = $subscriber_manager->bulk_delete_subscribers($subscriber_ids);
                break;
                
            case 'status_change':
                $new_status = sanitize_text_field($_POST['new_status']);
                $result = $subscriber_manager->bulk_update_status($subscriber_ids, $new_status);
                break;
                
            default:
                wp_send_json_error('유효하지 않은 작업입니다.');
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '대량 작업이 성공적으로 완료되었습니다.',
                'result' => $result
            ));
        }
    }
    
    /**
     * AJAX: 구독자 가져오기
     */
    public function ajax_import_subscribers() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('파일 업로드 오류가 발생했습니다.');
        }
        
        $subscriber_manager = new AINL_Subscriber_Manager();
        $file_path = $_FILES['csv_file']['tmp_name'];
        $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
        
        $options = array(
            'update_existing' => $update_existing
        );
        
        $result = $subscriber_manager->import_from_csv($file_path, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => 'CSV 가져오기가 완료되었습니다.',
                'result' => $result
            ));
        }
    }
    
    /**
     * AJAX: 구독자 내보내기
     */
    public function ajax_export_subscribers() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $subscriber_manager = new AINL_Subscriber_Manager();
        
        // 필터 조건 적용
        $args = array();
        if (isset($_POST['status']) && !empty($_POST['status'])) {
            $args['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['search']) && !empty($_POST['search'])) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }
        
        $file_path = $subscriber_manager->export_to_csv($args);
        
        if (is_wp_error($file_path)) {
            wp_send_json_error($file_path->get_error_message());
        } else {
            $upload_dir = wp_upload_dir();
            $file_url = str_replace($upload_dir['path'], $upload_dir['url'], $file_path);
            
            wp_send_json_success(array(
                'file_url' => $file_url,
                'message' => '구독자 목록이 성공적으로 내보내졌습니다.'
            ));
        }
    }
    
    /**
     * AJAX: SMTP 연결 테스트
     */
    public function ajax_test_smtp() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $email_manager = AINL_Email_Manager::get_instance();
        $result = $email_manager->test_smtp_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: 테스트 이메일 발송
     */
    public function ajax_send_test_email() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $to_email = sanitize_email($_POST['email']);
        
        if (!is_email($to_email)) {
            wp_send_json_error('유효하지 않은 이메일 주소입니다.');
        }
        
        $email_manager = AINL_Email_Manager::get_instance();
        $result = $email_manager->send_test_email($to_email);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => '테스트 이메일이 성공적으로 발송되었습니다.'
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX: 이메일 큐 정리
     */
    public function ajax_clear_email_queue() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $email_manager = AINL_Email_Manager::get_instance();
        $deleted = $email_manager->cleanup_queue();
        
        wp_send_json_success(array(
            'message' => $deleted . '개의 이메일 항목이 정리되었습니다.'
        ));
    }
    
    /**
     * AJAX: 이메일 큐 즉시 처리
     */
    public function ajax_process_email_queue() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $email_manager = AINL_Email_Manager::get_instance();
        $email_manager->process_email_queue();
        
        wp_send_json_success(array(
            'message' => '이메일 큐가 처리되었습니다.'
        ));
    }
    
    /**
     * AJAX: 큐 상태 새로고침
     */
    public function ajax_refresh_queue_status() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $email_manager = AINL_Email_Manager::get_instance();
        $queue_status = $email_manager->get_queue_status();
        
        wp_send_json_success($queue_status);
    }
} 
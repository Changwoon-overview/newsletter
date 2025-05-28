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
        add_action('wp_ajax_ainl_preview_filtered_posts', array($this, 'ajax_preview_filtered_posts'));
        add_action('wp_ajax_ainl_search_posts', array($this, 'ajax_search_posts'));
        add_action('wp_ajax_ainl_generate_ai_content', array($this, 'ajax_generate_ai_content'));
        add_action('wp_ajax_ainl_send_test_campaign', array($this, 'ajax_send_test_campaign'));
        add_action('wp_ajax_ainl_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_ainl_launch_campaign', array($this, 'ajax_launch_campaign'));
        add_action('wp_ajax_ainl_get_subscriber_count', array($this, 'ajax_get_subscriber_count'));
        add_action('wp_ajax_ainl_upload_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_ainl_load_campaign', array($this, 'ajax_load_campaign'));
        add_action('wp_ajax_ainl_load_selected_posts', array($this, 'ajax_load_selected_posts'));
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
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        // 액션 처리
        switch ($action) {
            case 'new':
                $this->render_campaign_wizard();
                return;
                
            case 'edit':
                if ($campaign_id > 0) {
                    $this->render_campaign_wizard($campaign_id);
                    return;
                }
                break;
                
            case 'duplicate':
                if ($campaign_id > 0) {
                    $this->duplicate_campaign($campaign_id);
                    wp_redirect(admin_url('admin.php?page=ainl-campaigns&duplicated=1'));
                    exit;
                }
                break;
                
            case 'save_template':
                if ($campaign_id > 0) {
                    $this->save_campaign_as_template($campaign_id);
                    wp_redirect(admin_url('admin.php?page=ainl-campaigns&template_saved=1'));
                    exit;
                }
                break;
                
            case 'delete':
                if ($campaign_id > 0) {
                    $this->delete_campaign($campaign_id);
                    wp_redirect(admin_url('admin.php?page=ainl-campaigns&deleted=1'));
                    exit;
                }
                break;
        }
        
        // 기본: 캠페인 목록 표시
        $this->render_campaigns_list();
    }
    
    /**
     * 캠페인 목록 렌더링
     */
    private function render_campaigns_list() {
        $this->render_page_header('캠페인 관리', '뉴스레터 캠페인을 생성하고 관리합니다.');
        
        // 알림 메시지 표시
        if (isset($_GET['duplicated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>캠페인이 성공적으로 복제되었습니다.</p></div>';
        }
        if (isset($_GET['template_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>캠페인이 템플릿으로 저장되었습니다.</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>캠페인이 삭제되었습니다.</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>작업 중 오류가 발생했습니다.</p></div>';
        }
        
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        $campaigns = $campaign_manager->get_campaigns();
        
        // 캠페인 매니저 인스턴스 생성
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        
        // 검색 및 필터 처리
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $campaigns = $campaign_manager->get_campaigns(array(
            'search' => $search,
            'status' => $status_filter,
            'limit' => 20
        ));
        ?>
        <div class="ainl-campaigns">
            <div class="ainl-page-actions">
                <a href="<?php echo admin_url('admin.php?page=ai-newsletter-campaigns&action=new'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    새 캠페인 생성
                </a>
            </div>
            
            <!-- 검색 및 필터 -->
            <div class="ainl-campaigns-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="ai-newsletter-campaigns">
                    <div class="ainl-filter-row">
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>" 
                               placeholder="캠페인 이름 또는 제목 검색..." class="regular-text">
                        <select name="status">
                            <option value="">모든 상태</option>
                            <option value="draft" <?php selected($status_filter, 'draft'); ?>>초안</option>
                            <option value="ready" <?php selected($status_filter, 'ready'); ?>>발송 준비</option>
                            <option value="sending" <?php selected($status_filter, 'sending'); ?>>발송 중</option>
                            <option value="sent" <?php selected($status_filter, 'sent'); ?>>발송 완료</option>
                            <option value="paused" <?php selected($status_filter, 'paused'); ?>>일시 정지</option>
                            <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>취소됨</option>
                        </select>
                        <button type="submit" class="button">필터 적용</button>
                        <?php if ($search || $status_filter): ?>
                        <a href="<?php echo admin_url('admin.php?page=ai-newsletter-campaigns'); ?>" class="button">초기화</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- 캠페인 목록 -->
            <div class="ainl-campaigns-table-wrapper">
                <?php if (empty($campaigns)): ?>
                <div class="ainl-empty-state">
                    <div class="ainl-empty-icon">
                        <span class="dashicons dashicons-email-alt"></span>
                    </div>
                    <h3>아직 캠페인이 없습니다</h3>
                    <p>첫 번째 뉴스레터 캠페인을 생성해보세요!</p>
                    <a href="<?php echo admin_url('admin.php?page=ai-newsletter-campaigns&action=new'); ?>" class="button button-primary">
                        새 캠페인 생성
                    </a>
                </div>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column">캠페인 이름</th>
                            <th scope="col" class="manage-column">이메일 제목</th>
                            <th scope="col" class="manage-column">상태</th>
                            <th scope="col" class="manage-column">생성일</th>
                            <th scope="col" class="manage-column">발송일</th>
                            <th scope="col" class="manage-column">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td class="campaign-name">
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=ai-newsletter-campaigns&action=edit&campaign=' . $campaign->id); ?>">
                                        <?php echo esc_html($campaign->name); ?>
                                    </a>
                                </strong>
                            </td>
                            <td class="campaign-subject">
                                <?php echo esc_html($campaign->subject); ?>
                            </td>
                            <td class="campaign-status">
                                <span class="ainl-status-badge ainl-status-<?php echo esc_attr($campaign->status); ?>">
                                    <?php echo $this->get_status_label($campaign->status); ?>
                                </span>
                            </td>
                            <td class="campaign-created">
                                <?php echo date_i18n('Y-m-d H:i', strtotime($campaign->created_at)); ?>
                            </td>
                            <td class="campaign-sent">
                                <?php 
                                if ($campaign->status === 'sent' && $campaign->updated_at) {
                                    echo date_i18n('Y-m-d H:i', strtotime($campaign->updated_at));
                                } elseif ($campaign->scheduled_at) {
                                    echo '예약: ' . date_i18n('Y-m-d H:i', strtotime($campaign->scheduled_at));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="campaign-actions">
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=ainl-campaigns&action=edit&campaign_id=' . $campaign->id); ?>">
                                            편집
                                        </a> |
                                    </span>
                                    <span class="duplicate">
                                        <a href="<?php echo admin_url('admin.php?page=ainl-campaigns&action=duplicate&campaign_id=' . $campaign->id); ?>"
                                           onclick="return confirm('이 캠페인을 복제하시겠습니까?');">
                                            복제
                                        </a> |
                                    </span>
                                    <span class="template">
                                        <a href="<?php echo admin_url('admin.php?page=ainl-campaigns&action=save_template&campaign_id=' . $campaign->id); ?>"
                                           onclick="return confirm('이 캠페인을 템플릿으로 저장하시겠습니까?');">
                                            템플릿으로 저장
                                        </a>
                                        <?php if ($campaign->status === 'draft' || $campaign->status === 'cancelled'): ?>
                                        |
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($campaign->status === 'draft' || $campaign->status === 'cancelled'): ?>
                                    <span class="delete">
                                        <a href="<?php echo admin_url('admin.php?page=ainl-campaigns&action=delete&campaign_id=' . $campaign->id); ?>"
                                           onclick="return confirm('정말로 이 캠페인을 삭제하시겠습니까?');" class="submitdelete">
                                            삭제
                                        </a>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .ainl-campaigns-filters {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        .ainl-filter-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .ainl-filter-row input[type="text"] {
            flex: 1;
            max-width: 300px;
        }
        .ainl-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .ainl-empty-icon {
            font-size: 48px;
            color: #c3c4c7;
            margin-bottom: 20px;
        }
        .ainl-empty-state h3 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        .ainl-empty-state p {
            margin: 0 0 20px 0;
            color: #646970;
        }
        .ainl-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ainl-status-draft {
            background: #f0f0f1;
            color: #646970;
        }
        .ainl-status-ready {
            background: #d63638;
            color: #fff;
        }
        .ainl-status-sending {
            background: #dba617;
            color: #fff;
        }
        .ainl-status-sent {
            background: #00a32a;
            color: #fff;
        }
        .ainl-status-paused {
            background: #72aee6;
            color: #fff;
        }
        .ainl-status-cancelled {
            background: #8c8f94;
            color: #fff;
        }
        </style>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 캠페인 마법사 렌더링
     */
    private function render_campaign_wizard($campaign_id = 0) {
        $campaign = null;
        $is_edit = false;
        
        if ($campaign_id > 0) {
            $campaign_manager = AINL_Campaign_Manager::get_instance();
            $campaign = $campaign_manager->get_campaign($campaign_id);
            $is_edit = true;
            
            if (!$campaign) {
                wp_die('캠페인을 찾을 수 없습니다.');
            }
        }
        
        $page_title = $is_edit ? '캠페인 편집' : '새 캠페인 생성';
        $page_description = $is_edit ? $campaign->name . ' 편집' : '단계별 캠페인 생성 마법사';
        
        $this->render_page_header($page_title, $page_description);
        ?>
        <div class="ainl-campaign-wizard" data-campaign-id="<?php echo $campaign_id; ?>">
            <!-- 진행 상태 표시 -->
            <div class="ainl-wizard-progress">
                <div class="ainl-progress-steps">
                    <div class="ainl-step active" data-step="basic">
                        <div class="ainl-step-number">1</div>
                        <div class="ainl-step-label">기본 정보</div>
                    </div>
                    <div class="ainl-step" data-step="content">
                        <div class="ainl-step-number">2</div>
                        <div class="ainl-step-label">콘텐츠 선택</div>
                    </div>
                    <div class="ainl-step" data-step="design">
                        <div class="ainl-step-number">3</div>
                        <div class="ainl-step-label">디자인</div>
                    </div>
                    <div class="ainl-step" data-step="preview">
                        <div class="ainl-step-number">4</div>
                        <div class="ainl-step-label">미리보기</div>
                    </div>
                    <div class="ainl-step" data-step="send">
                        <div class="ainl-step-number">5</div>
                        <div class="ainl-step-label">발송</div>
                    </div>
                </div>
                <div class="ainl-progress-bar">
                    <div class="ainl-progress-fill" style="width: 20%;"></div>
                </div>
            </div>
            
            <!-- 마법사 콘텐츠 -->
            <div class="ainl-wizard-content">
                <!-- 1단계: 기본 정보 -->
                <div class="ainl-wizard-step active" id="step-basic">
                    <div class="ainl-step-header">
                        <h2>기본 정보</h2>
                        <p>캠페인의 기본 정보를 입력해주세요.</p>
                    </div>
                    
                    <div class="ainl-step-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="campaign-name">캠페인 이름 *</label>
                                </th>
                                <td>
                                    <input type="text" id="campaign-name" name="campaign_name" 
                                           value="<?php echo $campaign ? esc_attr($campaign->name) : ''; ?>" 
                                           class="regular-text" required>
                                    <p class="description">내부 관리용 캠페인 이름입니다.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="email-subject">이메일 제목 *</label>
                                </th>
                                <td>
                                    <input type="text" id="email-subject" name="email_subject" 
                                           value="<?php echo $campaign ? esc_attr($campaign->subject) : ''; ?>" 
                                           class="regular-text" required>
                                    <p class="description">구독자에게 표시될 이메일 제목입니다.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="from-name">발신자 이름</label>
                                </th>
                                <td>
                                    <input type="text" id="from-name" name="from_name" 
                                           value="<?php echo $campaign ? esc_attr($campaign->from_name) : esc_attr(get_bloginfo('name')); ?>" 
                                           class="regular-text">
                                    <p class="description">이메일 발신자로 표시될 이름입니다.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="from-email">발신자 이메일</label>
                                </th>
                                <td>
                                    <input type="email" id="from-email" name="from_email" 
                                           value="<?php echo $campaign ? esc_attr($campaign->from_email) : esc_attr(get_option('admin_email')); ?>" 
                                           class="regular-text">
                                    <p class="description">이메일 발신자 주소입니다.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- 2단계: 콘텐츠 선택 -->
                <div class="ainl-wizard-step" id="step-content">
                    <div class="ainl-step-header">
                        <h2>콘텐츠 선택</h2>
                        <p>뉴스레터에 포함할 콘텐츠를 선택해주세요.</p>
                    </div>
                    
                    <div class="ainl-step-content">
                        <div class="ainl-content-options">
                            <div class="ainl-option-tabs">
                                <button type="button" class="ainl-tab-button active" data-tab="filter">자동 필터</button>
                                <button type="button" class="ainl-tab-button" data-tab="manual">수동 선택</button>
                            </div>
                            
                            <!-- 자동 필터 탭 -->
                            <div class="ainl-tab-content active" id="tab-filter">
                                <h3>콘텐츠 필터 설정</h3>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">날짜 범위</th>
                                        <td>
                                            <select name="date_range" id="date-range">
                                                <option value="last_week">지난 주</option>
                                                <option value="last_month">지난 달</option>
                                                <option value="last_3_months">지난 3개월</option>
                                                <option value="custom">사용자 정의</option>
                                            </select>
                                            <div id="custom-date-range" style="display: none; margin-top: 10px;">
                                                <input type="date" name="date_from" id="date-from">
                                                <span> ~ </span>
                                                <input type="date" name="date_to" id="date-to">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">카테고리</th>
                                        <td>
                                            <div class="ainl-category-list">
                                                <?php
                                                $categories = get_categories(array('hide_empty' => false));
                                                foreach ($categories as $category):
                                                ?>
                                                <label>
                                                    <input type="checkbox" name="categories[]" value="<?php echo $category->term_id; ?>">
                                                    <?php echo esc_html($category->name); ?> (<?php echo $category->count; ?>)
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">게시물 상태</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="post_status[]" value="publish" checked>
                                                게시됨
                                            </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">최대 게시물 수</th>
                                        <td>
                                            <input type="number" name="max_posts" value="10" min="1" max="50" class="small-text">
                                            <p class="description">뉴스레터에 포함할 최대 게시물 수입니다.</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <div class="ainl-filter-preview">
                                    <button type="button" class="button" id="preview-filtered-posts">
                                        필터 결과 미리보기
                                    </button>
                                    <div id="filtered-posts-preview"></div>
                                </div>
                            </div>
                            
                            <!-- 수동 선택 탭 -->
                            <div class="ainl-tab-content" id="tab-manual">
                                <h3>게시물 수동 선택</h3>
                                <div class="ainl-post-search">
                                    <input type="text" id="post-search" placeholder="게시물 제목으로 검색..." class="regular-text">
                                    <button type="button" class="button" id="search-posts">검색</button>
                                </div>
                                
                                <div class="ainl-posts-selection">
                                    <div class="ainl-available-posts">
                                        <h4>사용 가능한 게시물</h4>
                                        <div id="available-posts-list">
                                            <!-- AJAX로 로드됨 -->
                                        </div>
                                    </div>
                                    
                                    <div class="ainl-selected-posts">
                                        <h4>선택된 게시물</h4>
                                        <div id="selected-posts-list">
                                            <!-- 선택된 게시물 표시 -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 3단계: 디자인 -->
                <div class="ainl-wizard-step" id="step-design">
                    <div class="ainl-step-header">
                        <h2>디자인 선택</h2>
                        <p>뉴스레터 템플릿을 선택해주세요.</p>
                    </div>
                    
                    <div class="ainl-step-content">
                        <div class="ainl-template-selection">
                            <?php
                            $template_manager = new AINL_Template_Manager();
                            $templates = $template_manager->get_default_templates();
                            foreach ($templates as $key => $template):
                            ?>
                            <div class="ainl-template-option">
                                <label>
                                    <input type="radio" name="template_id" value="<?php echo esc_attr($key); ?>" 
                                           <?php echo ($campaign && $campaign->template_id == $key) ? 'checked' : ''; ?>>
                                    <div class="ainl-template-preview-small">
                                        <iframe src="<?php echo admin_url('admin.php?page=ai-newsletter-templates&action=preview&template=' . $key); ?>" 
                                                width="200" height="150" frameborder="0"></iframe>
                                    </div>
                                    <div class="ainl-template-name"><?php echo esc_html($template['name']); ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 4단계: 미리보기 -->
                <div class="ainl-wizard-step" id="step-preview">
                    <div class="ainl-step-header">
                        <h2>미리보기 및 편집</h2>
                        <p>생성된 뉴스레터를 확인하고 필요시 편집해주세요.</p>
                    </div>
                    
                    <div class="ainl-step-content">
                        <div class="ainl-ai-options">
                            <h3>AI 콘텐츠 생성 옵션</h3>
                            <div class="ainl-ai-settings">
                                <div class="ainl-setting-group">
                                    <label for="ai-style">작성 스타일:</label>
                                    <select id="ai-style" name="ai_style">
                                        <option value="professional">전문적</option>
                                        <option value="casual">친근한</option>
                                        <option value="friendly">따뜻한</option>
                                    </select>
                                </div>
                                
                                <div class="ainl-setting-group">
                                    <label for="ai-length">콘텐츠 길이:</label>
                                    <select id="ai-length" name="ai_length">
                                        <option value="short">간결함</option>
                                        <option value="medium" selected>적당함</option>
                                        <option value="long">상세함</option>
                                    </select>
                                </div>
                                
                                <div class="ainl-setting-group">
                                    <label>
                                        <input type="checkbox" id="generate-title" name="generate_title">
                                        AI로 이메일 제목도 생성
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ainl-preview-actions">
                            <button type="button" class="button button-primary" id="generate-content">
                                🤖 AI로 콘텐츠 생성
                            </button>
                            <button type="button" class="button" id="toggle-editor">
                                ✏️ 수동 편집 모드
                            </button>
                        </div>
                        
                        <div class="ainl-content-preview">
                            <div id="newsletter-preview">
                                <!-- AI 생성 콘텐츠 또는 미리보기 표시 -->
                                <div class="ainl-placeholder">
                                    <p>👆 위의 "AI로 콘텐츠 생성" 버튼을 클릭하여 뉴스레터 콘텐츠를 생성해보세요.</p>
                                    <p>선택하신 게시물들을 바탕으로 매력적인 뉴스레터가 자동으로 만들어집니다.</p>
                                </div>
                            </div>
                            
                            <div id="newsletter-editor" style="display: none;">
                                <?php
                                wp_editor('', 'newsletter_content', array(
                                    'textarea_name' => 'newsletter_content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 20,
                                    'teeny' => false,
                                    'tinymce' => true
                                ));
                                ?>
                            </div>
                        </div>
                        
                        <div class="ainl-test-email">
                            <h3>테스트 이메일 발송</h3>
                            <div class="ainl-test-form">
                                <input type="email" id="test-email" placeholder="테스트 이메일 주소" class="regular-text">
                                <button type="button" class="button" id="send-test-email">📧 테스트 발송</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 5단계: 발송 -->
                <div class="ainl-wizard-step" id="step-send">
                    <div class="ainl-step-header">
                        <h2>발송 설정</h2>
                        <p>캠페인 발송 방법을 선택해주세요.</p>
                    </div>
                    
                    <div class="ainl-step-content">
                        <div class="ainl-send-options">
                            <div class="ainl-send-option">
                                <label>
                                    <input type="radio" name="send_type" value="now" checked>
                                    <strong>즉시 발송</strong>
                                    <p>지금 바로 모든 구독자에게 발송합니다.</p>
                                </label>
                            </div>
                            
                            <div class="ainl-send-option">
                                <label>
                                    <input type="radio" name="send_type" value="scheduled">
                                    <strong>예약 발송</strong>
                                    <p>지정한 시간에 자동으로 발송합니다.</p>
                                </label>
                                <div class="ainl-schedule-settings" style="display: none;">
                                    <input type="datetime-local" name="scheduled_at" id="scheduled-at">
                                </div>
                            </div>
                        </div>
                        
                        <div class="ainl-subscriber-summary">
                            <h3>발송 대상</h3>
                            <div id="subscriber-count">
                                <!-- AJAX로 구독자 수 로드 -->
                            </div>
                        </div>
                        
                        <div class="ainl-final-check">
                            <h3>최종 확인</h3>
                            <div class="ainl-campaign-summary">
                                <ul>
                                    <li><strong>캠페인 이름:</strong> <span id="summary-name"></span></li>
                                    <li><strong>이메일 제목:</strong> <span id="summary-subject"></span></li>
                                    <li><strong>발신자:</strong> <span id="summary-from"></span></li>
                                    <li><strong>템플릿:</strong> <span id="summary-template"></span></li>
                                    <li><strong>발송 방법:</strong> <span id="summary-send-type"></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 마법사 네비게이션 -->
            <div class="ainl-wizard-navigation">
                <button type="button" class="button button-secondary" id="prev-step" style="display: none;">
                    이전 단계
                </button>
                <button type="button" class="button button-primary" id="next-step">
                    다음 단계
                </button>
                <button type="button" class="button button-primary" id="save-campaign" style="display: none;">
                    캠페인 저장
                </button>
                <button type="button" class="button button-primary" id="launch-campaign" style="display: none;">
                    캠페인 발송
                </button>
            </div>
        </div>
        
        <style>
        .ainl-campaign-wizard {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .ainl-wizard-progress {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .ainl-progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .ainl-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        
        .ainl-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 60%;
            right: -40%;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .ainl-step.active:not(:last-child)::after,
        .ainl-step.completed:not(:last-child)::after {
            background: #0073aa;
        }
        
        .ainl-step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
            position: relative;
            z-index: 2;
        }
        
        .ainl-step.active .ainl-step-number {
            background: #0073aa;
            color: #fff;
        }
        
        .ainl-step.completed .ainl-step-number {
            background: #00a32a;
            color: #fff;
        }
        
        .ainl-step-label {
            font-size: 12px;
            text-align: center;
            color: #666;
        }
        
        .ainl-step.active .ainl-step-label {
            color: #0073aa;
            font-weight: bold;
        }
        
        .ainl-progress-bar {
            height: 4px;
            background: #ddd;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .ainl-progress-fill {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        
        .ainl-wizard-content {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .ainl-wizard-step {
            display: none;
            padding: 30px;
        }
        
        .ainl-wizard-step.active {
            display: block;
        }
        
        .ainl-step-header {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        
        .ainl-step-header h2 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        
        .ainl-step-header p {
            margin: 0;
            color: #646970;
        }
        
        .ainl-option-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .ainl-tab-button {
            background: none;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .ainl-tab-button.active {
            border-bottom-color: #0073aa;
            color: #0073aa;
        }
        
        .ainl-tab-content {
            display: none;
        }
        
        .ainl-tab-content.active {
            display: block;
        }
        
        .ainl-category-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .ainl-category-list label {
            display: block;
            padding: 5px 0;
        }
        
        .ainl-posts-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .ainl-available-posts,
        .ainl-selected-posts {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            min-height: 300px;
        }
        
        .ainl-template-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .ainl-template-option {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            cursor: pointer;
        }
        
        .ainl-template-option:hover {
            border-color: #0073aa;
        }
        
        .ainl-template-option input[type="radio"] {
            display: none;
        }
        
        .ainl-template-option input[type="radio"]:checked + .ainl-template-preview-small {
            border: 2px solid #0073aa;
        }
        
        .ainl-template-preview-small {
            display: block;
            width: 100%;
            height: 150px;
        }
        
        .ainl-template-name {
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .ainl-send-option {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .ainl-send-option label {
            display: block;
            cursor: pointer;
        }
        
        .ainl-schedule-settings {
            margin-top: 10px;
            padding-left: 25px;
        }
        
        .ainl-wizard-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .ainl-campaign-summary ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ainl-campaign-summary li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .ainl-campaign-summary li:last-child {
            border-bottom: none;
        }
        
        /* AI 옵션 스타일 */
        .ainl-ai-options {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .ainl-ai-options h3 {
            margin: 0 0 15px 0;
            color: #1d2327;
            font-size: 16px;
        }
        
        .ainl-ai-settings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: center;
        }
        
        .ainl-setting-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .ainl-setting-group label {
            font-weight: 500;
            color: #1d2327;
        }
        
        .ainl-setting-group select {
            padding: 6px 8px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            background: #fff;
        }
        
        .ainl-setting-group input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .ainl-preview-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
        }
        
        .ainl-placeholder {
            text-align: center;
            padding: 40px 20px;
            background: #f6f7f7;
            border: 2px dashed #c3c4c7;
            border-radius: 4px;
            color: #646970;
        }
        
        .ainl-placeholder p {
            margin: 10px 0;
            font-size: 14px;
        }
        
        .ai-generation-message {
            margin: 15px 0;
            border-left: 4px solid #00a32a;
        }
        
        .ai-generation-message.notice-warning {
            border-left-color: #dba617;
        }
        
        .ai-generation-message.notice-error {
            border-left-color: #d63638;
        }
        
        .ainl-content-preview {
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            min-height: 400px;
            background: #fff;
        }
        
        #newsletter-preview {
            padding: 20px;
            min-height: 360px;
        }
        
        .ainl-test-email {
            margin-top: 30px;
            padding: 20px;
            background: #f6f7f7;
            border-radius: 4px;
        }
        
        .ainl-test-email h3 {
            margin: 0 0 15px 0;
            color: #1d2327;
        }
        
        .ainl-test-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .ainl-test-form input[type="email"] {
            flex: 1;
            max-width: 300px;
        }
        </style>
        <?php
        $this->render_page_footer();
    }
    
    /**
     * 상태 라벨 반환
     */
    private function get_status_label($status) {
        $labels = array(
            'draft' => '초안',
            'ready' => '발송 준비',
            'sending' => '발송 중',
            'sent' => '발송 완료',
            'paused' => '일시 정지',
            'cancelled' => '취소됨'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * 캠페인 복제
     */
    private function duplicate_campaign($campaign_id) {
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        $original_campaign = $campaign_manager->get_campaign($campaign_id);
        
        if (!$original_campaign) {
            wp_die('복제할 캠페인을 찾을 수 없습니다.');
        }
        
        // 복제 데이터 준비
        $duplicate_data = array(
            'name' => $original_campaign->name . ' (복사본)',
            'subject' => $original_campaign->subject,
            'from_name' => $original_campaign->from_name,
            'from_email' => $original_campaign->from_email,
            'template_id' => $original_campaign->template_id,
            'content' => $original_campaign->content,
            'content_type' => $original_campaign->content_type,
            'filter_settings' => $original_campaign->filter_settings,
            'selected_posts' => $original_campaign->selected_posts,
            'status' => 'draft',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // 복제 실행
        $new_campaign_id = $campaign_manager->create_campaign($duplicate_data);
        
        if ($new_campaign_id) {
            // 성공 메시지 설정
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>캠페인이 성공적으로 복제되었습니다.</p></div>';
            });
        } else {
            // 오류 메시지 설정
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>캠페인 복제 중 오류가 발생했습니다.</p></div>';
            });
        }
        
        return $new_campaign_id;
    }
    
    /**
     * 캠페인을 템플릿으로 저장
     */
    private function save_campaign_as_template($campaign_id) {
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        $campaign = $campaign_manager->get_campaign($campaign_id);
        
        if (!$campaign) {
            wp_die('템플릿으로 저장할 캠페인을 찾을 수 없습니다.');
        }
        
        // 템플릿 매니저 인스턴스 가져오기
        $template_manager = AINL_Template_Manager::get_instance();
        
        // 템플릿 데이터 준비
        $template_data = array(
            'name' => $campaign->name . ' 템플릿',
            'description' => $campaign->subject . '에서 생성된 템플릿',
            'content' => $campaign->content,
            'type' => 'campaign',
            'settings' => json_encode(array(
                'from_name' => $campaign->from_name,
                'from_email' => $campaign->from_email,
                'content_type' => $campaign->content_type,
                'filter_settings' => $campaign->filter_settings
            )),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // 템플릿 저장
        $template_id = $template_manager->create_template($template_data);
        
        if ($template_id) {
            // 성공 메시지 설정
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>캠페인이 템플릿으로 저장되었습니다.</p></div>';
            });
        } else {
            // 오류 메시지 설정
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>템플릿 저장 중 오류가 발생했습니다.</p></div>';
            });
        }
        
        return $template_id;
    }
    
    /**
     * 캠페인 삭제
     */
    private function delete_campaign($campaign_id) {
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        $campaign = $campaign_manager->get_campaign($campaign_id);
        
        if (!$campaign) {
            wp_die('삭제할 캠페인을 찾을 수 없습니다.');
        }
        
        // 발송 중인 캠페인은 삭제 불가
        if ($campaign->status === 'sending') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>발송 중인 캠페인은 삭제할 수 없습니다.</p></div>';
            });
            return false;
        }
        
        // 캠페인 삭제
        $deleted = $campaign_manager->delete_campaign($campaign_id);
        
        if ($deleted) {
            // 성공 메시지 설정
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>캠페인이 삭제되었습니다.</p></div>';
            });
        } else {
            // 오류 메시지 설정
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>캠페인 삭제 중 오류가 발생했습니다.</p></div>';
            });
        }
        
        return $deleted;
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
    
    /**
     * AJAX: 필터링된 게시물 미리보기
     */
    public function ajax_preview_filtered_posts() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        // 필터 파라미터 수집
        $date_range = sanitize_text_field($_POST['date_range']);
        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);
        $categories = isset($_POST['categories']) ? array_map('intval', $_POST['categories']) : array();
        $max_posts = intval($_POST['max_posts']);
        
        // 날짜 범위 계산
        $date_query = $this->build_date_query($date_range, $date_from, $date_to);
        
        // WP_Query 파라미터 구성
        $query_args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $max_posts,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => $date_query
        );
        
        if (!empty($categories)) {
            $query_args['category__in'] = $categories;
        }
        
        // 게시물 조회
        $posts = get_posts($query_args);
        
        // HTML 생성
        $html = '';
        if (empty($posts)) {
            $html = '<p>선택한 조건에 맞는 게시물이 없습니다.</p>';
        } else {
            $html .= '<div class="ainl-filtered-posts">';
            $html .= '<h4>필터 결과 (' . count($posts) . '개 게시물)</h4>';
            
            foreach ($posts as $post) {
                $categories_list = get_the_category_list(', ', '', $post->ID);
                $excerpt = wp_trim_words($post->post_content, 20);
                
                $html .= '<div class="ainl-post-preview">';
                $html .= '<h5><a href="' . get_permalink($post->ID) . '" target="_blank">' . esc_html($post->post_title) . '</a></h5>';
                $html .= '<div class="post-meta">';
                $html .= '<span class="post-date">' . get_the_date('Y-m-d', $post->ID) . '</span>';
                if ($categories_list) {
                    $html .= ' | <span class="post-categories">' . $categories_list . '</span>';
                }
                $html .= '</div>';
                $html .= '<p class="post-excerpt">' . esc_html($excerpt) . '</p>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: 게시물 검색
     */
    public function ajax_search_posts() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        $search_term = sanitize_text_field($_POST['search']);
        
        // 게시물 검색
        $query_args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            's' => $search_term
        );
        
        $posts = get_posts($query_args);
        
        // HTML 생성
        $html = '';
        if (empty($posts)) {
            $html = '<p>검색 결과가 없습니다.</p>';
        } else {
            foreach ($posts as $post) {
                $categories_list = get_the_category_list(', ', '', $post->ID);
                $excerpt = wp_trim_words($post->post_content, 15);
                
                $html .= '<div class="ainl-post-item" data-post-id="' . $post->ID . '">';
                $html .= '<h5 class="post-title">' . esc_html($post->post_title) . '</h5>';
                $html .= '<div class="post-meta">';
                $html .= '<span class="post-date">' . get_the_date('Y-m-d', $post->ID) . '</span>';
                if ($categories_list) {
                    $html .= ' | <span class="post-categories">' . $categories_list . '</span>';
                }
                $html .= '</div>';
                $html .= '<p class="post-excerpt">' . esc_html($excerpt) . '</p>';
                $html .= '<button type="button" class="button select-post">선택</button>';
                $html .= '</div>';
            }
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: AI 기반 콘텐츠 생성
     */
    public function ajax_generate_ai_content() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_data = $_POST['campaign_data'];
        
        // AI 엔진 인스턴스 가져오기
        $ai_engine = AINL_AI_Engine::get_instance();
        
        // AI 엔진이 설정되어 있는지 확인
        if (!$ai_engine->is_configured()) {
            // AI가 설정되지 않은 경우 기본 템플릿 사용
            $content = $this->generate_basic_newsletter_content($campaign_data);
            wp_send_json_success(array(
                'content' => $content,
                'ai_used' => false,
                'message' => 'OpenAI API 키가 설정되지 않아 기본 템플릿을 사용했습니다.'
            ));
            return;
        }
        
        // 게시물 데이터 수집
        $posts = array();
        
        if ($campaign_data['content_type'] === 'filter') {
            // 필터 기반 게시물 수집
            $posts = $this->get_filtered_posts($campaign_data['filter_settings']);
        } else {
            // 수동 선택 게시물 수집
            $posts = $this->get_manual_posts($campaign_data['selected_posts']);
        }
        
        if (empty($posts)) {
            wp_send_json_error('콘텐츠를 생성할 게시물이 없습니다.');
            return;
        }
        
        // AI 생성 옵션 설정
        $ai_options = array(
            'style' => $campaign_data['ai_style'] ?? 'professional',
            'length' => $campaign_data['ai_length'] ?? 'medium',
            'include_summary' => true,
            'include_excerpts' => true,
            'max_posts' => intval($campaign_data['filter_settings']['max_posts'] ?? 10),
            'language' => 'korean'
        );
        
        // AI를 통한 콘텐츠 생성
        $ai_content = $ai_engine->generate_newsletter_content($posts, $ai_options);
        
        if (is_wp_error($ai_content)) {
            // AI 생성 실패 시 기본 템플릿 사용
            $content = $this->generate_basic_newsletter_content($campaign_data);
            wp_send_json_success(array(
                'content' => $content,
                'ai_used' => false,
                'message' => 'AI 콘텐츠 생성 중 오류가 발생했습니다: ' . $ai_content->get_error_message() . '. 기본 템플릿을 사용했습니다.'
            ));
            return;
        }
        
        // AI 제목 생성 (선택사항)
        $ai_title = '';
        if (!empty($campaign_data['generate_title'])) {
            $title_result = $ai_engine->generate_newsletter_title($posts, array(
                'style' => 'engaging',
                'max_length' => 60
            ));
            
            if (!is_wp_error($title_result)) {
                $ai_title = $title_result;
            }
        }
        
        wp_send_json_success(array(
            'content' => $ai_content,
            'title' => $ai_title,
            'ai_used' => true,
            'message' => 'AI를 통해 뉴스레터 콘텐츠가 성공적으로 생성되었습니다.'
        ));
    }
    
    /**
     * AJAX: 테스트 캠페인 발송
     */
    public function ajax_send_test_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_data = $_POST['campaign_data'];
        $test_email = sanitize_email($campaign_data['test_email']);
        
        if (!is_email($test_email)) {
            wp_send_json_error('유효하지 않은 이메일 주소입니다.');
        }
        
        // 이메일 매니저를 통한 테스트 발송
        $email_manager = AINL_Email_Manager::get_instance();
        
        $email_data = array(
            'to' => $test_email,
            'subject' => '[테스트] ' . $campaign_data['subject'],
            'content' => $campaign_data['content'],
            'from_name' => $campaign_data['from_name'],
            'from_email' => $campaign_data['from_email']
        );
        
        $result = $email_manager->send_email($email_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('테스트 이메일이 발송되었습니다.');
    }
    
    /**
     * AJAX: 캠페인 저장
     */
    public function ajax_save_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_data = $_POST['campaign_data'];
        
        // 캠페인 매니저를 통한 저장
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        
        if ($campaign_data['campaign_id'] > 0) {
            // 기존 캠페인 업데이트
            $result = $campaign_manager->update_campaign($campaign_data['campaign_id'], $campaign_data);
        } else {
            // 새 캠페인 생성
            $result = $campaign_manager->create_campaign($campaign_data);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array('campaign_id' => $result));
    }
    
    /**
     * AJAX: 캠페인 발송
     */
    public function ajax_launch_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_data = $_POST['campaign_data'];
        
        // 캠페인 매니저를 통한 발송
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        
        $result = $campaign_manager->launch_campaign($campaign_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('캠페인이 발송되었습니다.');
    }
    
    /**
     * AJAX: 구독자 수 조회
     */
    public function ajax_get_subscriber_count() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ainl_subscribers';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
        
        wp_send_json_success(array(
            'count' => intval($count)
        ));
    }
    
    /**
     * AJAX: 이미지 업로드
     */
    public function ajax_upload_image() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_die('파일 업로드 권한이 없습니다.');
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('이미지 파일이 업로드되지 않았습니다.');
        }
        
        $file = $_FILES['image'];
        
        // 파일 타입 검증
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('지원되지 않는 이미지 형식입니다. (JPEG, PNG, GIF, WebP만 허용)');
        }
        
        // 파일 크기 검증 (5MB 제한)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            wp_send_json_error('파일 크기가 너무 큽니다. (최대 5MB)');
        }
        
        // WordPress 업로드 처리
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_upload('image', 0);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error('이미지 업로드 중 오류가 발생했습니다: ' . $attachment_id->get_error_message());
        }
        
        $attachment_url = wp_get_attachment_url($attachment_id);
        $attachment_meta = wp_get_attachment_metadata($attachment_id);
        
        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => $attachment_url,
            'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'title' => get_the_title($attachment_id),
            'width' => $attachment_meta['width'] ?? 0,
            'height' => $attachment_meta['height'] ?? 0
        ));
    }
    
    /**
     * 날짜 쿼리 구성
     */
    private function build_date_query($date_range, $date_from = '', $date_to = '') {
        $date_query = array();
        
        switch ($date_range) {
            case 'last_week':
                $date_query = array(
                    'after' => '1 week ago'
                );
                break;
            case 'last_month':
                $date_query = array(
                    'after' => '1 month ago'
                );
                break;
            case 'last_3_months':
                $date_query = array(
                    'after' => '3 months ago'
                );
                break;
            case 'custom':
                if ($date_from && $date_to) {
                    $date_query = array(
                        'after' => $date_from,
                        'before' => $date_to,
                        'inclusive' => true
                    );
                }
                break;
        }
        
        return $date_query;
    }
    
    /**
     * 기본 뉴스레터 콘텐츠 생성
     */
    private function generate_basic_newsletter_content($campaign_data) {
        $content = '<div class="newsletter-content">';
        $content .= '<h1>' . esc_html($campaign_data['subject']) . '</h1>';
        $content .= '<p>안녕하세요! 이번 주 뉴스레터를 전해드립니다.</p>';
        
        // 선택된 게시물들 포함
        if ($campaign_data['content_type'] === 'filter') {
            $content .= $this->generate_filtered_content($campaign_data['filter_settings']);
        } else {
            $content .= $this->generate_manual_content($campaign_data['selected_posts']);
        }
        
        $content .= '<p>감사합니다!</p>';
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * 필터 기반 콘텐츠 생성
     */
    private function generate_filtered_content($filter_settings) {
        $date_query = $this->build_date_query(
            $filter_settings['date_range'],
            $filter_settings['date_from'],
            $filter_settings['date_to']
        );
        
        $query_args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => intval($filter_settings['max_posts']),
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => $date_query
        );
        
        if (!empty($filter_settings['categories'])) {
            $query_args['category__in'] = array_map('intval', $filter_settings['categories']);
        }
        
        $posts = get_posts($query_args);
        
        return $this->format_posts_content($posts);
    }
    
    /**
     * 수동 선택 콘텐츠 생성
     */
    private function generate_manual_content($selected_posts) {
        if (empty($selected_posts)) {
            return '<p>선택된 게시물이 없습니다.</p>';
        }
        
        $posts = get_posts(array(
            'post__in' => array_map('intval', $selected_posts),
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'post__in'
        ));
        
        return $this->format_posts_content($posts);
    }
    
    /**
     * 게시물 콘텐츠 포맷팅
     */
    private function format_posts_content($posts) {
        if (empty($posts)) {
            return '<p>표시할 게시물이 없습니다.</p>';
        }
        
        $content = '<div class="newsletter-posts">';
        
        foreach ($posts as $post) {
            $content .= '<div class="newsletter-post">';
            $content .= '<h2><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></h2>';
            $content .= '<div class="post-meta">';
            $content .= '<span class="post-date">' . get_the_date('Y년 m월 d일', $post->ID) . '</span>';
            
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                $content .= ' | <span class="post-categories">' . get_the_category_list(', ', '', $post->ID) . '</span>';
            }
            $content .= '</div>';
            
            $excerpt = wp_trim_words($post->post_content, 50);
            $content .= '<p class="post-excerpt">' . esc_html($excerpt) . '</p>';
            $content .= '<p><a href="' . get_permalink($post->ID) . '" class="read-more">자세히 보기 →</a></p>';
            $content .= '</div>';
        }
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * 필터 기반 게시물 수집 (AI용)
     * 
     * @param array $filter_settings 필터 설정
     * @return array 게시물 배열
     */
    private function get_filtered_posts($filter_settings) {
        $date_query = $this->build_date_query(
            $filter_settings['date_range'],
            $filter_settings['date_from'],
            $filter_settings['date_to']
        );
        
        $query_args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => intval($filter_settings['max_posts']),
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => $date_query
        );
        
        if (!empty($filter_settings['categories'])) {
            $query_args['category__in'] = array_map('intval', $filter_settings['categories']);
        }
        
        return get_posts($query_args);
    }
    
    /**
     * 수동 선택 게시물 수집 (AI용)
     * 
     * @param array $selected_posts 선택된 게시물 ID 배열
     * @return array 게시물 배열
     */
    private function get_manual_posts($selected_posts) {
        if (empty($selected_posts)) {
            return array();
        }
        
        return get_posts(array(
            'post__in' => array_map('intval', $selected_posts),
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'post__in'
        ));
    }
    
    /**
     * AJAX: 캠페인 데이터 로드
     */
    public function ajax_load_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        
        if ($campaign_id <= 0) {
            wp_send_json_error('유효하지 않은 캠페인 ID입니다.');
        }
        
        $campaign_manager = AINL_Campaign_Manager::get_instance();
        $campaign = $campaign_manager->get_campaign($campaign_id);
        
        if (!$campaign) {
            wp_send_json_error('캠페인을 찾을 수 없습니다.');
        }
        
        // 응답 데이터 준비
        $response_data = array(
            'id' => $campaign->id,
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'from_name' => $campaign->from_name,
            'from_email' => $campaign->from_email,
            'template_id' => $campaign->template_id,
            'content' => $campaign->content,
            'content_type' => $campaign->content_type ?? 'filter',
            'content_filters' => $campaign->content_filters,
            'selected_posts' => $campaign->selected_posts,
            'status' => $campaign->status,
            'created_at' => $campaign->created_at,
            'updated_at' => $campaign->updated_at
        );
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX: 선택된 게시물 데이터 로드
     */
    public function ajax_load_selected_posts() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('권한이 없습니다.');
        }
        
        $post_ids = $_POST['post_ids'];
        
        if (!is_array($post_ids) || empty($post_ids)) {
            wp_send_json_error('게시물 ID가 제공되지 않았습니다.');
        }
        
        // 게시물 ID 정수 변환 및 유효성 검사
        $post_ids = array_map('intval', $post_ids);
        $post_ids = array_filter($post_ids, function($id) {
            return $id > 0;
        });
        
        if (empty($post_ids)) {
            wp_send_json_error('유효한 게시물 ID가 없습니다.');
        }
        
        // 게시물 데이터 조회
        $posts = get_posts(array(
            'post__in' => $post_ids,
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'post__in'
        ));
        
        $posts_data = array();
        
        foreach ($posts as $post) {
            $categories = get_the_category($post->ID);
            $category_names = array();
            
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $category_names[] = $category->name;
                }
            }
            
            $posts_data[] = array(
                'ID' => $post->ID,
                'post_title' => $post->post_title,
                'post_date' => get_the_date('Y년 m월 d일', $post->ID),
                'excerpt' => wp_trim_words(get_the_excerpt($post->ID), 20, '...'),
                'permalink' => get_permalink($post->ID),
                'categories' => implode(', ', $category_names)
            );
        }
        
        wp_send_json_success($posts_data);
    }
} 
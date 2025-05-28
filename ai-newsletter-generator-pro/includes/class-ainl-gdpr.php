<?php
/**
 * AI Newsletter Generator Pro - GDPR 컴플라이언스 클래스
 * GDPR 및 개인정보 보호 규정 준수를 위한 기능을 제공합니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GDPR 컴플라이언스 클래스
 */
class AINL_GDPR {
    
    /**
     * 클래스 인스턴스
     */
    private static $instance = null;
    
    /**
     * 개인정보 보유 기간 (일)
     */
    const DATA_RETENTION_PERIOD = 365 * 2; // 2년
    
    /**
     * 싱글톤 인스턴스 반환
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * WordPress 훅 초기화
     */
    private function init_hooks() {
        // 개인정보 처리방침 페이지 생성 (플러그인 활성화 시)
        add_action('ainl_plugin_activated', array($this, 'create_privacy_policy_page'));
        
        // 구독 취소 요청 처리
        add_action('init', array($this, 'handle_unsubscribe_request'));
        
        // 데이터 삭제 요청 처리
        add_action('init', array($this, 'handle_data_deletion_request'));
        
        // 데이터 추출 요청 처리
        add_action('init', array($this, 'handle_data_export_request'));
        
        // WordPress 개인정보 삭제 도구와 연동
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_data_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_data_eraser'));
        
        // 관리자 페이지에 GDPR 메뉴 추가
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX 핸들러
        add_action('wp_ajax_ainl_data_deletion', array($this, 'ajax_data_deletion'));
        add_action('wp_ajax_nopriv_ainl_data_deletion', array($this, 'ajax_data_deletion'));
        
        // 크론 작업으로 오래된 데이터 자동 삭제
        add_action('ainl_cleanup_expired_data', array($this, 'cleanup_expired_data'));
        
        // 일일 크론 스케줄 설정
        if (!wp_next_scheduled('ainl_cleanup_expired_data')) {
            wp_schedule_event(time(), 'daily', 'ainl_cleanup_expired_data');
        }
    }
    
    /**
     * 개인정보 처리방침 페이지 생성
     */
    public function create_privacy_policy_page() {
        // 이미 존재하는지 확인
        $existing_page = get_option('ainl_privacy_policy_page_id');
        if ($existing_page && get_post($existing_page)) {
            return;
        }
        
        $page_content = $this->get_privacy_policy_template();
        
        $page_data = array(
            'post_title'   => __('뉴스레터 개인정보 처리방침', 'ai-newsletter-generator-pro'),
            'post_content' => $page_content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
            'meta_input'   => array(
                '_ainl_privacy_policy' => true
            )
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id && !is_wp_error($page_id)) {
            update_option('ainl_privacy_policy_page_id', $page_id);
        }
    }
    
    /**
     * 개인정보 처리방침 템플릿 생성
     * 
     * @return string 개인정보 처리방침 내용
     */
    private function get_privacy_policy_template() {
        return '
        <h2>뉴스레터 구독 관련 개인정보 처리방침</h2>
        
        <h3>1. 수집하는 개인정보</h3>
        <p>뉴스레터 서비스 제공을 위해 다음 정보를 수집합니다:</p>
        <ul>
            <li>이메일 주소 (필수)</li>
            <li>이름 (선택)</li>
            <li>구독 카테고리 선택 사항 (선택)</li>
            <li>구독 일시 및 IP 주소 (자동 수집)</li>
        </ul>
        
        <h3>2. 개인정보의 수집 및 이용 목적</h3>
        <ul>
            <li>뉴스레터 발송</li>
            <li>서비스 개선을 위한 통계 분석</li>
            <li>고객 문의 응답</li>
        </ul>
        
        <h3>3. 개인정보의 보유 및 이용기간</h3>
        <p>구독 해지 시까지 또는 최대 2년간 보관하며, 그 이후 자동으로 삭제됩니다.</p>
        
        <h3>4. 개인정보의 제3자 제공</h3>
        <p>수집된 개인정보는 원칙적으로 제3자에게 제공하지 않습니다.</p>
        
        <h3>5. 개인정보 처리의 위탁</h3>
        <p>이메일 발송을 위해 신뢰할 수 있는 이메일 서비스 제공업체를 이용할 수 있습니다.</p>
        
        <h3>6. 정보주체의 권리</h3>
        <p>귀하는 다음과 같은 권리를 가집니다:</p>
        <ul>
            <li>개인정보 처리 현황에 대한 열람 요구</li>
            <li>개인정보의 정정 및 삭제 요구</li>
            <li>개인정보 처리 정지 요구</li>
            <li>언제든지 구독 해지 가능</li>
        </ul>
        
        <p><strong>구독 해지 및 개인정보 삭제 요청:</strong> <a href="' . home_url('?ainl_action=unsubscribe_form') . '">여기를 클릭하세요</a></p>
        ';
    }
    
    /**
     * 구독 취소 요청 처리
     */
    public function handle_unsubscribe_request() {
        if (!isset($_GET['ainl_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['ainl_action']);
        
        switch ($action) {
            case 'unsubscribe_form':
                $this->show_unsubscribe_form();
                break;
                
            case 'process_unsubscribe':
                $this->process_unsubscribe();
                break;
        }
    }
    
    /**
     * 구독 취소 폼 표시
     */
    private function show_unsubscribe_form() {
        $title = __('뉴스레터 구독 해지', 'ai-newsletter-generator-pro');
        
        // 간단한 HTML 페이지 출력
        wp_head();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; }
                .form-group { margin-bottom: 20px; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
                button { background: #e74c3c; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
                button:hover { background: #c0392b; }
                .info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1><?php echo esc_html($title); ?></h1>
                
                <div class="info">
                    <p><?php _e('뉴스레터 구독을 해지하고 개인정보를 삭제하려면 아래 이메일 주소를 입력해주세요.', 'ai-newsletter-generator-pro'); ?></p>
                </div>
                
                <form method="post" action="<?php echo esc_url(add_query_arg('ainl_action', 'process_unsubscribe', home_url())); ?>">
                    <div class="form-group">
                        <label for="email"><?php _e('이메일 주소', 'ai-newsletter-generator-pro'); ?></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="delete_data" value="1" checked>
                            <?php _e('개인정보도 함께 삭제합니다.', 'ai-newsletter-generator-pro'); ?>
                        </label>
                    </div>
                    
                    <?php wp_nonce_field('ainl_unsubscribe', 'ainl_nonce'); ?>
                    <button type="submit"><?php _e('구독 해지', 'ai-newsletter-generator-pro'); ?></button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * 구독 취소 처리
     */
    private function process_unsubscribe() {
        // Nonce 검증
        if (!wp_verify_nonce($_POST['ainl_nonce'], 'ainl_unsubscribe')) {
            wp_die(__('보안 검증에 실패했습니다.', 'ai-newsletter-generator-pro'));
        }
        
        $email = sanitize_email($_POST['email']);
        $delete_data = isset($_POST['delete_data']);
        
        if (!is_email($email)) {
            wp_die(__('올바른 이메일 주소를 입력해주세요.', 'ai-newsletter-generator-pro'));
        }
        
        global $wpdb;
        
        // 구독자 찾기
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ainl_subscribers WHERE email = %s",
            $email
        ));
        
        if (!$subscriber) {
            wp_die(__('해당 이메일로 구독된 정보를 찾을 수 없습니다.', 'ai-newsletter-generator-pro'));
        }
        
        if ($delete_data) {
            // 완전 삭제
            $this->delete_subscriber_data($subscriber->id);
            $message = __('구독이 해지되고 개인정보가 삭제되었습니다.', 'ai-newsletter-generator-pro');
        } else {
            // 비활성화만
            $wpdb->update(
                $wpdb->prefix . 'ainl_subscribers',
                array('status' => 'inactive'),
                array('id' => $subscriber->id)
            );
            $message = __('구독이 해지되었습니다.', 'ai-newsletter-generator-pro');
        }
        
        // 성공 페이지 표시
        wp_head();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <title><?php _e('구독 해지 완료', 'ai-newsletter-generator-pro'); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; text-align: center; }
                .container { max-width: 600px; margin: 0 auto; }
                .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 4px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1><?php _e('구독 해지 완료', 'ai-newsletter-generator-pro'); ?></h1>
                <div class="success">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <p><a href="<?php echo esc_url(home_url()); ?>"><?php _e('홈으로 돌아가기', 'ai-newsletter-generator-pro'); ?></a></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * 데이터 삭제 요청 처리
     */
    public function handle_data_deletion_request() {
        if (!isset($_GET['ainl_action']) || $_GET['ainl_action'] !== 'delete_data') {
            return;
        }
        
        if (!isset($_GET['token'])) {
            wp_die(__('잘못된 요청입니다.', 'ai-newsletter-generator-pro'));
        }
        
        $token = sanitize_text_field($_GET['token']);
        
        // 토큰으로 구독자 찾기
        global $wpdb;
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ainl_subscribers WHERE unsubscribe_token = %s",
            $token
        ));
        
        if (!$subscriber) {
            wp_die(__('잘못된 삭제 요청입니다.', 'ai-newsletter-generator-pro'));
        }
        
        // 데이터 삭제
        $this->delete_subscriber_data($subscriber->id);
        
        wp_redirect(add_query_arg('data_deleted', '1', home_url()));
        exit;
    }
    
    /**
     * 구독자 데이터 완전 삭제
     * 
     * @param int $subscriber_id 구독자 ID
     * @return bool 삭제 성공 여부
     */
    public function delete_subscriber_data($subscriber_id) {
        global $wpdb;
        
        // 트랜잭션 시작
        $wpdb->query('START TRANSACTION');
        
        try {
            // 구독자-카테고리 관계 삭제
            $wpdb->delete(
                $wpdb->prefix . 'ainl_subscriber_categories',
                array('subscriber_id' => $subscriber_id)
            );
            
            // 이메일 큐에서 삭제
            $wpdb->delete(
                $wpdb->prefix . 'ainl_email_queue',
                array('subscriber_id' => $subscriber_id)
            );
            
            // 캠페인 로그에서 삭제
            $wpdb->delete(
                $wpdb->prefix . 'ainl_campaign_logs',
                array('subscriber_id' => $subscriber_id)
            );
            
            // 구독자 정보 삭제
            $result = $wpdb->delete(
                $wpdb->prefix . 'ainl_subscribers',
                array('id' => $subscriber_id)
            );
            
            if ($result === false) {
                throw new Exception('Failed to delete subscriber');
            }
            
            $wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('AINL GDPR: Failed to delete subscriber data - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 데이터 추출 요청 처리
     */
    public function handle_data_export_request() {
        if (!isset($_GET['ainl_action']) || $_GET['ainl_action'] !== 'export_data') {
            return;
        }
        
        if (!isset($_GET['email'])) {
            wp_die(__('이메일 주소가 필요합니다.', 'ai-newsletter-generator-pro'));
        }
        
        $email = sanitize_email($_GET['email']);
        if (!is_email($email)) {
            wp_die(__('올바른 이메일 주소를 입력해주세요.', 'ai-newsletter-generator-pro'));
        }
        
        $data = $this->export_subscriber_data($email);
        
        if (empty($data)) {
            wp_die(__('해당 이메일로 구독된 정보를 찾을 수 없습니다.', 'ai-newsletter-generator-pro'));
        }
        
        // JSON 파일로 다운로드
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="newsletter_data_' . date('Y-m-d') . '.json"');
        echo wp_json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * 구독자 데이터 추출
     * 
     * @param string $email 이메일 주소
     * @return array 구독자 데이터
     */
    public function export_subscriber_data($email) {
        global $wpdb;
        
        // 구독자 정보
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, name, first_name, last_name, status, source, created_at, confirmed_at 
             FROM {$wpdb->prefix}ainl_subscribers WHERE email = %s",
            $email
        ), ARRAY_A);
        
        if (!$subscriber) {
            return array();
        }
        
        // 구독 카테고리
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT c.name 
             FROM {$wpdb->prefix}ainl_categories c
             JOIN {$wpdb->prefix}ainl_subscriber_categories sc ON c.id = sc.category_id
             WHERE sc.subscriber_id = %d",
            $subscriber['id']
        ), ARRAY_A);
        
        // 캠페인 수신 로그
        $campaigns = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title, cl.sent_at, cl.opened_at, cl.clicked_at
             FROM {$wpdb->prefix}ainl_campaign_logs cl
             JOIN {$wpdb->prefix}ainl_campaigns c ON cl.campaign_id = c.id
             WHERE cl.subscriber_id = %d
             ORDER BY cl.sent_at DESC",
            $subscriber['id']
        ), ARRAY_A);
        
        return array(
            'personal_info' => $subscriber,
            'subscribed_categories' => wp_list_pluck($categories, 'name'),
            'email_history' => $campaigns,
            'export_date' => current_time('mysql'),
            'data_retention_info' => sprintf(
                __('이 데이터는 최대 %d일간 보관됩니다.', 'ai-newsletter-generator-pro'),
                self::DATA_RETENTION_PERIOD
            )
        );
    }
    
    /**
     * WordPress 개인정보 도구와 연동 - 데이터 추출기 등록
     */
    public function register_data_exporter($exporters) {
        $exporters['ainl-newsletter'] = array(
            'exporter_friendly_name' => __('AI Newsletter Generator Pro', 'ai-newsletter-generator-pro'),
            'callback' => array($this, 'wp_data_exporter'),
        );
        return $exporters;
    }
    
    /**
     * WordPress 개인정보 도구와 연동 - 데이터 삭제기 등록
     */
    public function register_data_eraser($erasers) {
        $erasers['ainl-newsletter'] = array(
            'eraser_friendly_name' => __('AI Newsletter Generator Pro', 'ai-newsletter-generator-pro'),
            'callback' => array($this, 'wp_data_eraser'),
        );
        return $erasers;
    }
    
    /**
     * WordPress 개인정보 도구 - 데이터 추출 콜백
     */
    public function wp_data_exporter($email_address, $page = 1) {
        $data = $this->export_subscriber_data($email_address);
        
        if (empty($data)) {
            return array(
                'data' => array(),
                'done' => true,
            );
        }
        
        $export_items = array();
        
        // 개인정보
        $export_items[] = array(
            'group_id' => 'ainl_personal_info',
            'group_label' => __('뉴스레터 개인정보', 'ai-newsletter-generator-pro'),
            'item_id' => 'subscriber-' . $data['personal_info']['id'],
            'data' => array(
                array(
                    'name' => __('이메일', 'ai-newsletter-generator-pro'),
                    'value' => $data['personal_info']['email']
                ),
                array(
                    'name' => __('이름', 'ai-newsletter-generator-pro'),
                    'value' => $data['personal_info']['name']
                ),
                array(
                    'name' => __('구독일', 'ai-newsletter-generator-pro'),
                    'value' => $data['personal_info']['created_at']
                ),
                array(
                    'name' => __('상태', 'ai-newsletter-generator-pro'),
                    'value' => $data['personal_info']['status']
                )
            )
        );
        
        return array(
            'data' => $export_items,
            'done' => true,
        );
    }
    
    /**
     * WordPress 개인정보 도구 - 데이터 삭제 콜백
     */
    public function wp_data_eraser($email_address, $page = 1) {
        global $wpdb;
        
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ainl_subscribers WHERE email = %s",
            $email_address
        ));
        
        if (!$subscriber) {
            return array(
                'items_removed' => 0,
                'items_retained' => 0,
                'messages' => array(),
                'done' => true,
            );
        }
        
        $success = $this->delete_subscriber_data($subscriber->id);
        
        return array(
            'items_removed' => $success ? 1 : 0,
            'items_retained' => $success ? 0 : 1,
            'messages' => $success ? array() : array(__('데이터 삭제 중 오류가 발생했습니다.', 'ai-newsletter-generator-pro')),
            'done' => true,
        );
    }
    
    /**
     * 오래된 데이터 자동 정리
     */
    public function cleanup_expired_data() {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::DATA_RETENTION_PERIOD . ' days'));
        
        // 비활성 구독자 중 보유 기간이 지난 데이터 삭제
        $expired_subscribers = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ainl_subscribers 
             WHERE status = 'inactive' AND created_at < %s",
            $cutoff_date
        ));
        
        foreach ($expired_subscribers as $subscriber) {
            $this->delete_subscriber_data($subscriber->id);
        }
        
        // 로그 기록
        if (!empty($expired_subscribers)) {
            error_log(sprintf(
                'AINL GDPR: Cleaned up %d expired subscriber records',
                count($expired_subscribers)
            ));
        }
    }
    
    /**
     * 관리자 메뉴 추가
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ainl-dashboard',
            __('GDPR 관리', 'ai-newsletter-generator-pro'),
            __('GDPR 관리', 'ai-newsletter-generator-pro'),
            'manage_options',
            'ainl-gdpr',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 관리자 페이지
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('GDPR 및 개인정보 보호 관리', 'ai-newsletter-generator-pro'); ?></h1>
            
            <div class="card">
                <h2><?php _e('개인정보 처리방침', 'ai-newsletter-generator-pro'); ?></h2>
                <p><?php _e('구독자들이 확인할 수 있는 개인정보 처리방침 페이지입니다.', 'ai-newsletter-generator-pro'); ?></p>
                <?php
                $privacy_page_id = get_option('ainl_privacy_policy_page_id');
                if ($privacy_page_id) {
                    $privacy_page_url = get_permalink($privacy_page_id);
                    echo '<p><a href="' . esc_url($privacy_page_url) . '" target="_blank">' . __('개인정보 처리방침 보기', 'ai-newsletter-generator-pro') . '</a></p>';
                }
                ?>
            </div>
            
            <div class="card">
                <h2><?php _e('구독 해지 및 데이터 삭제', 'ai-newsletter-generator-pro'); ?></h2>
                <p><?php _e('구독자들이 스스로 구독을 해지하고 개인정보를 삭제할 수 있는 페이지입니다.', 'ai-newsletter-generator-pro'); ?></p>
                <p><a href="<?php echo esc_url(home_url('?ainl_action=unsubscribe_form')); ?>" target="_blank"><?php _e('구독 해지 페이지 보기', 'ai-newsletter-generator-pro'); ?></a></p>
            </div>
            
            <div class="card">
                <h2><?php _e('데이터 보유 현황', 'ai-newsletter-generator-pro'); ?></h2>
                <?php
                global $wpdb;
                $total_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ainl_subscribers");
                $active_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ainl_subscribers WHERE status = 'active'");
                $inactive_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ainl_subscribers WHERE status = 'inactive'");
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('구분', 'ai-newsletter-generator-pro'); ?></th>
                            <th><?php _e('개수', 'ai-newsletter-generator-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php _e('전체 구독자', 'ai-newsletter-generator-pro'); ?></td>
                            <td><?php echo esc_html($total_subscribers); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('활성 구독자', 'ai-newsletter-generator-pro'); ?></td>
                            <td><?php echo esc_html($active_subscribers); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('비활성 구독자', 'ai-newsletter-generator-pro'); ?></td>
                            <td><?php echo esc_html($inactive_subscribers); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <p><small><?php printf(__('비활성 구독자 데이터는 %d일 후 자동으로 삭제됩니다.', 'ai-newsletter-generator-pro'), self::DATA_RETENTION_PERIOD); ?></small></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX 데이터 삭제 처리
     */
    public function ajax_data_deletion() {
        // Nonce 검증
        if (!wp_verify_nonce($_POST['nonce'], 'ainl_data_deletion')) {
            wp_send_json_error(array('message' => __('보안 검증에 실패했습니다.', 'ai-newsletter-generator-pro')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('권한이 없습니다.', 'ai-newsletter-generator-pro')));
        }
        
        $subscriber_id = intval($_POST['subscriber_id']);
        $success = $this->delete_subscriber_data($subscriber_id);
        
        if ($success) {
            wp_send_json_success(array('message' => __('데이터가 삭제되었습니다.', 'ai-newsletter-generator-pro')));
        } else {
            wp_send_json_error(array('message' => __('데이터 삭제 중 오류가 발생했습니다.', 'ai-newsletter-generator-pro')));
        }
    }
    
    /**
     * 플러그인 비활성화 시 크론 정리
     */
    public static function cleanup_cron() {
        wp_clear_scheduled_hook('ainl_cleanup_expired_data');
    }
} 
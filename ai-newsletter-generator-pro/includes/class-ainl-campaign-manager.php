<?php
/**
 * 캠페인 생성 및 관리 클래스
 * 뉴스레터 캠페인의 생성, 수정, 삭제, 발송을 관리합니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 캠페인 관리 클래스
 */
class AINL_Campaign_Manager {
    
    /**
     * 클래스 인스턴스
     */
    private static $instance = null;
    
    /**
     * 데이터베이스 인스턴스
     */
    private $database;
    
    /**
     * 캠페인 테이블명
     */
    private $campaigns_table;
    
    /**
     * 캠페인 상태 목록
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_READY = 'ready';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * 마법사 단계 목록
     */
    const STEP_BASIC = 'basic';
    const STEP_CONTENT = 'content';
    const STEP_DESIGN = 'design';
    const STEP_PREVIEW = 'preview';
    const STEP_SEND = 'send';
    
    /**
     * 생성자
     */
    public function __construct() {
        global $wpdb;
        
        $this->campaigns_table = $wpdb->prefix . 'ainl_campaigns';
        
        $this->init_hooks();
    }
    
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
     * WordPress 훅 초기화
     */
    private function init_hooks() {
        // AJAX 핸들러
        add_action('wp_ajax_ainl_create_campaign', array($this, 'ajax_create_campaign'));
        add_action('wp_ajax_ainl_save_campaign_step', array($this, 'ajax_save_campaign_step'));
        add_action('wp_ajax_ainl_load_campaign', array($this, 'ajax_load_campaign'));
        add_action('wp_ajax_ainl_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_ainl_duplicate_campaign', array($this, 'ajax_duplicate_campaign'));
        add_action('wp_ajax_ainl_get_campaign_preview', array($this, 'ajax_get_campaign_preview'));
        add_action('wp_ajax_ainl_send_test_campaign', array($this, 'ajax_send_test_campaign'));
        add_action('wp_ajax_ainl_launch_campaign', array($this, 'ajax_launch_campaign'));
    }
    
    /**
     * 새 캠페인 생성
     * 
     * @param array $data 캠페인 데이터
     * @return int|WP_Error 캠페인 ID 또는 에러
     */
    public function create_campaign($data) {
        global $wpdb;
        
        // 데이터 유효성 검사
        $validation = $this->validate_campaign_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // 기본값 설정
        $defaults = array(
            'name' => '',
            'subject' => '',
            'from_name' => get_option('ainl_settings')['from_name'] ?? get_bloginfo('name'),
            'from_email' => get_option('ainl_settings')['from_email'] ?? get_option('admin_email'),
            'status' => self::STATUS_DRAFT,
            'content_filters' => wp_json_encode(array()),
            'selected_posts' => wp_json_encode(array()),
            'content' => '',
            'template_id' => 1,
            'scheduled_at' => null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $campaign_data = wp_parse_args($data, $defaults);
        
        // 데이터 정리
        $campaign_data['name'] = sanitize_text_field($campaign_data['name']);
        $campaign_data['subject'] = sanitize_text_field($campaign_data['subject']);
        $campaign_data['from_name'] = sanitize_text_field($campaign_data['from_name']);
        $campaign_data['from_email'] = sanitize_email($campaign_data['from_email']);
        $campaign_data['content'] = wp_kses_post($campaign_data['content']);
        
        $result = $wpdb->insert($this->campaigns_table, $campaign_data);
        
        if ($result === false) {
            return new WP_Error('db_error', '캠페인 생성 중 데이터베이스 오류가 발생했습니다: ' . $wpdb->last_error);
        }
        
        $campaign_id = $wpdb->insert_id;
        
        // 액션 훅 실행
        do_action('ainl_campaign_created', $campaign_id, $campaign_data);
        
        return $campaign_id;
    }
    
    /**
     * 캠페인 업데이트
     * 
     * @param int $campaign_id 캠페인 ID
     * @param array $data 업데이트할 데이터
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function update_campaign($campaign_id, $data) {
        global $wpdb;
        
        // 캠페인 존재 확인
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new WP_Error('not_found', '캠페인을 찾을 수 없습니다.');
        }
        
        // 발송 중이거나 완료된 캠페인은 수정 불가
        if (in_array($campaign->status, array(self::STATUS_SENDING, self::STATUS_SENT))) {
            return new WP_Error('invalid_status', '발송 중이거나 완료된 캠페인은 수정할 수 없습니다.');
        }
        
        // 데이터 정리
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['subject'])) {
            $update_data['subject'] = sanitize_text_field($data['subject']);
        }
        if (isset($data['from_name'])) {
            $update_data['from_name'] = sanitize_text_field($data['from_name']);
        }
        if (isset($data['from_email'])) {
            $update_data['from_email'] = sanitize_email($data['from_email']);
        }
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        if (isset($data['content_filters'])) {
            $update_data['content_filters'] = wp_json_encode($data['content_filters']);
        }
        if (isset($data['selected_posts'])) {
            $update_data['selected_posts'] = wp_json_encode($data['selected_posts']);
        }
        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
        }
        if (isset($data['template_id'])) {
            $update_data['template_id'] = intval($data['template_id']);
        }
        if (isset($data['scheduled_at'])) {
            $update_data['scheduled_at'] = $data['scheduled_at'];
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->campaigns_table,
            $update_data,
            array('id' => $campaign_id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', '캠페인 업데이트 중 데이터베이스 오류가 발생했습니다: ' . $wpdb->last_error);
        }
        
        // 액션 훅 실행
        do_action('ainl_campaign_updated', $campaign_id, $update_data);
        
        return true;
    }
    
    /**
     * 캠페인 조회
     * 
     * @param int $campaign_id 캠페인 ID
     * @return object|null 캠페인 데이터 또는 null
     */
    public function get_campaign($campaign_id) {
        global $wpdb;
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->campaigns_table} WHERE id = %d",
            $campaign_id
        ));
        
        if ($campaign) {
            // JSON 데이터 디코딩
            $campaign->content_filters = json_decode($campaign->content_filters, true);
            $campaign->selected_posts = json_decode($campaign->selected_posts, true);
        }
        
        return $campaign;
    }
    
    /**
     * 캠페인 목록 조회
     * 
     * @param array $args 조회 조건
     * @return array 캠페인 목록
     */
    public function get_campaigns($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        // 상태 필터
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        // 검색 필터
        if (!empty($args['search'])) {
            $where_clauses[] = '(name LIKE %s OR subject LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // 정렬
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }
        
        $sql = "SELECT * FROM {$this->campaigns_table} WHERE {$where_sql} ORDER BY {$orderby}";
        
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $campaigns = $wpdb->get_results($sql);
        
        // JSON 데이터 디코딩
        foreach ($campaigns as $campaign) {
            $campaign->content_filters = json_decode($campaign->content_filters, true);
            $campaign->selected_posts = json_decode($campaign->selected_posts, true);
        }
        
        return $campaigns;
    }
    
    /**
     * 캠페인 삭제
     * 
     * @param int $campaign_id 캠페인 ID
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function delete_campaign($campaign_id) {
        global $wpdb;
        
        // 캠페인 존재 확인
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new WP_Error('not_found', '캠페인을 찾을 수 없습니다.');
        }
        
        // 발송 중인 캠페인은 삭제 불가
        if ($campaign->status === self::STATUS_SENDING) {
            return new WP_Error('invalid_status', '발송 중인 캠페인은 삭제할 수 없습니다.');
        }
        
        $result = $wpdb->delete(
            $this->campaigns_table,
            array('id' => $campaign_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', '캠페인 삭제 중 데이터베이스 오류가 발생했습니다: ' . $wpdb->last_error);
        }
        
        // 액션 훅 실행
        do_action('ainl_campaign_deleted', $campaign_id);
        
        return true;
    }
    
    /**
     * 캠페인 복제
     * 
     * @param int $campaign_id 복제할 캠페인 ID
     * @return int|WP_Error 새 캠페인 ID 또는 에러
     */
    public function duplicate_campaign($campaign_id) {
        $original = $this->get_campaign($campaign_id);
        if (!$original) {
            return new WP_Error('not_found', '복제할 캠페인을 찾을 수 없습니다.');
        }
        
        // 복제 데이터 준비
        $duplicate_data = array(
            'name' => $original->name . ' (복사본)',
            'subject' => $original->subject,
            'from_name' => $original->from_name,
            'from_email' => $original->from_email,
            'content_filters' => $original->content_filters,
            'selected_posts' => $original->selected_posts,
            'content' => $original->content,
            'template_id' => $original->template_id,
            'status' => self::STATUS_DRAFT
        );
        
        return $this->create_campaign($duplicate_data);
    }
    
    /**
     * 캠페인 데이터 유효성 검사
     * 
     * @param array $data 검사할 데이터
     * @return bool|WP_Error 유효성 검사 결과
     */
    private function validate_campaign_data($data) {
        $errors = array();
        
        // 필수 필드 검사
        if (empty($data['name'])) {
            $errors[] = '캠페인 이름은 필수입니다.';
        }
        
        if (empty($data['subject'])) {
            $errors[] = '이메일 제목은 필수입니다.';
        }
        
        if (!empty($data['from_email']) && !is_email($data['from_email'])) {
            $errors[] = '유효한 발신자 이메일 주소를 입력해주세요.';
        }
        
        if (!empty($data['status']) && !in_array($data['status'], array(
            self::STATUS_DRAFT,
            self::STATUS_READY,
            self::STATUS_SENDING,
            self::STATUS_SENT,
            self::STATUS_PAUSED,
            self::STATUS_CANCELLED
        ))) {
            $errors[] = '유효하지 않은 캠페인 상태입니다.';
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_error', implode(' ', $errors));
        }
        
        return true;
    }
    
    /**
     * 마법사 단계 유효성 검사
     * 
     * @param string $step 단계명
     * @param array $data 단계 데이터
     * @return bool|WP_Error 유효성 검사 결과
     */
    public function validate_wizard_step($step, $data) {
        switch ($step) {
            case self::STEP_BASIC:
                return $this->validate_basic_step($data);
                
            case self::STEP_CONTENT:
                return $this->validate_content_step($data);
                
            case self::STEP_DESIGN:
                return $this->validate_design_step($data);
                
            case self::STEP_PREVIEW:
                return $this->validate_preview_step($data);
                
            case self::STEP_SEND:
                return $this->validate_send_step($data);
                
            default:
                return new WP_Error('invalid_step', '유효하지 않은 단계입니다.');
        }
    }
    
    /**
     * 기본 정보 단계 유효성 검사
     */
    private function validate_basic_step($data) {
        $errors = array();
        
        if (empty($data['name'])) {
            $errors[] = '캠페인 이름을 입력해주세요.';
        }
        
        if (empty($data['subject'])) {
            $errors[] = '이메일 제목을 입력해주세요.';
        }
        
        if (!empty($data['from_email']) && !is_email($data['from_email'])) {
            $errors[] = '유효한 발신자 이메일 주소를 입력해주세요.';
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_error', implode(' ', $errors));
        }
        
        return true;
    }
    
    /**
     * 콘텐츠 단계 유효성 검사
     */
    private function validate_content_step($data) {
        // 콘텐츠 필터 또는 선택된 게시물이 있어야 함
        $has_filters = !empty($data['content_filters']) && is_array($data['content_filters']);
        $has_posts = !empty($data['selected_posts']) && is_array($data['selected_posts']);
        
        if (!$has_filters && !$has_posts) {
            return new WP_Error('validation_error', '콘텐츠 필터를 설정하거나 게시물을 선택해주세요.');
        }
        
        return true;
    }
    
    /**
     * 디자인 단계 유효성 검사
     */
    private function validate_design_step($data) {
        if (empty($data['template_id'])) {
            return new WP_Error('validation_error', '템플릿을 선택해주세요.');
        }
        
        return true;
    }
    
    /**
     * 미리보기 단계 유효성 검사
     */
    private function validate_preview_step($data) {
        if (empty($data['content'])) {
            return new WP_Error('validation_error', '뉴스레터 콘텐츠가 생성되지 않았습니다.');
        }
        
        return true;
    }
    
    /**
     * 발송 단계 유효성 검사
     */
    private function validate_send_step($data) {
        // 구독자 수 확인
        $subscriber_manager = new AINL_Subscriber_Manager();
        $active_subscribers = $subscriber_manager->get_subscribers(array('status' => 'active'));
        
        if (empty($active_subscribers)) {
            return new WP_Error('validation_error', '활성 구독자가 없습니다.');
        }
        
        return true;
    }
    
    /**
     * AJAX: 캠페인 생성
     */
    public function ajax_create_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_data = array(
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'subject' => sanitize_text_field($_POST['subject'] ?? ''),
            'from_name' => sanitize_text_field($_POST['from_name'] ?? ''),
            'from_email' => sanitize_email($_POST['from_email'] ?? '')
        );
        
        $result = $this->create_campaign($campaign_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'campaign_id' => $result,
                'message' => '캠페인이 성공적으로 생성되었습니다.'
            ));
        }
    }
    
    /**
     * AJAX: 캠페인 단계 저장
     */
    public function ajax_save_campaign_step() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $step = sanitize_text_field($_POST['step']);
        $step_data = $_POST['step_data'] ?? array();
        
        // 단계 유효성 검사
        $validation = $this->validate_wizard_step($step, $step_data);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }
        
        // 캠페인 업데이트
        $result = $this->update_campaign($campaign_id, $step_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '단계가 성공적으로 저장되었습니다.'
            ));
        }
    }
    
    /**
     * AJAX: 캠페인 로드
     */
    public function ajax_load_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $campaign = $this->get_campaign($campaign_id);
        
        if (!$campaign) {
            wp_send_json_error('캠페인을 찾을 수 없습니다.');
        }
        
        wp_send_json_success($campaign);
    }
    
    /**
     * AJAX: 캠페인 삭제
     */
    public function ajax_delete_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $result = $this->delete_campaign($campaign_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '캠페인이 성공적으로 삭제되었습니다.'
            ));
        }
    }
    
    /**
     * AJAX: 캠페인 복제
     */
    public function ajax_duplicate_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $result = $this->duplicate_campaign($campaign_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'campaign_id' => $result,
                'message' => '캠페인이 성공적으로 복제되었습니다.'
            ));
        }
    }
    
    /**
     * AJAX: 캠페인 미리보기
     */
    public function ajax_get_campaign_preview() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $campaign = $this->get_campaign($campaign_id);
        
        if (!$campaign) {
            wp_send_json_error('캠페인을 찾을 수 없습니다.');
        }
        
        // 템플릿 매니저를 통해 HTML 생성
        $template_manager = new AINL_Template_Manager();
        $html = $template_manager->render_template($campaign->template_id, array(
            'content' => $campaign->content,
            'subject' => $campaign->subject,
            'from_name' => $campaign->from_name
        ));
        
        wp_send_json_success(array(
            'html' => $html
        ));
    }
    
    /**
     * AJAX: 테스트 이메일 발송
     */
    public function ajax_send_test_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_id = intval($_POST['campaign_id']);
        $test_email = sanitize_email($_POST['test_email']);
        
        if (!is_email($test_email)) {
            wp_send_json_error('유효한 이메일 주소를 입력해주세요.');
        }
        
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            wp_send_json_error('캠페인을 찾을 수 없습니다.');
        }
        
        // 이메일 매니저를 통해 테스트 이메일 발송
        $email_manager = AINL_Email_Manager::get_instance();
        
        // 템플릿 렌더링
        $template_manager = new AINL_Template_Manager();
        $html_content = $template_manager->render_template($campaign->template_id, array(
            'content' => $campaign->content,
            'subject' => $campaign->subject,
            'from_name' => $campaign->from_name
        ));
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $campaign->from_name . ' <' . $campaign->from_email . '>'
        );
        
        $result = $email_manager->add_to_queue(
            $test_email,
            '[테스트] ' . $campaign->subject,
            $html_content,
            $headers,
            array(),
            'high'
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => '테스트 이메일이 큐에 추가되었습니다.'
            ));
        } else {
            wp_send_json_error('테스트 이메일 발송 중 오류가 발생했습니다.');
        }
    }
    
    /**
     * AJAX: 캠페인 발송
     */
    public function ajax_launch_campaign() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $campaign_data = $_POST['campaign_data'] ?? array();
        $result = $this->launch_campaign($campaign_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * 캠페인 발송
     * 
     * @param int|array $campaign_data 캠페인 ID 또는 캠페인 데이터
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function launch_campaign($campaign_data) {
        // 캠페인 데이터가 배열인 경우 (새 캠페인 또는 임시 캠페인)
        if (is_array($campaign_data)) {
            return $this->launch_campaign_from_data($campaign_data);
        }
        
        // 캠페인 ID인 경우 (기존 캠페인)
        $campaign_id = intval($campaign_data);
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new WP_Error('not_found', '캠페인을 찾을 수 없습니다.');
        }
        
        return $this->launch_campaign_from_object($campaign);
    }
    
    /**
     * 캠페인 데이터로부터 발송
     * 
     * @param array $campaign_data 캠페인 데이터
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    private function launch_campaign_from_data($campaign_data) {
        // 필수 필드 검증
        $required_fields = array('name', 'subject', 'content', 'from_name', 'from_email');
        foreach ($required_fields as $field) {
            if (empty($campaign_data[$field])) {
                return new WP_Error('missing_field', "필수 필드가 누락되었습니다: {$field}");
            }
        }
        
        // 예약 발송 처리
        if (isset($campaign_data['status']) && $campaign_data['status'] === self::STATUS_READY) {
            if (empty($campaign_data['scheduled_at'])) {
                return new WP_Error('missing_schedule', '예약 발송 시간이 설정되지 않았습니다.');
            }
            
            // 캠페인 저장 후 예약 처리
            $campaign_id = $this->create_campaign($campaign_data);
            if (is_wp_error($campaign_id)) {
                return $campaign_id;
            }
            
            // 예약 작업 등록
            $this->schedule_campaign($campaign_id, $campaign_data['scheduled_at']);
            
            return array(
                'campaign_id' => $campaign_id,
                'message' => '캠페인이 예약되었습니다.',
                'scheduled_at' => $campaign_data['scheduled_at']
            );
        }
        
        // 즉시 발송 처리
        return $this->send_campaign_immediately($campaign_data);
    }
    
    /**
     * 캠페인 객체로부터 발송
     * 
     * @param object $campaign 캠페인 객체
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    private function launch_campaign_from_object($campaign) {
        // 발송 가능 상태 확인
        if (in_array($campaign->status, array(self::STATUS_SENDING, self::STATUS_SENT))) {
            return new WP_Error('invalid_status', '이미 발송 중이거나 완료된 캠페인입니다.');
        }
        
        // 캠페인 데이터 배열로 변환
        $campaign_data = array(
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'content' => $campaign->content,
            'from_name' => $campaign->from_name,
            'from_email' => $campaign->from_email,
            'template_id' => $campaign->template_id
        );
        
        return $this->send_campaign_immediately($campaign_data);
    }
    
    /**
     * 캠페인 즉시 발송
     * 
     * @param array $campaign_data 캠페인 데이터
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    private function send_campaign_immediately($campaign_data) {
        // 구독자 목록 조회
        $subscriber_manager = AINL_Subscriber_Manager::get_instance();
        $subscribers = $subscriber_manager->get_subscribers(array('status' => 'active'));
        
        if (empty($subscribers)) {
            return new WP_Error('no_subscribers', '활성 구독자가 없습니다.');
        }
        
        // 캠페인 상태 업데이트 (기존 캠페인인 경우)
        if (isset($campaign_data['campaign_id'])) {
            $this->update_campaign($campaign_data['campaign_id'], array('status' => self::STATUS_SENDING));
        }
        
        // 템플릿 렌더링
        $html_content = $this->render_campaign_content($campaign_data);
        if (is_wp_error($html_content)) {
            return $html_content;
        }
        
        // 이메일 헤더 설정
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $campaign_data['from_name'] . ' <' . $campaign_data['from_email'] . '>'
        );
        
        // 이메일 큐에 추가
        $email_manager = AINL_Email_Manager::get_instance();
        $queued_count = 0;
        $failed_count = 0;
        
        foreach ($subscribers as $subscriber) {
            // 개인화된 콘텐츠 생성
            $personalized_content = $this->personalize_content($html_content, $subscriber);
            
            // 구독 취소 링크 추가
            $unsubscribe_url = $this->generate_unsubscribe_url($subscriber->email);
            $personalized_content = str_replace('{{unsubscribe_url}}', $unsubscribe_url, $personalized_content);
            
            // 큐에 추가
            $result = $email_manager->add_to_queue(
                $subscriber->email,
                $campaign_data['subject'],
                $personalized_content,
                $headers,
                array(),
                'normal'
            );
            
            if ($result) {
                $queued_count++;
            } else {
                $failed_count++;
            }
        }
        
        // 발송 완료 상태로 변경 (기존 캠페인인 경우)
        if (isset($campaign_data['campaign_id'])) {
            $this->update_campaign($campaign_data['campaign_id'], array(
                'status' => self::STATUS_SENT,
                'sent_at' => current_time('mysql'),
                'sent_count' => $queued_count
            ));
        }
        
        // 액션 훅 실행
        do_action('ainl_campaign_launched', $campaign_data, $queued_count, $failed_count);
        
        return array(
            'success' => true,
            'message' => "캠페인이 발송되었습니다. (성공: {$queued_count}명, 실패: {$failed_count}명)",
            'queued_count' => $queued_count,
            'failed_count' => $failed_count
        );
    }
    
    /**
     * 캠페인 콘텐츠 렌더링
     * 
     * @param array $campaign_data 캠페인 데이터
     * @return string|WP_Error 렌더링된 HTML 또는 에러
     */
    private function render_campaign_content($campaign_data) {
        // 템플릿 매니저가 있는 경우 템플릿 사용
        if (class_exists('AINL_Template_Manager') && !empty($campaign_data['template_id'])) {
            $template_manager = AINL_Template_Manager::get_instance();
            $html_content = $template_manager->render_template($campaign_data['template_id'], array(
                'content' => $campaign_data['content'],
                'subject' => $campaign_data['subject'],
                'from_name' => $campaign_data['from_name'],
                'site_name' => get_bloginfo('name'),
                'newsletter_date' => date_i18n(get_option('date_format'))
            ));
        } else {
            // 기본 HTML 래퍼 사용
            $html_content = $this->wrap_content_in_basic_template($campaign_data);
        }
        
        return $html_content;
    }
    
    /**
     * 기본 템플릿으로 콘텐츠 래핑
     * 
     * @param array $campaign_data 캠페인 데이터
     * @return string HTML 콘텐츠
     */
    private function wrap_content_in_basic_template($campaign_data) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $current_date = date_i18n(get_option('date_format'));
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($campaign_data['subject']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #0073aa; padding-bottom: 20px; margin-bottom: 30px; }
        .content { margin-bottom: 30px; }
        .footer { text-align: center; border-top: 1px solid #e1e1e1; padding-top: 20px; margin-top: 30px; font-size: 12px; color: #666; }
        .newsletter-post { margin-bottom: 30px; padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px; }
        a { color: #0073aa; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . esc_html($site_name) . ' 뉴스레터</h1>
        <p>' . esc_html($current_date) . '</p>
    </div>
    
    <div class="content">
        ' . $campaign_data['content'] . '
    </div>
    
    <div class="footer">
        <p>이 이메일은 <a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a>에서 발송되었습니다.</p>
        <p><a href="{{unsubscribe_url}}">구독 취소</a></p>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * 콘텐츠 개인화
     * 
     * @param string $content 원본 콘텐츠
     * @param object $subscriber 구독자 정보
     * @return string 개인화된 콘텐츠
     */
    private function personalize_content($content, $subscriber) {
        $replacements = array(
            '{{subscriber_name}}' => $subscriber->name ?: '구독자',
            '{{subscriber_email}}' => $subscriber->email,
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url(),
            '{{newsletter_date}}' => date_i18n(get_option('date_format'))
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    /**
     * 구독 취소 URL 생성
     * 
     * @param string $email 구독자 이메일
     * @return string 구독 취소 URL
     */
    private function generate_unsubscribe_url($email) {
        $token = wp_hash($email . 'unsubscribe' . wp_salt());
        return add_query_arg(array(
            'action' => 'ainl_unsubscribe',
            'email' => urlencode($email),
            'token' => $token
        ), home_url());
    }
    
    /**
     * 캠페인 예약
     * 
     * @param int $campaign_id 캠페인 ID
     * @param string $scheduled_at 예약 시간
     * @return bool 성공 여부
     */
    private function schedule_campaign($campaign_id, $scheduled_at) {
        $timestamp = strtotime($scheduled_at);
        
        if ($timestamp <= time()) {
            return false;
        }
        
        // WordPress 크론 작업 등록
        wp_schedule_single_event($timestamp, 'ainl_send_scheduled_campaign', array($campaign_id));
        
        // 크론 훅 등록 (아직 등록되지 않은 경우)
        if (!has_action('ainl_send_scheduled_campaign', array($this, 'send_scheduled_campaign'))) {
            add_action('ainl_send_scheduled_campaign', array($this, 'send_scheduled_campaign'));
        }
        
        return true;
    }
    
    /**
     * 예약된 캠페인 발송
     * 
     * @param int $campaign_id 캠페인 ID
     */
    public function send_scheduled_campaign($campaign_id) {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign || $campaign->status !== self::STATUS_READY) {
            return;
        }
        
        // 상태를 발송 중으로 변경
        $this->update_campaign($campaign_id, array('status' => self::STATUS_SENDING));
        
        // 발송 실행
        $result = $this->launch_campaign_from_object($campaign);
        
        if (is_wp_error($result)) {
            // 발송 실패 시 상태를 준비 완료로 되돌림
            $this->update_campaign($campaign_id, array('status' => self::STATUS_READY));
            
            // 에러 로그 기록
            error_log('AINL: 예약 캠페인 발송 실패 - ' . $result->get_error_message());
        }
    }
    
    /**
     * 캠페인을 템플릿으로 저장
     * 
     * @param int $campaign_id 캠페인 ID
     * @return int|WP_Error 템플릿 ID 또는 에러
     */
    public function save_as_template($campaign_id) {
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new WP_Error('not_found', '템플릿으로 저장할 캠페인을 찾을 수 없습니다.');
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
                'content_type' => $campaign->content_type ?? 'filter',
                'content_filters' => $campaign->content_filters,
                'selected_posts' => $campaign->selected_posts
            ))
        );
        
        // 템플릿 생성
        $template_id = $template_manager->create_template($template_data);
        
        if ($template_id) {
            // 액션 훅 실행
            do_action('ainl_campaign_saved_as_template', $campaign_id, $template_id);
        }
        
        return $template_id;
    }
    
    /**
     * 템플릿에서 캠페인 생성
     * 
     * @param int $template_id 템플릿 ID
     * @param array $override_data 덮어쓸 데이터
     * @return int|WP_Error 캠페인 ID 또는 에러
     */
    public function create_from_template($template_id, $override_data = array()) {
        $template_manager = AINL_Template_Manager::get_instance();
        $template = $template_manager->get_template($template_id);
        
        if (!$template) {
            return new WP_Error('not_found', '템플릿을 찾을 수 없습니다.');
        }
        
        // 템플릿 설정 디코딩
        $template_settings = json_decode($template->settings, true) ?: array();
        
        // 캠페인 데이터 준비
        $campaign_data = array_merge(array(
            'name' => $template->name . '에서 생성된 캠페인',
            'subject' => '새 뉴스레터',
            'from_name' => $template_settings['from_name'] ?? get_bloginfo('name'),
            'from_email' => $template_settings['from_email'] ?? get_option('admin_email'),
            'content' => $template->content,
            'content_type' => $template_settings['content_type'] ?? 'filter',
            'content_filters' => $template_settings['content_filters'] ?? array(),
            'selected_posts' => $template_settings['selected_posts'] ?? array(),
            'template_id' => $template_id,
            'status' => self::STATUS_DRAFT
        ), $override_data);
        
        return $this->create_campaign($campaign_data);
    }
    
    /**
     * 캠페인 버전 관리
     * 
     * @param int $campaign_id 캠페인 ID
     * @return int|WP_Error 버전 ID 또는 에러
     */
    public function create_version($campaign_id) {
        global $wpdb;
        
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) {
            return new WP_Error('not_found', '캠페인을 찾을 수 없습니다.');
        }
        
        // 버전 테이블에 현재 상태 저장
        $version_data = array(
            'campaign_id' => $campaign_id,
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'content' => $campaign->content,
            'content_filters' => json_encode($campaign->content_filters),
            'selected_posts' => json_encode($campaign->selected_posts),
            'version_number' => $this->get_next_version_number($campaign_id),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $this->campaigns_table . '_versions',
            $version_data
        );
        
        if ($result === false) {
            return new WP_Error('db_error', '버전 생성 중 데이터베이스 오류가 발생했습니다: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * 다음 버전 번호 가져오기
     * 
     * @param int $campaign_id 캠페인 ID
     * @return int 다음 버전 번호
     */
    private function get_next_version_number($campaign_id) {
        global $wpdb;
        
        $max_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version_number) FROM {$this->campaigns_table}_versions WHERE campaign_id = %d",
            $campaign_id
        ));
        
        return intval($max_version) + 1;
    }
    
    /**
     * 캠페인 버전 목록 조회
     * 
     * @param int $campaign_id 캠페인 ID
     * @return array 버전 목록
     */
    public function get_campaign_versions($campaign_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->campaigns_table}_versions WHERE campaign_id = %d ORDER BY version_number DESC",
            $campaign_id
        ));
    }
    
    /**
     * 특정 버전으로 복원
     * 
     * @param int $campaign_id 캠페인 ID
     * @param int $version_number 버전 번호
     * @return bool|WP_Error 성공 여부 또는 에러
     */
    public function restore_version($campaign_id, $version_number) {
        global $wpdb;
        
        // 버전 데이터 조회
        $version = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->campaigns_table}_versions WHERE campaign_id = %d AND version_number = %d",
            $campaign_id,
            $version_number
        ));
        
        if (!$version) {
            return new WP_Error('not_found', '지정된 버전을 찾을 수 없습니다.');
        }
        
        // 현재 버전 백업
        $this->create_version($campaign_id);
        
        // 버전 데이터로 캠페인 업데이트
        $update_data = array(
            'name' => $version->name,
            'subject' => $version->subject,
            'content' => $version->content,
            'content_filters' => $version->content_filters,
            'selected_posts' => $version->selected_posts,
            'updated_at' => current_time('mysql')
        );
        
        return $this->update_campaign($campaign_id, $update_data);
    }
} 
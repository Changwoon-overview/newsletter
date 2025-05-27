<?php
/**
 * 구독자 관리 클래스
 * 구독자의 생성, 조회, 수정, 삭제 기능을 담당합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Subscriber_Manager {
    
    /**
     * 구독자 상태 상수
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_UNSUBSCRIBED = 'unsubscribed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_BLOCKED = 'blocked';
    
    /**
     * 데이터베이스 테이블명
     */
    private $table_name;
    
    /**
     * 생성자
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ainl_subscribers';
    }
    
    /**
     * 새 구독자 추가
     * 
     * @param array $data 구독자 데이터
     * @return int|false 구독자 ID 또는 false
     */
    public function create_subscriber($data) {
        global $wpdb;
        
        // 데이터 유효성 검증
        $validation_result = $this->validate_subscriber_data($data);
        if ($validation_result !== true) {
            return new WP_Error('validation_failed', implode(', ', $validation_result));
        }
        
        // 이메일 중복 체크
        if ($this->email_exists($data['email'])) {
            return new WP_Error('email_exists', '이미 등록된 이메일 주소입니다.');
        }
        
        // 기본값 설정
        $subscriber_data = array(
            'email' => sanitize_email($data['email']),
            'first_name' => isset($data['first_name']) ? sanitize_text_field($data['first_name']) : '',
            'last_name' => isset($data['last_name']) ? sanitize_text_field($data['last_name']) : '',
            'status' => isset($data['status']) ? $data['status'] : self::STATUS_ACTIVE,
            'source' => isset($data['source']) ? sanitize_text_field($data['source']) : 'manual',
            'tags' => isset($data['tags']) ? sanitize_text_field($data['tags']) : '',
            'custom_fields' => isset($data['custom_fields']) ? wp_json_encode($data['custom_fields']) : '{}',
            'subscribed_at' => current_time('mysql'),
            'confirmed_at' => isset($data['confirmed']) && $data['confirmed'] ? current_time('mysql') : null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
        );
        
        $result = $wpdb->insert(
            $this->table_name,
            $subscriber_data,
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
        }
        
        $subscriber_id = $wpdb->insert_id;
        
        // 액션 훅 실행
        do_action('ainl_subscriber_created', $subscriber_id, $subscriber_data);
        
        return $subscriber_id;
    }
    
    /**
     * 구독자 정보 조회
     * 
     * @param int $subscriber_id 구독자 ID
     * @return object|null 구독자 정보
     */
    public function get_subscriber($subscriber_id) {
        global $wpdb;
        
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $subscriber_id
        ));
        
        if ($subscriber && $subscriber->custom_fields) {
            $subscriber->custom_fields = json_decode($subscriber->custom_fields, true);
        }
        
        return $subscriber;
    }
    
    /**
     * 이메일로 구독자 조회
     * 
     * @param string $email 이메일 주소
     * @return object|null 구독자 정보
     */
    public function get_subscriber_by_email($email) {
        global $wpdb;
        
        $subscriber = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE email = %s",
            sanitize_email($email)
        ));
        
        if ($subscriber && $subscriber->custom_fields) {
            $subscriber->custom_fields = json_decode($subscriber->custom_fields, true);
        }
        
        return $subscriber;
    }
    
    /**
     * 구독자 목록 조회 (페이지네이션 지원)
     * 
     * @param array $args 조회 조건
     * @return array 구독자 목록과 총 개수
     */
    public function get_subscribers($args = array()) {
        global $wpdb;
        
        // 기본값 설정
        $defaults = array(
            'status' => '',
            'search' => '',
            'tags' => '',
            'source' => '',
            'orderby' => 'subscribed_at',
            'order' => 'DESC',
            'per_page' => 20,
            'page' => 1,
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // WHERE 조건 구성
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($args['tags'])) {
            $where_conditions[] = 'tags LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($args['tags']) . '%';
        }
        
        if (!empty($args['source'])) {
            $where_conditions[] = 'source = %s';
            $where_values[] = $args['source'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'subscribed_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'subscribed_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // 총 개수 조회
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total_count = $wpdb->get_var($count_query);
        
        // ORDER BY 조건
        $allowed_orderby = array('id', 'email', 'first_name', 'last_name', 'status', 'subscribed_at', 'confirmed_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'subscribed_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // LIMIT 조건
        $per_page = max(1, intval($args['per_page']));
        $page = max(1, intval($args['page']));
        $offset = ($page - 1) * $per_page;
        
        // 메인 쿼리
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        
        $subscribers = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        // custom_fields JSON 디코딩
        foreach ($subscribers as $subscriber) {
            if ($subscriber->custom_fields) {
                $subscriber->custom_fields = json_decode($subscriber->custom_fields, true);
            }
        }
        
        return array(
            'subscribers' => $subscribers,
            'total_count' => $total_count,
            'total_pages' => ceil($total_count / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        );
    }
    
    /**
     * 구독자 정보 업데이트
     * 
     * @param int $subscriber_id 구독자 ID
     * @param array $data 업데이트할 데이터
     * @return bool 성공 여부
     */
    public function update_subscriber($subscriber_id, $data) {
        global $wpdb;
        
        // 구독자 존재 확인
        $existing_subscriber = $this->get_subscriber($subscriber_id);
        if (!$existing_subscriber) {
            return new WP_Error('subscriber_not_found', '구독자를 찾을 수 없습니다.');
        }
        
        // 이메일 변경 시 중복 체크
        if (isset($data['email']) && $data['email'] !== $existing_subscriber->email) {
            if ($this->email_exists($data['email'])) {
                return new WP_Error('email_exists', '이미 등록된 이메일 주소입니다.');
            }
        }
        
        // 업데이트할 데이터 준비
        $update_data = array();
        $update_format = array();
        
        $allowed_fields = array(
            'email' => '%s',
            'first_name' => '%s',
            'last_name' => '%s',
            'status' => '%s',
            'tags' => '%s',
            'custom_fields' => '%s'
        );
        
        foreach ($allowed_fields as $field => $format) {
            if (isset($data[$field])) {
                if ($field === 'email') {
                    $update_data[$field] = sanitize_email($data[$field]);
                } elseif ($field === 'custom_fields') {
                    $update_data[$field] = is_array($data[$field]) ? wp_json_encode($data[$field]) : $data[$field];
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
                $update_format[] = $format;
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', '업데이트할 데이터가 없습니다.');
        }
        
        // 수정 시간 추가
        $update_data['updated_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $subscriber_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
        }
        
        // 액션 훅 실행
        do_action('ainl_subscriber_updated', $subscriber_id, $update_data, $existing_subscriber);
        
        return true;
    }
    
    /**
     * 구독자 삭제
     * 
     * @param int $subscriber_id 구독자 ID
     * @return bool 성공 여부
     */
    public function delete_subscriber($subscriber_id) {
        global $wpdb;
        
        // 구독자 존재 확인
        $subscriber = $this->get_subscriber($subscriber_id);
        if (!$subscriber) {
            return new WP_Error('subscriber_not_found', '구독자를 찾을 수 없습니다.');
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $subscriber_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
        }
        
        // 액션 훅 실행
        do_action('ainl_subscriber_deleted', $subscriber_id, $subscriber);
        
        return true;
    }
    
    /**
     * 대량 구독자 삭제
     * 
     * @param array $subscriber_ids 구독자 ID 배열
     * @return array 결과 정보
     */
    public function bulk_delete_subscribers($subscriber_ids) {
        global $wpdb;
        
        if (empty($subscriber_ids) || !is_array($subscriber_ids)) {
            return new WP_Error('invalid_data', '유효하지 않은 구독자 ID입니다.');
        }
        
        $subscriber_ids = array_map('intval', $subscriber_ids);
        $placeholders = implode(',', array_fill(0, count($subscriber_ids), '%d'));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})",
            $subscriber_ids
        ));
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
        }
        
        // 액션 훅 실행
        do_action('ainl_subscribers_bulk_deleted', $subscriber_ids);
        
        return array(
            'deleted_count' => $result,
            'requested_count' => count($subscriber_ids)
        );
    }
    
    /**
     * 대량 구독자 상태 변경
     * 
     * @param array $subscriber_ids 구독자 ID 배열
     * @param string $status 새로운 상태
     * @return array 결과 정보
     */
    public function bulk_update_status($subscriber_ids, $status) {
        global $wpdb;
        
        if (empty($subscriber_ids) || !is_array($subscriber_ids)) {
            return new WP_Error('invalid_data', '유효하지 않은 구독자 ID입니다.');
        }
        
        if (!in_array($status, $this->get_valid_statuses())) {
            return new WP_Error('invalid_status', '유효하지 않은 상태입니다.');
        }
        
        $subscriber_ids = array_map('intval', $subscriber_ids);
        $placeholders = implode(',', array_fill(0, count($subscriber_ids), '%d'));
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
            array_merge(array($status, current_time('mysql')), $subscriber_ids)
        ));
        
        if ($result === false) {
            return new WP_Error('db_error', 'Database error: ' . $wpdb->last_error);
        }
        
        // 액션 훅 실행
        do_action('ainl_subscribers_bulk_status_updated', $subscriber_ids, $status);
        
        return array(
            'updated_count' => $result,
            'requested_count' => count($subscriber_ids)
        );
    }
    
    /**
     * 이메일 중복 체크
     * 
     * @param string $email 이메일 주소
     * @param int $exclude_id 제외할 구독자 ID
     * @return bool 중복 여부
     */
    public function email_exists($email, $exclude_id = 0) {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s";
        $params = array(sanitize_email($email));
        
        if ($exclude_id > 0) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, $params));
        
        return $count > 0;
    }
    
    /**
     * 구독자 데이터 유효성 검증
     * 
     * @param array $data 구독자 데이터
     * @return bool|array true 또는 오류 메시지 배열
     */
    private function validate_subscriber_data($data) {
        $errors = array();
        
        // 이메일 필수 체크
        if (empty($data['email'])) {
            $errors[] = '이메일 주소는 필수입니다.';
        } elseif (!is_email($data['email'])) {
            $errors[] = '유효하지 않은 이메일 주소입니다.';
        }
        
        // 상태 유효성 체크
        if (isset($data['status']) && !in_array($data['status'], $this->get_valid_statuses())) {
            $errors[] = '유효하지 않은 구독자 상태입니다.';
        }
        
        // 이름 길이 체크
        if (isset($data['first_name']) && strlen($data['first_name']) > 50) {
            $errors[] = '이름은 50자를 초과할 수 없습니다.';
        }
        
        if (isset($data['last_name']) && strlen($data['last_name']) > 50) {
            $errors[] = '성은 50자를 초과할 수 없습니다.';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * 유효한 구독자 상태 목록 반환
     * 
     * @return array 상태 목록
     */
    public function get_valid_statuses() {
        return array(
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_UNSUBSCRIBED,
            self::STATUS_BOUNCED,
            self::STATUS_BLOCKED
        );
    }
    
    /**
     * 구독자 통계 조회
     * 
     * @return array 통계 정보
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // 전체 구독자 수
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // 상태별 구독자 수
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
            OBJECT_K
        );
        
        foreach ($this->get_valid_statuses() as $status) {
            $stats[$status] = isset($status_counts[$status]) ? $status_counts[$status]->count : 0;
        }
        
        // 최근 30일 신규 구독자
        $stats['recent_30_days'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE subscribed_at >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // 이번 달 신규 구독자
        $stats['this_month'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE subscribed_at >= %s",
            date('Y-m-01 00:00:00')
        ));
        
        return $stats;
    }
    
    /**
     * 클라이언트 IP 주소 조회
     * 
     * @return string IP 주소
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * CSV 파일에서 구독자 가져오기
     * 
     * @param string $file_path CSV 파일 경로
     * @param array $options 가져오기 옵션
     * @return array 결과 정보
     */
    public function import_from_csv($file_path, $options = array()) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV 파일을 찾을 수 없습니다.');
        }
        
        $defaults = array(
            'skip_header' => true,
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'update_existing' => false
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_error', 'CSV 파일을 열 수 없습니다.');
        }
        
        $results = array(
            'total_rows' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        $row_number = 0;
        
        while (($data = fgetcsv($handle, 1000, $options['delimiter'], $options['enclosure'], $options['escape'])) !== false) {
            $row_number++;
            $results['total_rows']++;
            
            // 헤더 행 건너뛰기
            if ($row_number === 1 && $options['skip_header']) {
                continue;
            }
            
            // 최소 이메일 컬럼은 있어야 함
            if (empty($data[0])) {
                $results['errors'][] = "Row {$row_number}: 이메일이 비어있습니다.";
                $results['skipped']++;
                continue;
            }
            
            $subscriber_data = array(
                'email' => trim($data[0]),
                'first_name' => isset($data[1]) ? trim($data[1]) : '',
                'last_name' => isset($data[2]) ? trim($data[2]) : '',
                'status' => isset($data[3]) ? trim($data[3]) : self::STATUS_ACTIVE,
                'tags' => isset($data[4]) ? trim($data[4]) : '',
                'source' => 'csv_import'
            );
            
            // 기존 구독자 확인
            $existing_subscriber = $this->get_subscriber_by_email($subscriber_data['email']);
            
            if ($existing_subscriber) {
                if ($options['update_existing']) {
                    $update_result = $this->update_subscriber($existing_subscriber->id, $subscriber_data);
                    if (is_wp_error($update_result)) {
                        $results['errors'][] = "Row {$row_number}: " . $update_result->get_error_message();
                        $results['skipped']++;
                    } else {
                        $results['updated']++;
                    }
                } else {
                    $results['skipped']++;
                }
            } else {
                $create_result = $this->create_subscriber($subscriber_data);
                if (is_wp_error($create_result)) {
                    $results['errors'][] = "Row {$row_number}: " . $create_result->get_error_message();
                    $results['skipped']++;
                } else {
                    $results['imported']++;
                }
            }
        }
        
        fclose($handle);
        
        return $results;
    }
    
    /**
     * 구독자 데이터를 CSV로 내보내기
     * 
     * @param array $args 내보내기 조건
     * @return string CSV 파일 경로
     */
    public function export_to_csv($args = array()) {
        $subscribers_data = $this->get_subscribers(array_merge($args, array('per_page' => -1)));
        $subscribers = $subscribers_data['subscribers'];
        
        $upload_dir = wp_upload_dir();
        $file_name = 'subscribers_export_' . date('Y-m-d_H-i-s') . '.csv';
        $file_path = $upload_dir['path'] . '/' . $file_name;
        
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            return new WP_Error('file_error', 'CSV 파일을 생성할 수 없습니다.');
        }
        
        // 헤더 작성
        fputcsv($handle, array(
            'Email',
            'First Name',
            'Last Name',
            'Status',
            'Tags',
            'Source',
            'Subscribed At',
            'Confirmed At',
            'IP Address'
        ));
        
        // 데이터 작성
        foreach ($subscribers as $subscriber) {
            fputcsv($handle, array(
                $subscriber->email,
                $subscriber->first_name,
                $subscriber->last_name,
                $subscriber->status,
                $subscriber->tags,
                $subscriber->source,
                $subscriber->subscribed_at,
                $subscriber->confirmed_at,
                $subscriber->ip_address
            ));
        }
        
        fclose($handle);
        
        return $file_path;
    }
} 
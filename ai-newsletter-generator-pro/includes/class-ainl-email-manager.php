<?php
/**
 * SMTP 이메일 발송 시스템 관리 클래스
 * 이메일 큐 관리, 배치 발송, 속도 제한, 재시도 로직을 담당합니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMTP 이메일 발송 및 큐 관리 클래스
 */
class AINL_Email_Manager {
    
    /**
     * 클래스 인스턴스
     */
    private static $instance = null;
    
    /**
     * 데이터베이스 인스턴스
     */
    private $database;
    
    /**
     * 설정 인스턴스
     */
    private $settings;
    
    /**
     * 에러 핸들러 인스턴스
     */
    private $error_handler;
    
    /**
     * 이메일 큐 테이블명
     */
    private $queue_table;
    
    /**
     * 이메일 로그 테이블명
     */
    private $log_table;
    
    /**
     * 발송 속도 제한 (초당 이메일 수)
     */
    private $rate_limit = 5;
    
    /**
     * 배치 크기
     */
    private $batch_size = 50;
    
    /**
     * 최대 재시도 횟수
     */
    private $max_attempts = 3;
    
    /**
     * 생성자
     */
    public function __construct() {
        global $wpdb;
        
        $this->queue_table = $wpdb->prefix . 'ainl_email_queue';
        $this->log_table = $wpdb->prefix . 'ainl_email_logs';
        
        $this->init_hooks();
        $this->load_settings();
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
        // 크론 작업 등록
        add_action('init', array($this, 'schedule_email_processing'));
        add_action('ainl_process_email_queue', array($this, 'process_email_queue'));
        
        // AJAX 핸들러
        add_action('wp_ajax_ainl_test_smtp', array($this, 'ajax_test_smtp'));
        add_action('wp_ajax_ainl_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_ainl_clear_email_queue', array($this, 'ajax_clear_email_queue'));
        
        // 관리자 알림
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // 플러그인 비활성화 시 크론 정리
        register_deactivation_hook(AINL_PLUGIN_FILE, array($this, 'clear_scheduled_events'));
    }
    
    /**
     * 설정 로드
     */
    private function load_settings() {
        $settings = get_option('ainl_settings', array());
        
        // SMTP 설정
        $this->smtp_settings = array(
            'host' => isset($settings['smtp_host']) ? $settings['smtp_host'] : '',
            'port' => isset($settings['smtp_port']) ? intval($settings['smtp_port']) : 587,
            'username' => isset($settings['smtp_username']) ? $settings['smtp_username'] : '',
            'password' => isset($settings['smtp_password']) ? $settings['smtp_password'] : '',
            'encryption' => isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls',
            'from_email' => isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email'),
            'from_name' => isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name')
        );
        
        // 발송 설정
        $this->rate_limit = isset($settings['email_rate_limit']) ? intval($settings['email_rate_limit']) : 5;
        $this->batch_size = isset($settings['email_batch_size']) ? intval($settings['email_batch_size']) : 50;
        $this->max_attempts = isset($settings['email_max_attempts']) ? intval($settings['email_max_attempts']) : 3;
    }
    
    /**
     * 이메일 큐 처리 스케줄링
     */
    public function schedule_email_processing() {
        if (!wp_next_scheduled('ainl_process_email_queue')) {
            wp_schedule_event(time(), 'every_minute', 'ainl_process_email_queue');
        }
    }
    
    /**
     * 이메일을 큐에 추가
     * 
     * @param string $to_email 수신자 이메일
     * @param string $subject 제목
     * @param string $message 메시지 내용
     * @param array $headers 헤더 (선택사항)
     * @param array $attachments 첨부파일 (선택사항)
     * @param string $priority 우선순위 (high, normal, low)
     * @param datetime $scheduled_at 예약 발송 시간 (선택사항)
     * @return int|false 큐 ID 또는 false
     */
    public function add_to_queue($to_email, $subject, $message, $headers = array(), $attachments = array(), $priority = 'normal', $scheduled_at = null) {
        global $wpdb;
        
        // 이메일 유효성 검사
        if (!is_email($to_email)) {
            $this->log_error('Invalid email address: ' . $to_email);
            return false;
        }
        
        // 데이터 준비
        $queue_data = array(
            'to_email' => sanitize_email($to_email),
            'subject' => sanitize_text_field($subject),
            'message' => wp_kses_post($message),
            'headers' => wp_json_encode($headers),
            'attachments' => wp_json_encode($attachments),
            'priority' => in_array($priority, array('high', 'normal', 'low')) ? $priority : 'normal',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $this->max_attempts,
            'scheduled_at' => $scheduled_at ? $scheduled_at : current_time('mysql'),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($this->queue_table, $queue_data);
        
        if ($result === false) {
            $this->log_error('Failed to add email to queue: ' . $wpdb->last_error);
            return false;
        }
        
        $queue_id = $wpdb->insert_id;
        
        // 액션 훅 실행
        do_action('ainl_email_queued', $queue_id, $queue_data);
        
        return $queue_id;
    }
    
    /**
     * 이메일 큐 처리
     */
    public function process_email_queue() {
        global $wpdb;
        
        // 처리할 이메일 조회 (우선순위 순)
        $emails = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->queue_table} 
            WHERE status = 'pending' 
            AND scheduled_at <= %s 
            AND attempts < max_attempts
            ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'normal' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                created_at ASC
            LIMIT %d
        ", current_time('mysql'), $this->batch_size));
        
        if (empty($emails)) {
            return;
        }
        
        $processed_count = 0;
        $start_time = time();
        
        foreach ($emails as $email) {
            // 속도 제한 체크
            if ($processed_count > 0 && $processed_count % $this->rate_limit === 0) {
                $elapsed = time() - $start_time;
                if ($elapsed < 1) {
                    sleep(1 - $elapsed);
                }
                $start_time = time();
            }
            
            $this->process_single_email($email);
            $processed_count++;
        }
        
        // 통계 업데이트
        $this->update_processing_stats($processed_count);
    }
    
    /**
     * 개별 이메일 처리
     * 
     * @param object $email 이메일 큐 데이터
     */
    private function process_single_email($email) {
        global $wpdb;
        
        // 상태를 'sending'으로 변경
        $wpdb->update(
            $this->queue_table,
            array(
                'status' => 'sending',
                'last_attempt_at' => current_time('mysql'),
                'attempts' => $email->attempts + 1
            ),
            array('id' => $email->id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        
        // 이메일 발송 시도
        $send_result = $this->send_email(
            $email->to_email,
            $email->subject,
            $email->message,
            json_decode($email->headers, true),
            json_decode($email->attachments, true)
        );
        
        if ($send_result['success']) {
            // 발송 성공
            $wpdb->update(
                $this->queue_table,
                array(
                    'status' => 'sent',
                    'sent_at' => current_time('mysql')
                ),
                array('id' => $email->id),
                array('%s', '%s'),
                array('%d')
            );
            
            $this->log_email_result($email->id, 'success', $send_result['message']);
            
        } else {
            // 발송 실패
            $new_attempts = $email->attempts + 1;
            
            if ($new_attempts >= $email->max_attempts) {
                // 최대 재시도 횟수 초과
                $wpdb->update(
                    $this->queue_table,
                    array(
                        'status' => 'failed',
                        'error_message' => $send_result['message']
                    ),
                    array('id' => $email->id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                $this->log_email_result($email->id, 'failed', $send_result['message']);
                
                // 관리자에게 알림
                $this->notify_admin_of_failure($email, $send_result['message']);
                
            } else {
                // 재시도 대기
                $retry_delay = $this->calculate_retry_delay($new_attempts);
                $next_attempt = date('Y-m-d H:i:s', time() + $retry_delay);
                
                $wpdb->update(
                    $this->queue_table,
                    array(
                        'status' => 'pending',
                        'scheduled_at' => $next_attempt,
                        'error_message' => $send_result['message']
                    ),
                    array('id' => $email->id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }
        }
        
        // 액션 훅 실행
        do_action('ainl_email_processed', $email->id, $send_result);
    }
    
    /**
     * 실제 이메일 발송
     * 
     * @param string $to_email 수신자
     * @param string $subject 제목
     * @param string $message 메시지
     * @param array $headers 헤더
     * @param array $attachments 첨부파일
     * @return array 발송 결과
     */
    private function send_email($to_email, $subject, $message, $headers = array(), $attachments = array()) {
        // SMTP 설정이 있는 경우 PHPMailer 사용
        if (!empty($this->smtp_settings['host'])) {
            return $this->send_via_smtp($to_email, $subject, $message, $headers, $attachments);
        } else {
            // WordPress 기본 wp_mail 사용
            return $this->send_via_wp_mail($to_email, $subject, $message, $headers, $attachments);
        }
    }
    
    /**
     * SMTP를 통한 이메일 발송
     */
    private function send_via_smtp($to_email, $subject, $message, $headers = array(), $attachments = array()) {
        // PHPMailer 설정
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));
        
        // 헤더 준비
        $wp_headers = array();
        $wp_headers[] = 'Content-Type: text/html; charset=UTF-8';
        $wp_headers[] = 'From: ' . $this->smtp_settings['from_name'] . ' <' . $this->smtp_settings['from_email'] . '>';
        
        if (!empty($headers)) {
            $wp_headers = array_merge($wp_headers, $headers);
        }
        
        // 이메일 발송
        $result = wp_mail($to_email, $subject, $message, $wp_headers, $attachments);
        
        // PHPMailer 설정 제거
        remove_action('phpmailer_init', array($this, 'configure_phpmailer'));
        
        if ($result) {
            return array(
                'success' => true,
                'message' => 'Email sent successfully via SMTP'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to send email via SMTP'
            );
        }
    }
    
    /**
     * WordPress wp_mail을 통한 이메일 발송
     */
    private function send_via_wp_mail($to_email, $subject, $message, $headers = array(), $attachments = array()) {
        // 헤더 준비
        $wp_headers = array();
        $wp_headers[] = 'Content-Type: text/html; charset=UTF-8';
        $wp_headers[] = 'From: ' . $this->smtp_settings['from_name'] . ' <' . $this->smtp_settings['from_email'] . '>';
        
        if (!empty($headers)) {
            $wp_headers = array_merge($wp_headers, $headers);
        }
        
        // 이메일 발송
        $result = wp_mail($to_email, $subject, $message, $wp_headers, $attachments);
        
        if ($result) {
            return array(
                'success' => true,
                'message' => 'Email sent successfully via wp_mail'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to send email via wp_mail'
            );
        }
    }
    
    /**
     * PHPMailer SMTP 설정
     */
    public function configure_phpmailer($phpmailer) {
        $phpmailer->isSMTP();
        $phpmailer->Host = $this->smtp_settings['host'];
        $phpmailer->Port = $this->smtp_settings['port'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $this->smtp_settings['username'];
        $phpmailer->Password = $this->smtp_settings['password'];
        
        if ($this->smtp_settings['encryption'] === 'ssl') {
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($this->smtp_settings['encryption'] === 'tls') {
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $phpmailer->From = $this->smtp_settings['from_email'];
        $phpmailer->FromName = $this->smtp_settings['from_name'];
        
        // 디버그 모드 (개발 환경에서만)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function($str, $level) {
                error_log("SMTP Debug: $str");
            };
        }
    }
    
    /**
     * 재시도 지연 시간 계산 (지수 백오프)
     */
    private function calculate_retry_delay($attempt) {
        // 1분, 5분, 15분 순으로 지연
        $delays = array(60, 300, 900);
        $index = min($attempt - 1, count($delays) - 1);
        return $delays[$index];
    }
    
    /**
     * 이메일 발송 결과 로깅
     */
    private function log_email_result($queue_id, $status, $message = '') {
        global $wpdb;
        
        $log_data = array(
            'queue_id' => $queue_id,
            'status' => $status,
            'error_message' => $message,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'sent_at' => current_time('mysql')
        );
        
        $wpdb->insert($this->log_table, $log_data);
    }
    
    /**
     * 에러 로깅
     */
    private function log_error($message) {
        error_log('AINL Email Manager Error: ' . $message);
        
        // 관리자 알림 옵션에 추가
        $notices = get_option('ainl_admin_notices', array());
        $notices[] = array(
            'type' => 'error',
            'message' => $message,
            'timestamp' => time()
        );
        update_option('ainl_admin_notices', $notices);
    }
    
    /**
     * 관리자에게 발송 실패 알림
     */
    private function notify_admin_of_failure($email, $error_message) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = '[' . $site_name . '] 이메일 발송 실패 알림';
        $message = "
        <h3>이메일 발송 실패</h3>
        <p><strong>수신자:</strong> {$email->to_email}</p>
        <p><strong>제목:</strong> {$email->subject}</p>
        <p><strong>시도 횟수:</strong> {$email->attempts}/{$email->max_attempts}</p>
        <p><strong>오류 메시지:</strong> {$error_message}</p>
        <p><strong>시간:</strong> " . current_time('Y-m-d H:i:s') . "</p>
        ";
        
        wp_mail($admin_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * 클라이언트 IP 주소 조회
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
     * 처리 통계 업데이트
     */
    private function update_processing_stats($processed_count) {
        $stats = get_option('ainl_email_stats', array());
        $today = date('Y-m-d');
        
        if (!isset($stats[$today])) {
            $stats[$today] = array(
                'processed' => 0,
                'sent' => 0,
                'failed' => 0
            );
        }
        
        $stats[$today]['processed'] += $processed_count;
        
        // 최근 30일 데이터만 유지
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($stats as $date => $data) {
            if ($date < $cutoff_date) {
                unset($stats[$date]);
            }
        }
        
        update_option('ainl_email_stats', $stats);
    }
    
    /**
     * 큐 상태 조회
     */
    public function get_queue_status() {
        global $wpdb;
        
        $status_counts = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM {$this->queue_table} 
            GROUP BY status
        ", OBJECT_K);
        
        $stats = array(
            'pending' => isset($status_counts['pending']) ? $status_counts['pending']->count : 0,
            'sending' => isset($status_counts['sending']) ? $status_counts['sending']->count : 0,
            'sent' => isset($status_counts['sent']) ? $status_counts['sent']->count : 0,
            'failed' => isset($status_counts['failed']) ? $status_counts['failed']->count : 0
        );
        
        $stats['total'] = array_sum($stats);
        
        return $stats;
    }
    
    /**
     * 큐 정리 (오래된 항목 삭제)
     */
    public function cleanup_queue($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // 발송 완료된 오래된 항목 삭제
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->queue_table} 
            WHERE status IN ('sent', 'failed') 
            AND created_at < %s
        ", $cutoff_date));
        
        // 로그도 정리
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->log_table} 
            WHERE sent_at < %s
        ", $cutoff_date));
        
        return $deleted;
    }
    
    /**
     * SMTP 연결 테스트
     */
    public function test_smtp_connection() {
        if (empty($this->smtp_settings['host'])) {
            return array(
                'success' => false,
                'message' => 'SMTP 설정이 구성되지 않았습니다.'
            );
        }
        
        try {
            // PHPMailer를 사용한 연결 테스트
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->smtp_settings['host'];
            $mail->Port = $this->smtp_settings['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_settings['username'];
            $mail->Password = $this->smtp_settings['password'];
            
            if ($this->smtp_settings['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->smtp_settings['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // 연결 테스트
            $mail->smtpConnect();
            $mail->smtpClose();
            
            return array(
                'success' => true,
                'message' => 'SMTP 연결이 성공했습니다.'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'SMTP 연결 실패: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 테스트 이메일 발송
     */
    public function send_test_email($to_email) {
        $subject = '[' . get_bloginfo('name') . '] 테스트 이메일';
        $message = '
        <h2>이메일 발송 테스트</h2>
        <p>이 이메일은 AI Newsletter Generator Pro 플러그인의 이메일 발송 기능을 테스트하기 위해 발송되었습니다.</p>
        <p><strong>발송 시간:</strong> ' . current_time('Y-m-d H:i:s') . '</p>
        <p><strong>SMTP 설정:</strong> ' . ($this->smtp_settings['host'] ? '사용 중' : '미사용') . '</p>
        <p>이 이메일을 받으셨다면 이메일 발송 기능이 정상적으로 작동하고 있습니다.</p>
        ';
        
        return $this->send_email($to_email, $subject, $message);
    }
    
    /**
     * AJAX: SMTP 테스트
     */
    public function ajax_test_smtp() {
        check_ajax_referer('ainl_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('권한이 없습니다.');
        }
        
        $result = $this->test_smtp_connection();
        
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
        
        $result = $this->send_test_email($to_email);
        
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
        
        $deleted = $this->cleanup_queue();
        
        wp_send_json_success(array(
            'message' => $deleted . '개의 이메일 항목이 정리되었습니다.'
        ));
    }
    
    /**
     * 관리자 알림 표시
     */
    public function admin_notices() {
        $notices = get_option('ainl_admin_notices', array());
        
        foreach ($notices as $key => $notice) {
            // 24시간 이상 된 알림은 제거
            if (time() - $notice['timestamp'] > 86400) {
                unset($notices[$key]);
                continue;
            }
            
            $class = 'notice notice-' . $notice['type'] . ' is-dismissible';
            echo '<div class="' . $class . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
        
        // 알림 목록 업데이트
        update_option('ainl_admin_notices', $notices);
    }
    
    /**
     * 스케줄된 이벤트 정리
     */
    public function clear_scheduled_events() {
        wp_clear_scheduled_hook('ainl_process_email_queue');
    }
    
    /**
     * 대량 이메일 발송 (캠페인용)
     * 
     * @param array $recipients 수신자 목록
     * @param string $subject 제목
     * @param string $message 메시지
     * @param array $options 발송 옵션
     * @return array 발송 결과
     */
    public function send_bulk_emails($recipients, $subject, $message, $options = array()) {
        $defaults = array(
            'priority' => 'normal',
            'scheduled_at' => null,
            'headers' => array(),
            'attachments' => array()
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $queued_count = 0;
        $failed_count = 0;
        
        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            
            // 개인화된 메시지 생성 (필요한 경우)
            $personalized_message = $message;
            if (is_array($recipient) && isset($recipient['name'])) {
                $personalized_message = str_replace('{{name}}', $recipient['name'], $message);
            }
            
            $queue_id = $this->add_to_queue(
                $email,
                $subject,
                $personalized_message,
                $options['headers'],
                $options['attachments'],
                $options['priority'],
                $options['scheduled_at']
            );
            
            if ($queue_id) {
                $queued_count++;
            } else {
                $failed_count++;
            }
        }
        
        return array(
            'queued' => $queued_count,
            'failed' => $failed_count,
            'total' => count($recipients)
        );
    }
} 
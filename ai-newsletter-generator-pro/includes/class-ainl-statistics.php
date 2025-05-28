<?php
/**
 * AI Newsletter Generator Pro - 통계 관리 클래스
 * 이메일 발송, 오픈, 클릭 통계를 수집하고 분석하는 기능을 제공합니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 통계 관리 클래스
 */
class AINL_Statistics {
    
    /**
     * 클래스 인스턴스
     */
    private static $instance = null;
    
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
        // 이메일 추적 엔드포인트
        add_action('init', array($this, 'handle_tracking_requests'));
        
        // 관리자 페이지에 통계 메뉴 추가
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 관리자 스크립트 및 스타일 로드
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX 핸들러
        add_action('wp_ajax_ainl_get_statistics', array($this, 'ajax_get_statistics'));
        add_action('wp_ajax_ainl_export_statistics', array($this, 'ajax_export_statistics'));
        
        // 이메일 발송 후 추적 링크 추가
        add_filter('ainl_email_content_before_send', array($this, 'add_tracking_to_email'), 10, 3);
    }
    
    /**
     * 추가 통계 테이블 생성
     */
    public static function create_additional_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        self::create_campaign_logs_table();
        self::create_link_tracking_table();
        self::create_daily_stats_table();
    }
    
    /**
     * 캠페인 로그 테이블 생성 (기존 통계 테이블 보완)
     */
    private static function create_campaign_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_campaign_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            email varchar(255) NOT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            unsubscribed_at datetime DEFAULT NULL,
            bounced_at datetime DEFAULT NULL,
            open_count int(11) DEFAULT 0,
            click_count int(11) DEFAULT 0,
            last_open_at datetime DEFAULT NULL,
            last_click_at datetime DEFAULT NULL,
            device_type varchar(50) DEFAULT '',
            browser varchar(100) DEFAULT '',
            location varchar(100) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY campaign_subscriber (campaign_id, subscriber_id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY email (email),
            KEY sent_at (sent_at),
            KEY opened_at (opened_at),
            KEY clicked_at (clicked_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 링크 추적 테이블 생성
     */
    private static function create_link_tracking_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_link_tracking';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            original_url text NOT NULL,
            tracking_token varchar(64) NOT NULL,
            clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            referer text DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY tracking_token (tracking_token),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY clicked_at (clicked_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 일일 통계 요약 테이블 생성
     */
    private static function create_daily_stats_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_daily_stats';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            emails_sent int(11) DEFAULT 0,
            emails_delivered int(11) DEFAULT 0,
            emails_opened int(11) DEFAULT 0,
            emails_clicked int(11) DEFAULT 0,
            emails_bounced int(11) DEFAULT 0,
            emails_unsubscribed int(11) DEFAULT 0,
            unique_opens int(11) DEFAULT 0,
            unique_clicks int(11) DEFAULT 0,
            new_subscribers int(11) DEFAULT 0,
            total_subscribers int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date (date),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 추적 요청 처리 (픽셀 추적 및 링크 클릭)
     */
    public function handle_tracking_requests() {
        if (!isset($_GET['ainl_track'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['ainl_track']);
        
        switch ($action) {
            case 'open':
                $this->track_email_open();
                break;
                
            case 'click':
                $this->track_link_click();
                break;
        }
    }
    
    /**
     * 이메일 오픈 추적
     */
    private function track_email_open() {
        if (!isset($_GET['token'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['token']);
        
        // 토큰을 디코드하여 캠페인 ID와 구독자 ID 추출
        $data = $this->decode_tracking_token($token);
        
        if (!$data) {
            return;
        }
        
        global $wpdb;
        
        // 캠페인 로그 업데이트
        $logs_table = $wpdb->prefix . 'ainl_campaign_logs';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $logs_table WHERE campaign_id = %d AND subscriber_id = %d",
            $data['campaign_id'],
            $data['subscriber_id']
        ));
        
        if ($existing) {
            // 기존 로그 업데이트
            $wpdb->update(
                $logs_table,
                array(
                    'opened_at' => current_time('mysql'),
                    'open_count' => $existing->open_count + 1,
                    'last_open_at' => current_time('mysql'),
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => $this->get_user_agent(),
                    'device_type' => $this->detect_device_type(),
                    'browser' => $this->detect_browser()
                ),
                array(
                    'campaign_id' => $data['campaign_id'],
                    'subscriber_id' => $data['subscriber_id']
                )
            );
        }
        
        // 통계 테이블에도 기록
        $wpdb->insert(
            $wpdb->prefix . 'ainl_statistics',
            array(
                'campaign_id' => $data['campaign_id'],
                'subscriber_id' => $data['subscriber_id'],
                'action' => 'opened',
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $this->get_user_agent(),
                'timestamp' => current_time('mysql')
            )
        );
        
        // 1x1 투명 픽셀 이미지 출력
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // 1x1 투명 GIF (base64 디코드)
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    
    /**
     * 링크 클릭 추적
     */
    private function track_link_click() {
        if (!isset($_GET['token'])) {
            wp_die('Invalid tracking token');
        }
        
        $token = sanitize_text_field($_GET['token']);
        
        global $wpdb;
        
        // 링크 추적 정보 조회
        $link_table = $wpdb->prefix . 'ainl_link_tracking';
        $link_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $link_table WHERE tracking_token = %s",
            $token
        ));
        
        if (!$link_data) {
            wp_die('Invalid tracking token');
        }
        
        // 클릭 기록
        $wpdb->insert(
            $link_table,
            array(
                'campaign_id' => $link_data->campaign_id,
                'subscriber_id' => $link_data->subscriber_id,
                'original_url' => $link_data->original_url,
                'tracking_token' => $token . '_' . time(), // 고유성 보장
                'clicked_at' => current_time('mysql'),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $this->get_user_agent(),
                'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
            )
        );
        
        // 캠페인 로그 업데이트
        $logs_table = $wpdb->prefix . 'ainl_campaign_logs';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $logs_table WHERE campaign_id = %d AND subscriber_id = %d",
            $link_data->campaign_id,
            $link_data->subscriber_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $logs_table,
                array(
                    'clicked_at' => current_time('mysql'),
                    'click_count' => $existing->click_count + 1,
                    'last_click_at' => current_time('mysql')
                ),
                array(
                    'campaign_id' => $link_data->campaign_id,
                    'subscriber_id' => $link_data->subscriber_id
                )
            );
        }
        
        // 통계 테이블에도 기록
        $wpdb->insert(
            $wpdb->prefix . 'ainl_statistics',
            array(
                'campaign_id' => $link_data->campaign_id,
                'subscriber_id' => $link_data->subscriber_id,
                'action' => 'clicked',
                'action_data' => wp_json_encode(array('url' => $link_data->original_url)),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $this->get_user_agent(),
                'timestamp' => current_time('mysql')
            )
        );
        
        // 원본 URL로 리다이렉트
        wp_redirect($link_data->original_url);
        exit;
    }
    
    /**
     * 이메일에 추적 코드 추가
     */
    public function add_tracking_to_email($content, $campaign_id, $subscriber_id) {
        // 추적 픽셀 추가
        $tracking_token = $this->generate_tracking_token($campaign_id, $subscriber_id);
        $tracking_url = home_url('?ainl_track=open&token=' . $tracking_token);
        
        $tracking_pixel = '<img src="' . esc_url($tracking_url) . '" width="1" height="1" border="0" alt="" style="display:block;" />';
        
        // 이메일 하단에 추적 픽셀 추가
        $content .= $tracking_pixel;
        
        // 모든 링크를 추적 가능한 형태로 변환
        $content = $this->add_link_tracking($content, $campaign_id, $subscriber_id);
        
        return $content;
    }
    
    /**
     * 링크 추적 코드 추가
     */
    private function add_link_tracking($content, $campaign_id, $subscriber_id) {
        // HTML 링크 패턴 매칭
        $pattern = '/<a\s+(?:[^>]*?\s+)?href\s*=\s*["\'](https?:\/\/[^\s"\'<>]+)["\'][^>]*>/i';
        
        return preg_replace_callback($pattern, function($matches) use ($campaign_id, $subscriber_id) {
            $original_url = $matches[1];
            
            // 이미 추적 링크인 경우 건너뛰기
            if (strpos($original_url, '?ainl_track=click') !== false) {
                return $matches[0];
            }
            
            // 추적 토큰 생성
            $tracking_token = $this->generate_link_tracking_token($campaign_id, $subscriber_id, $original_url);
            $tracking_url = home_url('?ainl_track=click&token=' . $tracking_token);
            
            // 원본 링크를 추적 링크로 교체
            return str_replace($original_url, $tracking_url, $matches[0]);
        }, $content);
    }
    
    /**
     * 추적 토큰 생성
     */
    private function generate_tracking_token($campaign_id, $subscriber_id) {
        $data = array(
            'campaign_id' => $campaign_id,
            'subscriber_id' => $subscriber_id,
            'timestamp' => time()
        );
        
        return base64_encode(wp_json_encode($data));
    }
    
    /**
     * 링크 추적 토큰 생성
     */
    private function generate_link_tracking_token($campaign_id, $subscriber_id, $original_url) {
        global $wpdb;
        
        $token = wp_generate_password(32, false);
        
        // 링크 추적 정보 저장
        $wpdb->insert(
            $wpdb->prefix . 'ainl_link_tracking',
            array(
                'campaign_id' => $campaign_id,
                'subscriber_id' => $subscriber_id,
                'original_url' => $original_url,
                'tracking_token' => $token,
                'clicked_at' => null // 아직 클릭되지 않음
            )
        );
        
        return $token;
    }
    
    /**
     * 추적 토큰 디코드
     */
    private function decode_tracking_token($token) {
        $decoded = base64_decode($token);
        $data = json_decode($decoded, true);
        
        if (!$data || !isset($data['campaign_id']) || !isset($data['subscriber_id'])) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * 클라이언트 IP 주소 가져오기
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
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
     * User Agent 가져오기
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }
    
    /**
     * 디바이스 타입 감지
     */
    private function detect_device_type() {
        $user_agent = $this->get_user_agent();
        
        if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
            return 'mobile';
        } elseif (preg_match('/Tablet|iPad/', $user_agent)) {
            return 'tablet';
        }
        
        return 'desktop';
    }
    
    /**
     * 브라우저 감지
     */
    private function detect_browser() {
        $user_agent = $this->get_user_agent();
        
        if (strpos($user_agent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            return 'Edge';
        }
        
        return 'Unknown';
    }
    
    /**
     * 관리자 메뉴 추가
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ainl-dashboard',
            __('통계', 'ai-newsletter-generator-pro'),
            __('통계', 'ai-newsletter-generator-pro'),
            'manage_options',
            'ainl-statistics',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 관리자 스크립트 및 스타일 로드
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ainl-statistics') === false) {
            return;
        }
        
        // Chart.js 라이브러리
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );
        
        // 통계 관리자 스크립트
        wp_enqueue_script(
            'ainl-statistics-admin',
            AINL_PLUGIN_URL . 'admin/js/statistics-admin.js',
            array('jquery', 'chart-js'),
            AINL_PLUGIN_VERSION,
            true
        );
        
        // AJAX URL 및 nonce 전달
        wp_localize_script('ainl-statistics-admin', 'ainl_statistics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ainl_statistics_nonce')
        ));
        
        // 통계 관리자 스타일
        wp_enqueue_style(
            'ainl-statistics-admin',
            AINL_PLUGIN_URL . 'admin/css/statistics-admin.css',
            array(),
            AINL_PLUGIN_VERSION
        );
    }
    
    /**
     * 관리자 페이지
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('뉴스레터 통계', 'ai-newsletter-generator-pro'); ?></h1>
            
            <!-- 통계 요약 카드 -->
            <div class="ainl-stats-overview">
                <div class="ainl-stat-card">
                    <h3><?php _e('총 발송 수', 'ai-newsletter-generator-pro'); ?></h3>
                    <div class="ainl-stat-number" id="total-sent">0</div>
                </div>
                <div class="ainl-stat-card">
                    <h3><?php _e('오픈율', 'ai-newsletter-generator-pro'); ?></h3>
                    <div class="ainl-stat-number" id="open-rate">0%</div>
                </div>
                <div class="ainl-stat-card">
                    <h3><?php _e('클릭률', 'ai-newsletter-generator-pro'); ?></h3>
                    <div class="ainl-stat-number" id="click-rate">0%</div>
                </div>
                <div class="ainl-stat-card">
                    <h3><?php _e('구독 해지율', 'ai-newsletter-generator-pro'); ?></h3>
                    <div class="ainl-stat-number" id="unsubscribe-rate">0%</div>
                </div>
            </div>
            
            <!-- 차트 영역 -->
            <div class="ainl-charts-container">
                <div class="ainl-chart-section">
                    <h2><?php _e('이메일 성과 추이', 'ai-newsletter-generator-pro'); ?></h2>
                    <canvas id="performance-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="ainl-chart-section">
                    <h2><?php _e('디바이스별 오픈율', 'ai-newsletter-generator-pro'); ?></h2>
                    <canvas id="device-chart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- 캠페인별 통계 테이블 -->
            <div class="ainl-campaigns-stats">
                <h2><?php _e('캠페인별 상세 통계', 'ai-newsletter-generator-pro'); ?></h2>
                <div id="campaigns-table-container">
                    <!-- AJAX로 로드 -->
                </div>
            </div>
            
            <!-- 내보내기 버튼 -->
            <div class="ainl-export-section">
                <h2><?php _e('통계 데이터 내보내기', 'ai-newsletter-generator-pro'); ?></h2>
                <button id="export-csv" class="button"><?php _e('CSV로 내보내기', 'ai-newsletter-generator-pro'); ?></button>
                <button id="export-pdf" class="button"><?php _e('PDF로 내보내기', 'ai-newsletter-generator-pro'); ?></button>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX 통계 데이터 가져오기
     */
    public function ajax_get_statistics() {
        check_ajax_referer('ainl_statistics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('권한이 없습니다.', 'ai-newsletter-generator-pro')));
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        switch ($type) {
            case 'overview':
                $data = $this->get_overview_stats();
                break;
                
            case 'performance':
                $data = $this->get_performance_chart_data();
                break;
                
            case 'device':
                $data = $this->get_device_stats();
                break;
                
            case 'campaigns':
                $data = $this->get_campaigns_stats();
                break;
                
            default:
                wp_send_json_error(array('message' => __('잘못된 요청입니다.', 'ai-newsletter-generator-pro')));
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * 개요 통계 가져오기
     */
    private function get_overview_stats() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'ainl_campaign_logs';
        
        // 기본 통계
        $total_sent = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE sent_at IS NOT NULL");
        $total_opened = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE opened_at IS NOT NULL");
        $total_clicked = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE clicked_at IS NOT NULL");
        $total_unsubscribed = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE unsubscribed_at IS NOT NULL");
        
        $open_rate = $total_sent > 0 ? round(($total_opened / $total_sent) * 100, 2) : 0;
        $click_rate = $total_sent > 0 ? round(($total_clicked / $total_sent) * 100, 2) : 0;
        $unsubscribe_rate = $total_sent > 0 ? round(($total_unsubscribed / $total_sent) * 100, 2) : 0;
        
        return array(
            'total_sent' => $total_sent,
            'open_rate' => $open_rate,
            'click_rate' => $click_rate,
            'unsubscribe_rate' => $unsubscribe_rate
        );
    }
    
    /**
     * 성과 차트 데이터 가져오기
     */
    private function get_performance_chart_data() {
        global $wpdb;
        
        $stats_table = $wpdb->prefix . 'ainl_daily_stats';
        
        // 최근 30일 데이터
        $results = $wpdb->get_results("
            SELECT date, emails_sent, emails_opened, emails_clicked 
            FROM $stats_table 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY date ASC
        ");
        
        $labels = array();
        $sent_data = array();
        $opened_data = array();
        $clicked_data = array();
        
        foreach ($results as $row) {
            $labels[] = date('m/d', strtotime($row->date));
            $sent_data[] = (int) $row->emails_sent;
            $opened_data[] = (int) $row->emails_opened;
            $clicked_data[] = (int) $row->emails_clicked;
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => __('발송', 'ai-newsletter-generator-pro'),
                    'data' => $sent_data,
                    'borderColor' => '#007cba',
                    'backgroundColor' => 'rgba(0, 124, 186, 0.1)'
                ),
                array(
                    'label' => __('오픈', 'ai-newsletter-generator-pro'),
                    'data' => $opened_data,
                    'borderColor' => '#00a32a',
                    'backgroundColor' => 'rgba(0, 163, 42, 0.1)'
                ),
                array(
                    'label' => __('클릭', 'ai-newsletter-generator-pro'),
                    'data' => $clicked_data,
                    'borderColor' => '#d63638',
                    'backgroundColor' => 'rgba(214, 54, 56, 0.1)'
                )
            )
        );
    }
    
    /**
     * 디바이스별 통계 가져오기
     */
    private function get_device_stats() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'ainl_campaign_logs';
        
        $results = $wpdb->get_results("
            SELECT device_type, COUNT(*) as count
            FROM $logs_table 
            WHERE opened_at IS NOT NULL AND device_type != ''
            GROUP BY device_type
        ");
        
        $labels = array();
        $data = array();
        $colors = array('#007cba', '#00a32a', '#d63638', '#ffb900');
        
        foreach ($results as $index => $row) {
            $labels[] = ucfirst($row->device_type);
            $data[] = (int) $row->count;
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'data' => $data,
                    'backgroundColor' => array_slice($colors, 0, count($data))
                )
            )
        );
    }
    
    /**
     * 캠페인별 통계 가져오기
     */
    private function get_campaigns_stats() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'ainl_campaigns';
        $logs_table = $wpdb->prefix . 'ainl_campaign_logs';
        
        $results = $wpdb->get_results("
            SELECT 
                c.id,
                c.name,
                c.subject,
                c.created_at,
                COUNT(cl.id) as total_sent,
                COUNT(CASE WHEN cl.opened_at IS NOT NULL THEN 1 END) as total_opened,
                COUNT(CASE WHEN cl.clicked_at IS NOT NULL THEN 1 END) as total_clicked,
                COUNT(CASE WHEN cl.unsubscribed_at IS NOT NULL THEN 1 END) as total_unsubscribed
            FROM $campaigns_table c
            LEFT JOIN $logs_table cl ON c.id = cl.campaign_id
            WHERE c.status = 'sent'
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT 20
        ");
        
        $campaigns = array();
        
        foreach ($results as $row) {
            $open_rate = $row->total_sent > 0 ? round(($row->total_opened / $row->total_sent) * 100, 2) : 0;
            $click_rate = $row->total_sent > 0 ? round(($row->total_clicked / $row->total_sent) * 100, 2) : 0;
            
            $campaigns[] = array(
                'id' => $row->id,
                'name' => $row->name,
                'subject' => $row->subject,
                'created_at' => $row->created_at,
                'total_sent' => $row->total_sent,
                'total_opened' => $row->total_opened,
                'total_clicked' => $row->total_clicked,
                'total_unsubscribed' => $row->total_unsubscribed,
                'open_rate' => $open_rate,
                'click_rate' => $click_rate
            );
        }
        
        return $campaigns;
    }
    
    /**
     * AJAX 통계 데이터 내보내기
     */
    public function ajax_export_statistics() {
        check_ajax_referer('ainl_statistics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('권한이 없습니다.', 'ai-newsletter-generator-pro')));
        }
        
        $format = sanitize_text_field($_POST['format']);
        
        if ($format === 'csv') {
            $this->export_csv();
        } elseif ($format === 'pdf') {
            $this->export_pdf();
        }
        
        wp_send_json_error(array('message' => __('지원하지 않는 형식입니다.', 'ai-newsletter-generator-pro')));
    }
    
    /**
     * CSV 내보내기
     */
    private function export_csv() {
        $campaigns = $this->get_campaigns_stats();
        
        $filename = 'newsletter_statistics_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // 헤더
        fputcsv($output, array(
            __('캠페인 이름', 'ai-newsletter-generator-pro'),
            __('제목', 'ai-newsletter-generator-pro'),
            __('발송일', 'ai-newsletter-generator-pro'),
            __('총 발송', 'ai-newsletter-generator-pro'),
            __('총 오픈', 'ai-newsletter-generator-pro'),
            __('총 클릭', 'ai-newsletter-generator-pro'),
            __('구독 해지', 'ai-newsletter-generator-pro'),
            __('오픈율(%)', 'ai-newsletter-generator-pro'),
            __('클릭률(%)', 'ai-newsletter-generator-pro')
        ));
        
        // 데이터
        foreach ($campaigns as $campaign) {
            fputcsv($output, array(
                $campaign['name'],
                $campaign['subject'],
                $campaign['created_at'],
                $campaign['total_sent'],
                $campaign['total_opened'],
                $campaign['total_clicked'],
                $campaign['total_unsubscribed'],
                $campaign['open_rate'],
                $campaign['click_rate']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * PDF 내보내기 (기본 구현)
     */
    private function export_pdf() {
        // 간단한 HTML to PDF 변환
        $campaigns = $this->get_campaigns_stats();
        $overview = $this->get_overview_stats();
        
        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <title>뉴스레터 통계 리포트</title>
            <style>
                body { font-family: DejaVu Sans, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .overview { margin-bottom: 30px; }
                .overview div { display: inline-block; margin-right: 30px; }
            </style>
        </head>
        <body>
            <h1>뉴스레터 통계 리포트</h1>
            <p>생성일: ' . date('Y-m-d H:i:s') . '</p>
            
            <div class="overview">
                <h2>전체 개요</h2>
                <div>총 발송: ' . $overview['total_sent'] . '</div>
                <div>오픈율: ' . $overview['open_rate'] . '%</div>
                <div>클릭률: ' . $overview['click_rate'] . '%</div>
                <div>구독 해지율: ' . $overview['unsubscribe_rate'] . '%</div>
            </div>
            
            <h2>캠페인별 상세 통계</h2>
            <table>
                <thead>
                    <tr>
                        <th>캠페인 이름</th>
                        <th>총 발송</th>
                        <th>오픈율</th>
                        <th>클릭률</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($campaigns as $campaign) {
            $html .= '<tr>
                <td>' . esc_html($campaign['name']) . '</td>
                <td>' . $campaign['total_sent'] . '</td>
                <td>' . $campaign['open_rate'] . '%</td>
                <td>' . $campaign['click_rate'] . '%</td>
            </tr>';
        }
        
        $html .= '</tbody></table></body></html>';
        
        // PDF 라이브러리가 없는 경우 HTML 파일로 다운로드
        $filename = 'newsletter_statistics_' . date('Y-m-d') . '.html';
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        echo $html;
        exit;
    }
    
    /**
     * 일일 통계 업데이트 (크론 작업용)
     */
    public function update_daily_stats() {
        global $wpdb;
        
        $today = date('Y-m-d');
        $logs_table = $wpdb->prefix . 'ainl_campaign_logs';
        $stats_table = $wpdb->prefix . 'ainl_daily_stats';
        $subscribers_table = $wpdb->prefix . 'ainl_subscribers';
        
        // 오늘의 통계 계산
        $sent = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE DATE(sent_at) = '$today'");
        $opened = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE DATE(opened_at) = '$today'");
        $clicked = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE DATE(clicked_at) = '$today'");
        $unsubscribed = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE DATE(unsubscribed_at) = '$today'");
        
        $unique_opens = $wpdb->get_var("SELECT COUNT(DISTINCT subscriber_id) FROM $logs_table WHERE DATE(opened_at) = '$today'");
        $unique_clicks = $wpdb->get_var("SELECT COUNT(DISTINCT subscriber_id) FROM $logs_table WHERE DATE(clicked_at) = '$today'");
        
        $new_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM $subscribers_table WHERE DATE(created_at) = '$today'");
        $total_subscribers = $wpdb->get_var("SELECT COUNT(*) FROM $subscribers_table WHERE status = 'active'");
        
        // 기존 레코드 확인
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $stats_table WHERE date = %s",
            $today
        ));
        
        $data = array(
            'date' => $today,
            'emails_sent' => $sent,
            'emails_opened' => $opened,
            'emails_clicked' => $clicked,
            'emails_unsubscribed' => $unsubscribed,
            'unique_opens' => $unique_opens,
            'unique_clicks' => $unique_clicks,
            'new_subscribers' => $new_subscribers,
            'total_subscribers' => $total_subscribers,
            'updated_at' => current_time('mysql')
        );
        
        if ($existing) {
            $wpdb->update($stats_table, $data, array('date' => $today));
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($stats_table, $data);
        }
    }
} 
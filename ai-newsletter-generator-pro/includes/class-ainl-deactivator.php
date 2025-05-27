<?php
/**
 * 플러그인 비활성화 처리 클래스
 * 플러그인이 비활성화될 때 필요한 정리 작업을 담당합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Deactivator {
    
    /**
     * 플러그인 비활성화 시 실행되는 메인 메서드
     * 스케줄된 작업 정리, 임시 데이터 삭제 등을 수행합니다.
     */
    public static function deactivate() {
        // WordPress Cron 작업 정리
        self::clear_scheduled_events();
        
        // 임시 데이터 정리
        self::cleanup_temporary_data();
        
        // 캐시 정리
        self::clear_cache();
        
        // 비활성화 로그 기록
        self::log_deactivation();
        
        // 플러그인 상태 업데이트
        self::update_plugin_status();
    }
    
    /**
     * 스케줄된 WordPress Cron 이벤트 정리
     * 플러그인이 등록한 모든 cron 작업을 제거합니다.
     */
    private static function clear_scheduled_events() {
        // 플러그인에서 사용하는 cron 이벤트들
        $cron_events = array(
            'ainl_send_scheduled_newsletter',
            'ainl_cleanup_old_logs',
            'ainl_update_statistics',
            'ainl_process_email_queue',
        );
        
        foreach ($cron_events as $event) {
            // 스케줄된 이벤트가 있는지 확인하고 제거
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
            
            // 모든 인스턴스 제거
            wp_clear_scheduled_hook($event);
        }
    }
    
    /**
     * 임시 데이터 정리
     * 캐시, 임시 파일, 세션 데이터 등을 정리합니다.
     */
    private static function cleanup_temporary_data() {
        // WordPress Transient 캐시 정리
        $transients = array(
            'ainl_ai_response_cache',
            'ainl_post_collection_cache',
            'ainl_template_cache',
            'ainl_subscriber_stats_cache',
        );
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
        
        // 임시 파일 정리 (업로드된 CSV 파일 등)
        self::cleanup_temp_files();
        
        // 오래된 로그 데이터 정리
        self::cleanup_old_logs();
    }
    
    /**
     * 임시 파일 정리
     * 플러그인이 생성한 임시 파일들을 삭제합니다.
     */
    private static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ai-newsletter-temp/';
        
        if (is_dir($temp_dir)) {
            // 임시 디렉토리의 모든 파일 삭제
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            // 빈 디렉토리 삭제
            rmdir($temp_dir);
        }
    }
    
    /**
     * 오래된 로그 데이터 정리
     * 30일 이상 된 로그 데이터를 삭제합니다.
     */
    private static function cleanup_old_logs() {
        global $wpdb;
        
        // 30일 이전 날짜 계산
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // 오래된 통계 데이터 삭제 (테이블이 존재하는 경우)
        $table_name = $wpdb->prefix . 'ainl_statistics';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s",
                $cutoff_date
            ));
        }
        
        // 오래된 캠페인 로그 정리
        $campaigns_table = $wpdb->prefix . 'ainl_campaigns';
        if ($wpdb->get_var("SHOW TABLES LIKE '$campaigns_table'") == $campaigns_table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $campaigns_table WHERE sent_at < %s AND status = 'completed'",
                $cutoff_date
            ));
        }
    }
    
    /**
     * 캐시 정리
     * 플러그인 관련 모든 캐시를 정리합니다.
     */
    private static function clear_cache() {
        // WordPress 객체 캐시 정리
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // 플러그인 전용 캐시 정리
        wp_cache_delete_group('ainl_cache');
        
        // 옵션 캐시 정리
        wp_cache_delete('ainl_settings', 'options');
        wp_cache_delete('ainl_templates', 'options');
    }
    
    /**
     * 비활성화 로그 기록
     * 플러그인 비활성화 정보를 로그에 기록합니다.
     */
    private static function log_deactivation() {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'action' => 'plugin_deactivated',
            'version' => AINL_PLUGIN_VERSION,
            'user_id' => get_current_user_id(),
            'reason' => 'manual_deactivation', // 향후 비활성화 이유 추적 가능
        );
        
        // 비활성화 로그를 기존 로그에 추가
        $existing_logs = get_option('ainl_activation_logs', array());
        $existing_logs[] = $log_data;
        
        // 최근 10개 로그만 유지
        if (count($existing_logs) > 10) {
            $existing_logs = array_slice($existing_logs, -10);
        }
        
        update_option('ainl_activation_logs', $existing_logs);
    }
    
    /**
     * 플러그인 상태 업데이트
     * 비활성화 관련 옵션들을 업데이트합니다.
     */
    private static function update_plugin_status() {
        // 마지막 비활성화 시간 기록
        update_option('ainl_plugin_deactivated_time', current_time('timestamp'));
        
        // 활성 상태 플래그 제거는 메인 클래스에서 처리
        // delete_option('ainl_plugin_activated'); // 메인 클래스에서 처리됨
    }
    
    /**
     * 긴급 정리 모드
     * 플러그인 삭제 시 호출되는 강제 정리 메서드입니다.
     */
    public static function emergency_cleanup() {
        // 모든 스케줄된 이벤트 강제 제거
        self::clear_scheduled_events();
        
        // 모든 임시 데이터 강제 삭제
        self::cleanup_temporary_data();
        
        // 모든 캐시 강제 정리
        self::clear_cache();
        
        // 플러그인 관련 모든 옵션 삭제 (주의: 설정도 함께 삭제됨)
        // 이 부분은 uninstall 시에만 실행되어야 함
    }
    
    /**
     * 데이터 보존 모드 비활성화
     * 사용자 데이터는 보존하면서 플러그인만 비활성화합니다.
     */
    public static function soft_deactivate() {
        // 스케줄된 작업만 정리 (데이터는 보존)
        self::clear_scheduled_events();
        
        // 캐시만 정리 (설정과 데이터는 보존)
        self::clear_cache();
        
        // 비활성화 로그만 기록
        self::log_deactivation();
    }
} 
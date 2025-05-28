<?php
/**
 * AI Newsletter Generator Pro Uninstall
 * WordPress 트러블슈팅 문서 권장: 안전한 플러그인 삭제 처리
 * 
 * @package AI_Newsletter_Generator_Pro
 * @version 1.0.0
 */

// WordPress 환경 체크 (보안 강화)
if (!defined('WP_UNINSTALL_PLUGIN') || !defined('ABSPATH')) {
    error_log('AINL Plugin Uninstall: Invalid WordPress environment');
    exit;
}

// 권한 체크
if (!current_user_can('activate_plugins')) {
    error_log('AINL Plugin Uninstall: Insufficient permissions');
    exit;
}

/**
 * 안전한 플러그인 데이터 정리
 * WordPress 트러블슈팅 권장사항 적용
 */
function ainl_safe_uninstall() {
    try {
        // 메모리 및 실행 시간 제한 증가
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
            @ini_set('max_execution_time', 60);
        }
        
        // 1. WordPress 데이터베이스 정리
        ainl_cleanup_database();
        
        // 2. 크론 작업 정리
        ainl_cleanup_cron_jobs();
        
        // 3. 트랜지언트 캐시 정리
        ainl_cleanup_transients();
        
        // 4. 업로드 파일 정리
        ainl_cleanup_upload_files();
        
        // 5. 사용자 메타데이터 정리
        ainl_cleanup_user_meta();
        
        // 완료 로그
        if (function_exists('error_log')) {
            error_log('AINL Plugin: Uninstall completed successfully at ' . date('Y-m-d H:i:s'));
        }
        
    } catch (Exception $e) {
        if (function_exists('error_log')) {
            error_log('AINL Plugin Uninstall Error: ' . $e->getMessage());
        }
        
        // 최소한의 정리라도 수행
        if (function_exists('delete_option')) {
            delete_option('ainl_plugin_activated');
            update_option('ainl_uninstall_error', $e->getMessage(), false);
        }
    }
}

/**
 * 데이터베이스 옵션 정리
 */
function ainl_cleanup_database() {
    if (!function_exists('delete_option')) {
        return;
    }
    
    // 중요 옵션 먼저 삭제
    $critical_options = array(
        'ainl_plugin_activated',
        'ainl_plugin_version'
    );
    
    foreach ($critical_options as $option) {
        delete_option($option);
    }
    
    // 기타 옵션들 (오류가 발생해도 계속 진행)
    $plugin_options = array(
        'ainl_settings',
        'ainl_db_version',
        'ainl_api_keys',
        'ainl_email_settings',
        'ainl_template_settings',
        'ainl_gdpr_settings',
        'ainl_subscription_form_settings',
        'ainl_campaign_defaults',
        'ainl_activation_logs',
        'ainl_admin_notice_welcome',
        'ainl_admin_notice_api_key_missing',
        'ainl_admin_notice_database_update',
        'ainl_admin_notice_dismissed'
    );
    
    foreach ($plugin_options as $option) {
        try {
            delete_option($option);
        } catch (Exception $e) {
            // 개별 옵션 삭제 실패는 전체 삭제를 중단하지 않음
            continue;
        }
    }
}

/**
 * 크론 작업 정리
 */
function ainl_cleanup_cron_jobs() {
    if (!function_exists('wp_clear_scheduled_hook')) {
        return;
    }
    
    $cron_jobs = array(
        'ainl_newsletter_cron',
        'ainl_cleanup_cron',
        'ainl_stats_update_cron',
        'ainl_email_queue_cron',
        'ainl_backup_cron',
        'ainl_maintenance_cron',
        'ainl_subscriber_cleanup_cron'
    );
    
    foreach ($cron_jobs as $job) {
        try {
            wp_clear_scheduled_hook($job);
        } catch (Exception $e) {
            // 크론 정리 실패는 치명적이지 않음
            continue;
        }
    }
}

/**
 * 트랜지언트 캐시 정리
 */
function ainl_cleanup_transients() {
    if (!function_exists('delete_transient')) {
        return;
    }
    
    $transients = array(
        'ainl_post_cache',
        'ainl_stats_cache',
        'ainl_email_queue_cache',
        'ainl_subscriber_count_cache',
        'ainl_template_cache',
        'ainl_campaign_cache',
        'ainl_api_status_cache'
    );
    
    foreach ($transients as $transient) {
        try {
            delete_transient($transient);
        } catch (Exception $e) {
            // 트랜지언트 삭제 실패는 치명적이지 않음
            continue;
        }
    }
}

/**
 * 업로드 파일 정리 (안전하게)
 */
function ainl_cleanup_upload_files() {
    if (!function_exists('wp_upload_dir')) {
        return;
    }
    
    $upload_dir = wp_upload_dir();
    if (!isset($upload_dir['basedir'])) {
        return;
    }
    
    $plugin_upload_dir = $upload_dir['basedir'] . '/ai-newsletter-files/';
    
    if (is_dir($plugin_upload_dir) && is_writable($plugin_upload_dir)) {
        try {
            ainl_safe_rmdir($plugin_upload_dir);
        } catch (Exception $e) {
            if (function_exists('error_log')) {
                error_log('AINL Plugin Uninstall - File cleanup failed: ' . $e->getMessage());
            }
        }
    }
}

/**
 * 사용자 메타데이터 정리
 */
function ainl_cleanup_user_meta() {
    if (!function_exists('delete_user_meta')) {
        return;
    }
    
    $user_meta_keys = array(
        'ainl_last_newsletter_sent',
        'ainl_email_preferences',
        'ainl_subscription_status',
        'ainl_admin_notices_dismissed'
    );
    
    foreach ($user_meta_keys as $meta_key) {
        try {
            // 모든 사용자에서 해당 메타 키 삭제
            global $wpdb;
            if (isset($wpdb)) {
                $wpdb->delete(
                    $wpdb->usermeta,
                    array('meta_key' => $meta_key),
                    array('%s')
                );
            }
        } catch (Exception $e) {
            // 사용자 메타 삭제 실패는 치명적이지 않음
            continue;
        }
    }
}

/**
 * 안전한 디렉토리 삭제
 */
function ainl_safe_rmdir($dir) {
    if (!is_dir($dir) || !is_readable($dir) || !is_writable($dir)) {
        return false;
    }
    
    // 보안을 위한 경로 체크
    $upload_dir = wp_upload_dir();
    if (strpos($dir, $upload_dir['basedir']) !== 0) {
        error_log('AINL Plugin Uninstall: Invalid directory path for deletion');
        return false;
    }
    
    $files = scandir($dir);
    if ($files === false) {
        return false;
    }
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.htaccess') {
            continue;
        }
        
        $file_path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($file_path)) {
            ainl_safe_rmdir($file_path);
        } elseif (is_file($file_path) && is_writable($file_path)) {
            @unlink($file_path);
        }
    }
    
    return @rmdir($dir);
}

// 안전한 삭제 실행
ainl_safe_uninstall(); 
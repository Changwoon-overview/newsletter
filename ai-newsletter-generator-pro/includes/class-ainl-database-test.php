<?php
/**
 * 데이터베이스 테스트 클래스
 * 플러그인의 데이터베이스 테이블이 올바르게 생성되었는지 확인합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Database_Test {
    
    /**
     * 모든 테이블이 존재하는지 확인
     */
    public static function verify_tables() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'ainl_subscribers',
            $wpdb->prefix . 'ainl_categories',
            $wpdb->prefix . 'ainl_subscriber_categories',
            $wpdb->prefix . 'ainl_templates',
            $wpdb->prefix . 'ainl_campaigns',
            $wpdb->prefix . 'ainl_statistics'
        );
        
        $results = array();
        
        foreach ($required_tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            $results[$table] = $table_exists;
        }
        
        return $results;
    }
    
    /**
     * 테이블 구조 확인
     */
    public static function verify_table_structure($table_name) {
        global $wpdb;
        
        $full_table_name = $wpdb->prefix . $table_name;
        $columns = $wpdb->get_results("DESCRIBE $full_table_name");
        
        return $columns;
    }
    
    /**
     * 기본 데이터 확인
     */
    public static function verify_initial_data() {
        global $wpdb;
        
        $results = array();
        
        // 기본 카테고리 확인
        $categories_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ainl_categories");
        $results['categories_count'] = $categories_count;
        
        // 기본 템플릿 확인
        $templates_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ainl_templates");
        $results['templates_count'] = $templates_count;
        
        return $results;
    }
    
    /**
     * 데이터베이스 상태 리포트 생성
     */
    public static function generate_status_report() {
        $report = array();
        
        // 테이블 존재 여부 확인
        $report['tables'] = self::verify_tables();
        
        // 기본 데이터 확인
        $report['initial_data'] = self::verify_initial_data();
        
        // 데이터베이스 버전 확인
        $report['db_version'] = get_option('ainl_db_version', 'Not set');
        
        return $report;
    }
    
    /**
     * 테스트 데이터 삽입
     */
    public static function insert_test_data() {
        global $wpdb;
        
        // 테스트 구독자 추가
        $test_subscriber = array(
            'email' => 'test@example.com',
            'name' => '테스트 사용자',
            'status' => 'active',
            'source' => 'test'
        );
        
        $wpdb->insert($wpdb->prefix . 'ainl_subscribers', $test_subscriber);
        
        return $wpdb->insert_id;
    }
    
    /**
     * 테스트 데이터 정리
     */
    public static function cleanup_test_data() {
        global $wpdb;
        
        // 테스트 구독자 삭제
        $wpdb->delete(
            $wpdb->prefix . 'ainl_subscribers',
            array('email' => 'test@example.com'),
            array('%s')
        );
    }
} 
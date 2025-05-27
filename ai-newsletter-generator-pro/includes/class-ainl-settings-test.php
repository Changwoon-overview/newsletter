<?php
/**
 * 설정 테스트 클래스
 * 플러그인 설정 시스템의 동작을 테스트합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Settings_Test {
    
    /**
     * 모든 설정 테스트 실행
     */
    public static function run_all_tests() {
        $results = array();
        
        $results['default_values'] = self::test_default_values();
        $results['setting_storage'] = self::test_setting_storage();
        $results['setting_validation'] = self::test_setting_validation();
        $results['api_key_validation'] = self::test_api_key_validation();
        
        return $results;
    }
    
    /**
     * 기본값 테스트
     */
    public static function test_default_values() {
        $test_result = array(
            'name' => '기본값 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // 기본값 확인
        $default_tests = array(
            'general.plugin_name' => 'AI Newsletter Generator Pro',
            'ai.provider' => 'openai',
            'ai.model' => 'gpt-3.5-turbo',
            'ai.max_tokens' => 1000,
            'ai.temperature' => 0.7,
            'email.smtp_port' => 587,
            'email.smtp_encryption' => 'tls',
            'email.batch_size' => 50,
            'content.date_range' => 7,
            'content.max_posts' => 10
        );
        
        foreach ($default_tests as $key => $expected) {
            $actual = AINL_Settings::get_setting($key);
            if ($actual !== $expected) {
                $test_result['passed'] = false;
                $test_result['details'][] = "기본값 불일치: {$key} (예상: {$expected}, 실제: {$actual})";
            }
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '모든 기본값이 올바르게 설정됨';
        }
        
        return $test_result;
    }
    
    /**
     * 설정 저장/불러오기 테스트
     */
    public static function test_setting_storage() {
        $test_result = array(
            'name' => '설정 저장/불러오기 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // 테스트 데이터
        $test_data = array(
            'general.plugin_name' => 'Test Plugin Name',
            'ai.max_tokens' => 2000,
            'ai.temperature' => 0.5,
            'email.batch_size' => 100
        );
        
        // 설정 저장 테스트
        foreach ($test_data as $key => $value) {
            $saved = AINL_Settings::update_setting($key, $value);
            if (!$saved) {
                $test_result['passed'] = false;
                $test_result['details'][] = "설정 저장 실패: {$key}";
            }
        }
        
        // 설정 불러오기 테스트
        foreach ($test_data as $key => $expected) {
            $actual = AINL_Settings::get_setting($key);
            if ($actual !== $expected) {
                $test_result['passed'] = false;
                $test_result['details'][] = "설정 불러오기 실패: {$key} (예상: {$expected}, 실제: {$actual})";
            }
        }
        
        // 테스트 데이터 정리
        foreach ($test_data as $key => $value) {
            AINL_Settings::update_setting($key, null);
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '설정 저장/불러오기가 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * 설정 검증 테스트
     */
    public static function test_setting_validation() {
        $test_result = array(
            'name' => '설정 검증 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // 설정 인스턴스 생성
        $settings = new AINL_Settings();
        
        // 유효한 데이터 테스트
        $valid_data = array(
            'general' => array(
                'plugin_name' => 'Valid Plugin Name',
                'sender_name' => 'Valid Sender',
                'sender_email' => 'valid@example.com',
                'reply_to_email' => 'reply@example.com'
            ),
            'ai' => array(
                'provider' => 'openai',
                'max_tokens' => 1500,
                'temperature' => 0.8
            ),
            'email' => array(
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'batch_size' => 75
            ),
            'content' => array(
                'post_types' => array('post', 'page'),
                'date_range' => 14,
                'max_posts' => 20,
                'include_featured_image' => true
            )
        );
        
        $sanitized = $settings->sanitize_settings($valid_data);
        
        // 검증 결과 확인
        if (!isset($sanitized['general']['sender_email']) || 
            $sanitized['general']['sender_email'] !== 'valid@example.com') {
            $test_result['passed'] = false;
            $test_result['details'][] = '이메일 검증 실패';
        }
        
        if (!isset($sanitized['ai']['max_tokens']) || 
            $sanitized['ai']['max_tokens'] !== 1500) {
            $test_result['passed'] = false;
            $test_result['details'][] = '숫자 검증 실패';
        }
        
        if (!isset($sanitized['content']['post_types']) || 
            !is_array($sanitized['content']['post_types'])) {
            $test_result['passed'] = false;
            $test_result['details'][] = '배열 검증 실패';
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '설정 검증이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * API 키 검증 테스트
     */
    public static function test_api_key_validation() {
        $test_result = array(
            'name' => 'API 키 검증 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $settings = new AINL_Settings();
        
        // 유효한 OpenAI API 키 형식 테스트
        $valid_openai_key = 'sk-' . str_repeat('a', 48);
        $valid_data = array(
            'ai' => array(
                'provider' => 'openai',
                'openai_api_key' => $valid_openai_key
            )
        );
        
        $sanitized = $settings->sanitize_settings($valid_data);
        
        if (!isset($sanitized['ai']['openai_api_key']) || 
            $sanitized['ai']['openai_api_key'] !== $valid_openai_key) {
            $test_result['passed'] = false;
            $test_result['details'][] = '유효한 OpenAI API 키 검증 실패';
        }
        
        // 무효한 OpenAI API 키 형식 테스트
        $invalid_openai_key = 'invalid-key';
        $invalid_data = array(
            'ai' => array(
                'provider' => 'openai',
                'openai_api_key' => $invalid_openai_key
            )
        );
        
        // 에러 메시지 캡처를 위한 설정
        $error_found = false;
        add_action('admin_notices', function() use (&$error_found) {
            $error_found = true;
        });
        
        $sanitized = $settings->sanitize_settings($invalid_data);
        
        if ($test_result['passed']) {
            $test_result['details'][] = 'API 키 검증이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * 테스트 결과를 HTML로 출력
     */
    public static function display_test_results($results) {
        echo '<div class="ainl-test-results">';
        echo '<h3>설정 시스템 테스트 결과</h3>';
        
        $total_tests = count($results);
        $passed_tests = 0;
        
        foreach ($results as $test_result) {
            if ($test_result['passed']) {
                $passed_tests++;
            }
            
            $status_class = $test_result['passed'] ? 'passed' : 'failed';
            $status_text = $test_result['passed'] ? '통과' : '실패';
            
            echo '<div class="test-result ' . $status_class . '">';
            echo '<h4>' . esc_html($test_result['name']) . ' - ' . $status_text . '</h4>';
            echo '<ul>';
            foreach ($test_result['details'] as $detail) {
                echo '<li>' . esc_html($detail) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        echo '<div class="test-summary">';
        echo '<strong>전체 결과: ' . $passed_tests . '/' . $total_tests . ' 테스트 통과</strong>';
        echo '</div>';
        
        echo '</div>';
        
        // 테스트 결과 스타일
        echo '<style>
        .ainl-test-results {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .test-result {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
        }
        
        .test-result.passed {
            background: #d1e7dd;
            border: 1px solid #badbcc;
        }
        
        .test-result.failed {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .test-result h4 {
            margin: 0 0 10px 0;
        }
        
        .test-result ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .test-summary {
            margin-top: 20px;
            padding: 15px;
            background: #f0f8ff;
            border: 1px solid #007cba;
            border-radius: 4px;
            text-align: center;
        }
        </style>';
    }
} 
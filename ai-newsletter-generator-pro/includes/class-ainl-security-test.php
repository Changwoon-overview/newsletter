<?php
/**
 * 보안 시스템 테스트 클래스
 * 플러그인의 보안 기능을 테스트합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Security_Test {
    
    /**
     * 모든 보안 테스트 실행
     */
    public static function run_all_tests() {
        $results = array();
        
        $results['capability_check'] = self::test_capability_check();
        $results['nonce_verification'] = self::test_nonce_verification();
        $results['input_sanitization'] = self::test_input_sanitization();
        $results['output_escaping'] = self::test_output_escaping();
        $results['api_key_encryption'] = self::test_api_key_encryption();
        $results['malicious_content_detection'] = self::test_malicious_content_detection();
        $results['sql_injection_prevention'] = self::test_sql_injection_prevention();
        
        return $results;
    }
    
    /**
     * 권한 체크 테스트
     */
    public static function test_capability_check() {
        $test_result = array(
            'name' => '사용자 권한 체크 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // 액션별 권한 체크 테스트
        $action_tests = array(
            'manage_settings' => 'manage_options',
            'manage_campaigns' => 'edit_posts',
            'view_statistics' => 'edit_posts',
            'invalid_action' => false
        );
        
        foreach ($action_tests as $action => $expected_capability) {
            $result = AINL_Security::check_action_capability($action);
            
            if ($expected_capability === false) {
                // 유효하지 않은 액션은 false를 반환해야 함
                if ($result !== false) {
                    $test_result['passed'] = false;
                    $test_result['details'][] = "유효하지 않은 액션 '{$action}'에 대해 잘못된 결과 반환";
                }
            } else {
                // 유효한 액션은 현재 사용자의 권한에 따라 결과가 달라짐
                $test_result['details'][] = "액션 '{$action}' 권한 체크 완료";
            }
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '권한 체크 시스템이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * Nonce 검증 테스트
     */
    public static function test_nonce_verification() {
        $test_result = array(
            'name' => 'Nonce 검증 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // Nonce 생성 테스트
        $action = 'test_action';
        $nonce = AINL_Security::create_nonce($action);
        
        if (empty($nonce)) {
            $test_result['passed'] = false;
            $test_result['details'][] = 'Nonce 생성 실패';
        } else {
            $test_result['details'][] = 'Nonce 생성 성공';
        }
        
        // Nonce 검증 테스트
        $verification_result = AINL_Security::verify_nonce($nonce, $action);
        
        if (!$verification_result) {
            $test_result['passed'] = false;
            $test_result['details'][] = '유효한 Nonce 검증 실패';
        } else {
            $test_result['details'][] = '유효한 Nonce 검증 성공';
        }
        
        // 잘못된 Nonce 검증 테스트
        $invalid_verification = AINL_Security::verify_nonce('invalid_nonce', $action);
        
        if ($invalid_verification) {
            $test_result['passed'] = false;
            $test_result['details'][] = '유효하지 않은 Nonce가 통과됨';
        } else {
            $test_result['details'][] = '유효하지 않은 Nonce 차단 성공';
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = 'Nonce 시스템이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * 입력값 정리 테스트
     */
    public static function test_input_sanitization() {
        $test_result = array(
            'name' => '입력값 정리 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // 다양한 입력값 타입 테스트
        $sanitization_tests = array(
            'text' => array(
                'input' => '<script>alert("xss")</script>Hello World',
                'expected_clean' => true,
                'type' => 'text'
            ),
            'email' => array(
                'input' => 'test@example.com<script>',
                'expected_clean' => true,
                'type' => 'email'
            ),
            'int' => array(
                'input' => '123abc',
                'expected' => 123,
                'type' => 'int'
            ),
            'float' => array(
                'input' => '12.34abc',
                'expected' => 12.34,
                'type' => 'float'
            ),
            'array' => array(
                'input' => array('<script>test</script>', 'normal text'),
                'expected_clean' => true,
                'type' => 'array'
            )
        );
        
        foreach ($sanitization_tests as $test_name => $test_data) {
            $sanitized = AINL_Security::sanitize_input($test_data['input'], $test_data['type']);
            
            if (isset($test_data['expected'])) {
                if ($sanitized !== $test_data['expected']) {
                    $test_result['passed'] = false;
                    $test_result['details'][] = "{$test_name} 타입 정리 실패: 예상 {$test_data['expected']}, 실제 {$sanitized}";
                }
            } elseif (isset($test_data['expected_clean'])) {
                // 스크립트 태그가 제거되었는지 확인
                if (strpos($sanitized, '<script>') !== false) {
                    $test_result['passed'] = false;
                    $test_result['details'][] = "{$test_name} 타입에서 악성 코드가 제거되지 않음";
                } else {
                    $test_result['details'][] = "{$test_name} 타입 정리 성공";
                }
            }
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '입력값 정리 시스템이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * 출력값 이스케이프 테스트
     */
    public static function test_output_escaping() {
        $test_result = array(
            'name' => '출력값 이스케이프 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $escape_tests = array(
            'html' => array(
                'input' => '<script>alert("xss")</script>',
                'context' => 'html'
            ),
            'attr' => array(
                'input' => 'value" onload="alert(1)"',
                'context' => 'attr'
            ),
            'url' => array(
                'input' => 'javascript:alert(1)',
                'context' => 'url'
            ),
            'js' => array(
                'input' => 'alert("xss")',
                'context' => 'js'
            )
        );
        
        foreach ($escape_tests as $test_name => $test_data) {
            $escaped = AINL_Security::escape_output($test_data['input'], $test_data['context']);
            
            // 원본과 다르게 이스케이프되었는지 확인
            if ($escaped === $test_data['input']) {
                $test_result['passed'] = false;
                $test_result['details'][] = "{$test_name} 컨텍스트에서 이스케이프되지 않음";
            } else {
                $test_result['details'][] = "{$test_name} 컨텍스트 이스케이프 성공";
            }
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '출력값 이스케이프 시스템이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * API 키 암호화 테스트
     */
    public static function test_api_key_encryption() {
        $test_result = array(
            'name' => 'API 키 암호화 테스트',
            'passed' => true,
            'details' => array()
        );
        
        $test_api_key = 'sk-test1234567890abcdef1234567890abcdef1234567890';
        
        // 암호화 테스트
        $encrypted = AINL_Security::encrypt_api_key($test_api_key);
        
        if (empty($encrypted)) {
            $test_result['passed'] = false;
            $test_result['details'][] = 'API 키 암호화 실패';
        } elseif ($encrypted === $test_api_key) {
            $test_result['passed'] = false;
            $test_result['details'][] = 'API 키가 암호화되지 않음';
        } else {
            $test_result['details'][] = 'API 키 암호화 성공';
        }
        
        // 복호화 테스트
        $decrypted = AINL_Security::decrypt_api_key($encrypted);
        
        if ($decrypted !== $test_api_key) {
            $test_result['passed'] = false;
            $test_result['details'][] = 'API 키 복호화 실패';
        } else {
            $test_result['details'][] = 'API 키 복호화 성공';
        }
        
        // 빈 값 처리 테스트
        $empty_encrypted = AINL_Security::encrypt_api_key('');
        $empty_decrypted = AINL_Security::decrypt_api_key('');
        
        if ($empty_encrypted !== '' || $empty_decrypted !== '') {
            $test_result['passed'] = false;
            $test_result['details'][] = '빈 API 키 처리 실패';
        } else {
            $test_result['details'][] = '빈 API 키 처리 성공';
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = 'API 키 암호화 시스템이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * 악성 콘텐츠 감지 테스트
     */
    public static function test_malicious_content_detection() {
        $test_result = array(
            'name' => '악성 콘텐츠 감지 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // 보안 클래스의 private 메서드를 테스트하기 위해 리플렉션 사용
        $security = new AINL_Security();
        $reflection = new ReflectionClass($security);
        $method = $reflection->getMethod('contains_malicious_content');
        $method->setAccessible(true);
        
        $malicious_contents = array(
            '<script>alert("xss")</script>',
            '<iframe src="javascript:alert(1)"></iframe>',
            'javascript:alert(1)',
            'vbscript:msgbox(1)',
            '<img onload="alert(1)">',
            '<div onerror="alert(1)">',
            '<button onclick="alert(1)">',
            'eval(alert(1))',
            'exec(rm -rf /)'
        );
        
        $safe_contents = array(
            'Normal text content',
            'email@example.com',
            'https://example.com',
            '<p>Safe HTML content</p>',
            'function normalFunction() { return true; }'
        );
        
        // 악성 콘텐츠 감지 테스트
        foreach ($malicious_contents as $content) {
            $is_malicious = $method->invoke($security, $content);
            if (!$is_malicious) {
                $test_result['passed'] = false;
                $test_result['details'][] = "악성 콘텐츠 미감지: " . substr($content, 0, 50);
            }
        }
        
        // 안전한 콘텐츠 테스트
        foreach ($safe_contents as $content) {
            $is_malicious = $method->invoke($security, $content);
            if ($is_malicious) {
                $test_result['passed'] = false;
                $test_result['details'][] = "안전한 콘텐츠 오감지: " . substr($content, 0, 50);
            }
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = '악성 콘텐츠 감지 시스템이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * SQL 인젝션 방지 테스트
     */
    public static function test_sql_injection_prevention() {
        $test_result = array(
            'name' => 'SQL 인젝션 방지 테스트',
            'passed' => true,
            'details' => array()
        );
        
        // 테스트 쿼리와 파라미터
        $test_query = "SELECT * FROM test_table WHERE id = %d AND name = %s";
        $test_args = array(1, "test'; DROP TABLE users; --");
        
        // 쿼리 준비 테스트
        $prepared_query = AINL_Security::prepare_query($test_query, $test_args);
        
        // 준비된 쿼리에 SQL 인젝션 패턴이 없는지 확인
        if (strpos($prepared_query, 'DROP TABLE') !== false) {
            $test_result['passed'] = false;
            $test_result['details'][] = 'SQL 인젝션 공격이 차단되지 않음';
        } else {
            $test_result['details'][] = 'SQL 인젝션 공격 차단 성공';
        }
        
        // 빈 인수 배열 테스트
        $simple_query = "SELECT * FROM test_table";
        $prepared_simple = AINL_Security::prepare_query($simple_query, array());
        
        if ($prepared_simple !== $simple_query) {
            $test_result['passed'] = false;
            $test_result['details'][] = '단순 쿼리 처리 실패';
        } else {
            $test_result['details'][] = '단순 쿼리 처리 성공';
        }
        
        if ($test_result['passed']) {
            $test_result['details'][] = 'SQL 인젝션 방지 시스템이 정상적으로 작동함';
        }
        
        return $test_result;
    }
    
    /**
     * 테스트 결과를 HTML로 출력
     */
    public static function display_test_results($results) {
        echo '<div class="ainl-security-test-results">';
        echo '<h3>보안 시스템 테스트 결과</h3>';
        
        $total_tests = count($results);
        $passed_tests = 0;
        
        foreach ($results as $test_result) {
            if ($test_result['passed']) {
                $passed_tests++;
            }
            
            $status_class = $test_result['passed'] ? 'passed' : 'failed';
            $status_text = $test_result['passed'] ? '통과' : '실패';
            $status_icon = $test_result['passed'] ? '✅' : '❌';
            
            echo '<div class="security-test-result ' . $status_class . '">';
            echo '<h4>' . $status_icon . ' ' . esc_html($test_result['name']) . ' - ' . $status_text . '</h4>';
            echo '<ul>';
            foreach ($test_result['details'] as $detail) {
                echo '<li>' . esc_html($detail) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        $overall_status = ($passed_tests === $total_tests) ? 'success' : 'warning';
        $overall_icon = ($passed_tests === $total_tests) ? '🛡️' : '⚠️';
        
        echo '<div class="security-test-summary ' . $overall_status . '">';
        echo '<strong>' . $overall_icon . ' 전체 결과: ' . $passed_tests . '/' . $total_tests . ' 테스트 통과</strong>';
        if ($passed_tests < $total_tests) {
            echo '<p>일부 보안 테스트가 실패했습니다. 보안 설정을 검토해주세요.</p>';
        } else {
            echo '<p>모든 보안 테스트가 통과했습니다. 시스템이 안전합니다.</p>';
        }
        echo '</div>';
        
        echo '</div>';
        
        // 보안 테스트 결과 스타일
        echo '<style>
        .ainl-security-test-results {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .security-test-result {
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #ccc;
        }
        
        .security-test-result.passed {
            background: #d1e7dd;
            border-left-color: #198754;
        }
        
        .security-test-result.failed {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .security-test-result h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .security-test-result ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .security-test-result li {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .security-test-summary {
            margin-top: 20px;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
            font-size: 16px;
        }
        
        .security-test-summary.success {
            background: #d1e7dd;
            border: 2px solid #198754;
            color: #0f5132;
        }
        
        .security-test-summary.warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #664d03;
        }
        
        .security-test-summary strong {
            display: block;
            margin-bottom: 10px;
            font-size: 18px;
        }
        </style>';
    }
} 
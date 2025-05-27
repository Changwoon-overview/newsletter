<?php
/**
 * API 모니터링 시스템 테스트 클래스
 * API 모니터링 및 테스트 시스템의 기능을 검증합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_API_Monitor_Test {
    
    /**
     * API 모니터
     */
    private $api_monitor;
    
    /**
     * 테스트 결과
     */
    private $test_results;
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->api_monitor = new AINL_API_Monitor();
        $this->test_results = array();
    }
    
    /**
     * 모든 테스트 실행
     * 
     * @return array 테스트 결과
     */
    public function run_all_tests() {
        $start_time = microtime(true);
        
        $this->test_results = array(
            'timestamp' => current_time('mysql'),
            'tests' => array(),
            'summary' => array()
        );
        
        // 1. 모니터링 설정 테스트
        $this->test_results['tests']['monitor_settings'] = $this->test_monitor_settings();
        
        // 2. 사용량 제한 테스트
        $this->test_results['tests']['usage_limits'] = $this->test_usage_limits();
        
        // 3. 알림 시스템 테스트
        $this->test_results['tests']['notification_system'] = $this->test_notification_system();
        
        // 4. 실시간 사용량 모니터링 테스트
        $this->test_results['tests']['real_time_monitoring'] = $this->test_real_time_monitoring();
        
        // 5. API 요청 추적 테스트
        $this->test_results['tests']['api_request_tracking'] = $this->test_api_request_tracking();
        
        // 6. 비용 계산 테스트
        $this->test_results['tests']['cost_calculation'] = $this->test_cost_calculation();
        
        // 7. 경고 시스템 테스트
        $this->test_results['tests']['warning_system'] = $this->test_warning_system();
        
        // 8. 로그 시스템 테스트
        $this->test_results['tests']['log_system'] = $this->test_log_system();
        
        // 9. 데이터 정리 테스트
        $this->test_results['tests']['data_cleanup'] = $this->test_data_cleanup();
        
        // 10. 종합 시스템 테스트
        $this->test_results['tests']['comprehensive_test'] = $this->test_comprehensive_system();
        
        $end_time = microtime(true);
        $execution_time = round(($end_time - $start_time) * 1000, 2);
        
        // 결과 요약
        $this->test_results['summary'] = $this->generate_summary($execution_time);
        
        return $this->test_results;
    }
    
    /**
     * 모니터링 설정 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_monitor_settings() {
        try {
            $settings = $this->api_monitor->get_monitor_settings();
            
            $required_keys = array(
                'monitoring_enabled', 'real_time_tracking', 'cost_tracking',
                'performance_tracking', 'error_tracking', 'daily_reports',
                'weekly_reports', 'alert_thresholds', 'data_retention_days'
            );
            
            $missing_keys = array();
            foreach ($required_keys as $key) {
                if (!isset($settings[$key])) {
                    $missing_keys[] = $key;
                }
            }
            
            // 설정 업데이트 테스트
            $test_settings = array('monitoring_enabled' => false);
            $update_success = $this->api_monitor->update_monitor_settings($test_settings);
            
            // 원래 설정으로 복원
            $this->api_monitor->update_monitor_settings(array('monitoring_enabled' => true));
            
            return array(
                'status' => empty($missing_keys) && $update_success ? 'PASS' : 'FAIL',
                'message' => empty($missing_keys) ? '모니터링 설정 테스트 통과' : '필수 설정 키 누락: ' . implode(', ', $missing_keys),
                'details' => array(
                    'settings_count' => count($settings),
                    'missing_keys' => $missing_keys,
                    'update_success' => $update_success
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '모니터링 설정 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 사용량 제한 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_usage_limits() {
        try {
            $limits = $this->api_monitor->get_usage_limits();
            
            $required_limits = array(
                'daily_requests', 'daily_tokens', 'daily_cost',
                'monthly_requests', 'monthly_tokens', 'monthly_cost',
                'rate_limit_per_minute', 'max_tokens_per_request', 'emergency_stop_cost'
            );
            
            $missing_limits = array();
            $invalid_limits = array();
            
            foreach ($required_limits as $limit) {
                if (!isset($limits[$limit])) {
                    $missing_limits[] = $limit;
                } elseif ($limits[$limit] <= 0) {
                    $invalid_limits[] = $limit;
                }
            }
            
            // 제한 업데이트 테스트
            $test_limits = array('daily_cost' => 50.0);
            $update_success = $this->api_monitor->update_usage_limits($test_limits);
            
            return array(
                'status' => empty($missing_limits) && empty($invalid_limits) && $update_success ? 'PASS' : 'FAIL',
                'message' => empty($missing_limits) && empty($invalid_limits) ? '사용량 제한 테스트 통과' : '사용량 제한 설정 문제 발견',
                'details' => array(
                    'limits_count' => count($limits),
                    'missing_limits' => $missing_limits,
                    'invalid_limits' => $invalid_limits,
                    'update_success' => $update_success
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '사용량 제한 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 알림 시스템 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_notification_system() {
        try {
            $notifications = $this->api_monitor->get_notification_settings();
            
            $required_keys = array(
                'email_notifications', 'admin_notifications',
                'slack_webhook', 'discord_webhook', 'notification_levels'
            );
            
            $missing_keys = array();
            foreach ($required_keys as $key) {
                if (!isset($notifications[$key])) {
                    $missing_keys[] = $key;
                }
            }
            
            // 알림 설정 업데이트 테스트
            $test_notifications = array('email_notifications' => false);
            $update_success = $this->api_monitor->update_notification_settings($test_notifications);
            
            // 원래 설정으로 복원
            $this->api_monitor->update_notification_settings(array('email_notifications' => true));
            
            return array(
                'status' => empty($missing_keys) && $update_success ? 'PASS' : 'FAIL',
                'message' => empty($missing_keys) ? '알림 시스템 테스트 통과' : '알림 설정 키 누락',
                'details' => array(
                    'notification_count' => count($notifications),
                    'missing_keys' => $missing_keys,
                    'update_success' => $update_success
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '알림 시스템 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 실시간 사용량 모니터링 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_real_time_monitoring() {
        try {
            $real_time_data = $this->api_monitor->get_real_time_usage();
            
            $required_keys = array(
                'timestamp', 'current_usage', 'daily_usage',
                'monthly_usage', 'usage_percentages', 'warnings', 'status'
            );
            
            $missing_keys = array();
            foreach ($required_keys as $key) {
                if (!isset($real_time_data[$key])) {
                    $missing_keys[] = $key;
                }
            }
            
            // 상태 값 검증
            $valid_statuses = array('healthy', 'caution', 'warning', 'critical');
            $status_valid = in_array($real_time_data['status'], $valid_statuses);
            
            return array(
                'status' => empty($missing_keys) && $status_valid ? 'PASS' : 'FAIL',
                'message' => empty($missing_keys) && $status_valid ? '실시간 모니터링 테스트 통과' : '실시간 모니터링 데이터 문제',
                'details' => array(
                    'data_keys' => count($real_time_data),
                    'missing_keys' => $missing_keys,
                    'current_status' => $real_time_data['status'],
                    'status_valid' => $status_valid,
                    'warnings_count' => count($real_time_data['warnings'])
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '실시간 모니터링 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * API 요청 추적 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_api_request_tracking() {
        try {
            // 테스트용 요청 데이터
            $test_request = array(
                'model' => 'gpt-3.5-turbo',
                'endpoint' => 'chat/completions'
            );
            
            $test_response = array(
                'usage' => array(
                    'prompt_tokens' => 15,
                    'completion_tokens' => 25,
                    'total_tokens' => 40
                )
            );
            
            // 추적 전 사용량 확인
            $before_usage = $this->api_monitor->get_real_time_usage();
            $before_requests = $before_usage['daily_usage']['requests'];
            $before_tokens = $before_usage['daily_usage']['tokens'];
            
            // API 요청 추적
            $this->api_monitor->track_api_request($test_request, $test_response, 1.2, true);
            
            // 추적 후 사용량 확인
            $after_usage = $this->api_monitor->get_real_time_usage();
            $after_requests = $after_usage['daily_usage']['requests'];
            $after_tokens = $after_usage['daily_usage']['tokens'];
            
            $requests_increased = $after_requests > $before_requests;
            $tokens_increased = $after_tokens > $before_tokens;
            
            return array(
                'status' => $requests_increased && $tokens_increased ? 'PASS' : 'FAIL',
                'message' => $requests_increased && $tokens_increased ? 'API 요청 추적 테스트 통과' : 'API 요청 추적 실패',
                'details' => array(
                    'requests_before' => $before_requests,
                    'requests_after' => $after_requests,
                    'tokens_before' => $before_tokens,
                    'tokens_after' => $after_tokens,
                    'requests_increased' => $requests_increased,
                    'tokens_increased' => $tokens_increased
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => 'API 요청 추적 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 비용 계산 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_cost_calculation() {
        try {
            // 다양한 모델에 대한 비용 계산 테스트
            $test_cases = array(
                array(
                    'model' => 'gpt-3.5-turbo',
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'expected_min' => 0.0001,
                    'expected_max' => 0.001
                ),
                array(
                    'model' => 'gpt-4',
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'expected_min' => 0.005,
                    'expected_max' => 0.01
                )
            );
            
            $test_results = array();
            $all_passed = true;
            
            foreach ($test_cases as $test_case) {
                $request_data = array('model' => $test_case['model']);
                $usage_data = array(
                    'prompt_tokens' => $test_case['prompt_tokens'],
                    'completion_tokens' => $test_case['completion_tokens'],
                    'total_tokens' => $test_case['prompt_tokens'] + $test_case['completion_tokens']
                );
                
                $response_data = array('usage' => $usage_data);
                
                // 비용 계산을 위해 추적 실행
                $before_cost = $this->api_monitor->get_real_time_usage()['daily_usage']['cost'];
                $this->api_monitor->track_api_request($request_data, $response_data, 1.0, true);
                $after_cost = $this->api_monitor->get_real_time_usage()['daily_usage']['cost'];
                
                $calculated_cost = $after_cost - $before_cost;
                $cost_in_range = $calculated_cost >= $test_case['expected_min'] && $calculated_cost <= $test_case['expected_max'];
                
                $test_results[] = array(
                    'model' => $test_case['model'],
                    'calculated_cost' => $calculated_cost,
                    'expected_range' => $test_case['expected_min'] . ' - ' . $test_case['expected_max'],
                    'passed' => $cost_in_range
                );
                
                if (!$cost_in_range) {
                    $all_passed = false;
                }
            }
            
            return array(
                'status' => $all_passed ? 'PASS' : 'FAIL',
                'message' => $all_passed ? '비용 계산 테스트 통과' : '비용 계산 오류 발견',
                'details' => array(
                    'test_cases' => count($test_cases),
                    'results' => $test_results,
                    'all_passed' => $all_passed
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '비용 계산 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 경고 시스템 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_warning_system() {
        try {
            // 현재 사용량 확인
            $current_usage = $this->api_monitor->get_real_time_usage();
            $warnings_before = count($current_usage['warnings']);
            
            // 경고 임계값 낮춰서 경고 발생시키기
            $original_limits = $this->api_monitor->get_usage_limits();
            $test_limits = array(
                'daily_requests' => 1,  // 매우 낮은 값으로 설정
                'daily_tokens' => 1,
                'daily_cost' => 0.001
            );
            
            $this->api_monitor->update_usage_limits($test_limits);
            
            // 테스트 요청으로 경고 발생시키기
            $test_request = array('model' => 'gpt-3.5-turbo');
            $test_response = array(
                'usage' => array(
                    'prompt_tokens' => 10,
                    'completion_tokens' => 10,
                    'total_tokens' => 20
                )
            );
            
            $this->api_monitor->track_api_request($test_request, $test_response, 1.0, true);
            
            // 경고 확인
            $updated_usage = $this->api_monitor->get_real_time_usage();
            $warnings_after = count($updated_usage['warnings']);
            
            // 원래 제한값으로 복원
            $this->api_monitor->update_usage_limits($original_limits);
            
            $warnings_generated = $warnings_after > $warnings_before;
            
            return array(
                'status' => $warnings_generated ? 'PASS' : 'FAIL',
                'message' => $warnings_generated ? '경고 시스템 테스트 통과' : '경고 시스템 작동 안함',
                'details' => array(
                    'warnings_before' => $warnings_before,
                    'warnings_after' => $warnings_after,
                    'warnings_generated' => $warnings_generated,
                    'current_status' => $updated_usage['status']
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '경고 시스템 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 로그 시스템 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_log_system() {
        try {
            $today = date('Y-m-d');
            
            // 요청 로그 확인
            $request_log = $this->api_monitor->get_request_log($today);
            $has_request_logs = !empty($request_log);
            
            // 알림 로그 확인
            $alert_log = $this->api_monitor->get_alert_log();
            $has_alert_logs = is_array($alert_log);
            
            return array(
                'status' => $has_request_logs && $has_alert_logs ? 'PASS' : 'FAIL',
                'message' => $has_request_logs && $has_alert_logs ? '로그 시스템 테스트 통과' : '로그 시스템 문제',
                'details' => array(
                    'request_logs_count' => count($request_log),
                    'alert_logs_count' => count($alert_log),
                    'has_request_logs' => $has_request_logs,
                    'has_alert_logs' => $has_alert_logs
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '로그 시스템 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 데이터 정리 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_data_cleanup() {
        try {
            // 일일 통계 리셋 테스트
            $reset_daily = $this->api_monitor->reset_statistics('daily');
            
            // 리셋 후 사용량 확인
            $usage_after_reset = $this->api_monitor->get_real_time_usage();
            $daily_requests_reset = $usage_after_reset['daily_usage']['requests'] == 0;
            
            return array(
                'status' => $reset_daily && $daily_requests_reset ? 'PASS' : 'FAIL',
                'message' => $reset_daily && $daily_requests_reset ? '데이터 정리 테스트 통과' : '데이터 정리 실패',
                'details' => array(
                    'reset_success' => $reset_daily,
                    'daily_requests_after_reset' => $usage_after_reset['daily_usage']['requests'],
                    'daily_requests_reset' => $daily_requests_reset
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '데이터 정리 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 종합 시스템 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_comprehensive_system() {
        try {
            $comprehensive_test = $this->api_monitor->run_comprehensive_test();
            
            $overall_status = $comprehensive_test['overall_status'];
            $success_rate = $comprehensive_test['summary']['success_rate'];
            
            return array(
                'status' => $overall_status === 'passed' ? 'PASS' : ($overall_status === 'warning' ? 'WARN' : 'FAIL'),
                'message' => "종합 시스템 테스트 완료 (성공률: {$success_rate}%)",
                'details' => array(
                    'overall_status' => $overall_status,
                    'success_rate' => $success_rate,
                    'total_tests' => $comprehensive_test['summary']['total_tests'],
                    'passed' => $comprehensive_test['summary']['passed'],
                    'failed' => $comprehensive_test['summary']['failed'],
                    'warnings' => $comprehensive_test['summary']['warnings']
                )
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => '종합 시스템 테스트 오류: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 테스트 결과 요약 생성
     * 
     * @param float $execution_time 실행 시간 (밀리초)
     * @return array 요약 결과
     */
    private function generate_summary($execution_time) {
        $total_tests = count($this->test_results['tests']);
        $passed = 0;
        $failed = 0;
        $errors = 0;
        $warnings = 0;
        
        foreach ($this->test_results['tests'] as $test) {
            switch ($test['status']) {
                case 'PASS':
                    $passed++;
                    break;
                case 'FAIL':
                    $failed++;
                    break;
                case 'ERROR':
                    $errors++;
                    break;
                case 'WARN':
                    $warnings++;
                    break;
            }
        }
        
        $success_rate = $total_tests > 0 ? round(($passed / $total_tests) * 100, 2) : 0;
        
        return array(
            'total_tests' => $total_tests,
            'passed' => $passed,
            'failed' => $failed,
            'errors' => $errors,
            'warnings' => $warnings,
            'success_rate' => $success_rate,
            'execution_time_ms' => $execution_time,
            'overall_status' => $this->determine_overall_status($passed, $failed, $errors, $warnings)
        );
    }
    
    /**
     * 전체 상태 결정
     * 
     * @param int $passed 통과한 테스트 수
     * @param int $failed 실패한 테스트 수
     * @param int $errors 오류 발생한 테스트 수
     * @param int $warnings 경고 발생한 테스트 수
     * @return string 전체 상태
     */
    private function determine_overall_status($passed, $failed, $errors, $warnings) {
        if ($errors > 0 || $failed > 0) {
            return 'FAILED';
        } elseif ($warnings > 0) {
            return 'WARNING';
        } else {
            return 'PASSED';
        }
    }
    
    /**
     * 테스트 결과를 읽기 쉬운 형태로 포맷
     * 
     * @return string 포맷된 결과
     */
    public function format_test_results() {
        if (empty($this->test_results)) {
            return "테스트가 실행되지 않았습니다.";
        }
        
        $output = "\n=== API 모니터링 시스템 테스트 결과 ===\n";
        $output .= "실행 시간: " . $this->test_results['timestamp'] . "\n";
        $output .= "총 실행 시간: " . $this->test_results['summary']['execution_time_ms'] . "ms\n\n";
        
        $output .= "=== 테스트 요약 ===\n";
        $output .= "총 테스트: " . $this->test_results['summary']['total_tests'] . "\n";
        $output .= "통과: " . $this->test_results['summary']['passed'] . "\n";
        $output .= "실패: " . $this->test_results['summary']['failed'] . "\n";
        $output .= "오류: " . $this->test_results['summary']['errors'] . "\n";
        $output .= "경고: " . $this->test_results['summary']['warnings'] . "\n";
        $output .= "성공률: " . $this->test_results['summary']['success_rate'] . "%\n";
        $output .= "전체 상태: " . $this->test_results['summary']['overall_status'] . "\n\n";
        
        $output .= "=== 개별 테스트 결과 ===\n";
        foreach ($this->test_results['tests'] as $test_name => $test_result) {
            $status_icon = $this->get_status_icon($test_result['status']);
            $output .= "{$status_icon} {$test_name}: {$test_result['message']}\n";
        }
        
        return $output;
    }
    
    /**
     * 상태 아이콘 가져오기
     * 
     * @param string $status 상태
     * @return string 아이콘
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'PASS':
                return '✅';
            case 'FAIL':
                return '❌';
            case 'ERROR':
                return '🚫';
            case 'WARN':
                return '⚠️';
            default:
                return '❓';
        }
    }
} 
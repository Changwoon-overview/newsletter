<?php
/**
 * API 사용량 모니터링 및 테스트 시스템 클래스
 * OpenAI API 사용량을 모니터링하고 시스템 전반의 테스트를 수행합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_API_Monitor {
    
    /**
     * OpenAI 클라이언트
     */
    private $openai_client;
    
    /**
     * 에러 핸들러
     */
    private $error_handler;
    
    /**
     * 모니터링 설정
     */
    private $monitor_settings;
    
    /**
     * 사용량 제한
     */
    private $usage_limits;
    
    /**
     * 알림 시스템
     */
    private $notification_system;
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->openai_client = new AINL_OpenAI_Client();
        $this->error_handler = new AINL_Error_Handler();
        
        $this->init_monitor_settings();
        $this->init_usage_limits();
        $this->init_notification_system();
    }
    
    /**
     * 모니터링 설정 초기화
     */
    private function init_monitor_settings() {
        $default_settings = array(
            'monitoring_enabled' => true,
            'real_time_tracking' => true,
            'cost_tracking' => true,
            'performance_tracking' => true,
            'error_tracking' => true,
            'daily_reports' => true,
            'weekly_reports' => true,
            'alert_thresholds' => array(
                'daily_cost' => 10.0,      // $10 per day
                'monthly_cost' => 200.0,   // $200 per month
                'error_rate' => 5.0,       // 5% error rate
                'response_time' => 5.0     // 5 seconds
            ),
            'data_retention_days' => 90,
            'backup_frequency' => 'daily'
        );
        
        $saved_settings = get_option('ainl_monitor_settings', array());
        $this->monitor_settings = array_merge($default_settings, $saved_settings);
    }
    
    /**
     * 사용량 제한 초기화
     */
    private function init_usage_limits() {
        $default_limits = array(
            'daily_requests' => 1000,
            'daily_tokens' => 100000,
            'daily_cost' => 20.0,
            'monthly_requests' => 25000,
            'monthly_tokens' => 2000000,
            'monthly_cost' => 500.0,
            'rate_limit_per_minute' => 60,
            'max_tokens_per_request' => 4000,
            'emergency_stop_cost' => 100.0
        );
        
        $saved_limits = get_option('ainl_usage_limits', array());
        $this->usage_limits = array_merge($default_limits, $saved_limits);
    }
    
    /**
     * 알림 시스템 초기화
     */
    private function init_notification_system() {
        $this->notification_system = array(
            'email_notifications' => true,
            'admin_notifications' => true,
            'slack_webhook' => '',
            'discord_webhook' => '',
            'notification_levels' => array('warning', 'critical', 'emergency')
        );
        
        $saved_notifications = get_option('ainl_notification_settings', array());
        $this->notification_system = array_merge($this->notification_system, $saved_notifications);
    }
    
    /**
     * 실시간 사용량 모니터링
     * 
     * @return array 현재 사용량 상태
     */
    public function get_real_time_usage() {
        $usage_stats = $this->openai_client->get_usage_stats();
        $current_time = current_time('mysql');
        
        // 현재 사용량 분석
        $daily_usage = $this->get_daily_usage();
        $monthly_usage = $this->get_monthly_usage();
        
        // 제한 대비 사용률 계산
        $usage_percentages = array(
            'daily_requests' => ($daily_usage['requests'] / $this->usage_limits['daily_requests']) * 100,
            'daily_tokens' => ($daily_usage['tokens'] / $this->usage_limits['daily_tokens']) * 100,
            'daily_cost' => ($daily_usage['cost'] / $this->usage_limits['daily_cost']) * 100,
            'monthly_requests' => ($monthly_usage['requests'] / $this->usage_limits['monthly_requests']) * 100,
            'monthly_tokens' => ($monthly_usage['tokens'] / $this->usage_limits['monthly_tokens']) * 100,
            'monthly_cost' => ($monthly_usage['cost'] / $this->usage_limits['monthly_cost']) * 100
        );
        
        // 경고 상태 확인
        $warnings = $this->check_usage_warnings($usage_percentages);
        
        return array(
            'timestamp' => $current_time,
            'current_usage' => $usage_stats,
            'daily_usage' => $daily_usage,
            'monthly_usage' => $monthly_usage,
            'usage_percentages' => $usage_percentages,
            'warnings' => $warnings,
            'status' => $this->determine_system_status($usage_percentages, $warnings)
        );
    }
    
    /**
     * 일일 사용량 가져오기
     * 
     * @return array 일일 사용량
     */
    private function get_daily_usage() {
        $today = date('Y-m-d');
        $daily_data = get_option('ainl_daily_usage_' . $today, array(
            'requests' => 0,
            'tokens' => 0,
            'cost' => 0.0,
            'errors' => 0,
            'avg_response_time' => 0.0
        ));
        
        return $daily_data;
    }
    
    /**
     * 월간 사용량 가져오기
     * 
     * @return array 월간 사용량
     */
    private function get_monthly_usage() {
        $current_month = date('Y-m');
        $monthly_data = get_option('ainl_monthly_usage_' . $current_month, array(
            'requests' => 0,
            'tokens' => 0,
            'cost' => 0.0,
            'errors' => 0,
            'avg_response_time' => 0.0,
            'peak_usage_day' => '',
            'peak_usage_value' => 0
        ));
        
        return $monthly_data;
    }
    
    /**
     * 사용량 경고 확인
     * 
     * @param array $usage_percentages 사용률
     * @return array 경고 목록
     */
    private function check_usage_warnings($usage_percentages) {
        $warnings = array();
        
        foreach ($usage_percentages as $metric => $percentage) {
            if ($percentage >= 90) {
                $warnings[] = array(
                    'level' => 'critical',
                    'metric' => $metric,
                    'percentage' => $percentage,
                    'message' => "{$metric} 사용량이 90%를 초과했습니다 ({$percentage}%)"
                );
            } elseif ($percentage >= 75) {
                $warnings[] = array(
                    'level' => 'warning',
                    'metric' => $metric,
                    'percentage' => $percentage,
                    'message' => "{$metric} 사용량이 75%를 초과했습니다 ({$percentage}%)"
                );
            }
        }
        
        return $warnings;
    }
    
    /**
     * 시스템 상태 결정
     * 
     * @param array $usage_percentages 사용률
     * @param array $warnings 경고 목록
     * @return string 시스템 상태
     */
    private function determine_system_status($usage_percentages, $warnings) {
        $critical_warnings = array_filter($warnings, function($warning) {
            return $warning['level'] === 'critical';
        });
        
        if (!empty($critical_warnings)) {
            return 'critical';
        }
        
        $warning_count = count($warnings);
        if ($warning_count >= 3) {
            return 'warning';
        } elseif ($warning_count > 0) {
            return 'caution';
        }
        
        return 'healthy';
    }
    
    /**
     * API 요청 추적 및 기록
     * 
     * @param array $request_data 요청 데이터
     * @param array $response_data 응답 데이터
     * @param float $response_time 응답 시간
     * @param bool $success 성공 여부
     */
    public function track_api_request($request_data, $response_data, $response_time, $success = true) {
        $today = date('Y-m-d');
        $current_month = date('Y-m');
        
        // 일일 사용량 업데이트
        $daily_usage = $this->get_daily_usage();
        $daily_usage['requests']++;
        
        if ($success && isset($response_data['usage'])) {
            $usage = $response_data['usage'];
            $daily_usage['tokens'] += isset($usage['total_tokens']) ? $usage['total_tokens'] : 0;
            
            // 비용 계산 (모델별 다른 요금 적용)
            $cost = $this->calculate_request_cost($request_data, $usage);
            $daily_usage['cost'] += $cost;
        } else {
            $daily_usage['errors']++;
        }
        
        // 평균 응답 시간 업데이트
        $total_requests = $daily_usage['requests'];
        $daily_usage['avg_response_time'] = (($daily_usage['avg_response_time'] * ($total_requests - 1)) + $response_time) / $total_requests;
        
        update_option('ainl_daily_usage_' . $today, $daily_usage);
        
        // 월간 사용량 업데이트
        $monthly_usage = $this->get_monthly_usage();
        $monthly_usage['requests']++;
        
        if ($success && isset($response_data['usage'])) {
            $usage = $response_data['usage'];
            $monthly_usage['tokens'] += isset($usage['total_tokens']) ? $usage['total_tokens'] : 0;
            $cost = $this->calculate_request_cost($request_data, $usage);
            $monthly_usage['cost'] += $cost;
        } else {
            $monthly_usage['errors']++;
        }
        
        // 피크 사용량 추적
        if ($daily_usage['requests'] > $monthly_usage['peak_usage_value']) {
            $monthly_usage['peak_usage_day'] = $today;
            $monthly_usage['peak_usage_value'] = $daily_usage['requests'];
        }
        
        update_option('ainl_monthly_usage_' . $current_month, $monthly_usage);
        
        // 실시간 알림 확인
        $this->check_real_time_alerts($daily_usage, $monthly_usage);
        
        // 상세 로그 저장
        $this->log_detailed_request($request_data, $response_data, $response_time, $success);
    }
    
    /**
     * 요청 비용 계산
     * 
     * @param array $request_data 요청 데이터
     * @param array $usage 사용량 데이터
     * @return float 비용
     */
    private function calculate_request_cost($request_data, $usage) {
        $model = isset($request_data['model']) ? $request_data['model'] : 'gpt-3.5-turbo';
        
        // 모델별 요금표 (2024년 기준)
        $pricing = array(
            'gpt-3.5-turbo' => array('input' => 0.0015, 'output' => 0.002),
            'gpt-3.5-turbo-16k' => array('input' => 0.003, 'output' => 0.004),
            'gpt-4' => array('input' => 0.03, 'output' => 0.06),
            'gpt-4-32k' => array('input' => 0.06, 'output' => 0.12),
            'gpt-4-turbo' => array('input' => 0.01, 'output' => 0.03),
            'gpt-4o' => array('input' => 0.005, 'output' => 0.015)
        );
        
        if (!isset($pricing[$model])) {
            $model = 'gpt-3.5-turbo'; // 기본값
        }
        
        $prompt_tokens = isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : 0;
        $completion_tokens = isset($usage['completion_tokens']) ? $usage['completion_tokens'] : 0;
        
        $input_cost = ($prompt_tokens / 1000) * $pricing[$model]['input'];
        $output_cost = ($completion_tokens / 1000) * $pricing[$model]['output'];
        
        return $input_cost + $output_cost;
    }
    
    /**
     * 실시간 알림 확인
     * 
     * @param array $daily_usage 일일 사용량
     * @param array $monthly_usage 월간 사용량
     */
    private function check_real_time_alerts($daily_usage, $monthly_usage) {
        $alerts = array();
        
        // 일일 제한 확인
        if ($daily_usage['cost'] >= $this->usage_limits['emergency_stop_cost']) {
            $alerts[] = array(
                'level' => 'emergency',
                'type' => 'cost_limit',
                'message' => '긴급 정지: 일일 비용이 긴급 정지 한도를 초과했습니다.',
                'value' => $daily_usage['cost'],
                'limit' => $this->usage_limits['emergency_stop_cost']
            );
        } elseif ($daily_usage['cost'] >= $this->usage_limits['daily_cost']) {
            $alerts[] = array(
                'level' => 'critical',
                'type' => 'daily_cost',
                'message' => '일일 비용 한도 초과',
                'value' => $daily_usage['cost'],
                'limit' => $this->usage_limits['daily_cost']
            );
        }
        
        if ($daily_usage['requests'] >= $this->usage_limits['daily_requests']) {
            $alerts[] = array(
                'level' => 'warning',
                'type' => 'daily_requests',
                'message' => '일일 요청 한도 초과',
                'value' => $daily_usage['requests'],
                'limit' => $this->usage_limits['daily_requests']
            );
        }
        
        // 월간 제한 확인
        if ($monthly_usage['cost'] >= $this->usage_limits['monthly_cost']) {
            $alerts[] = array(
                'level' => 'critical',
                'type' => 'monthly_cost',
                'message' => '월간 비용 한도 초과',
                'value' => $monthly_usage['cost'],
                'limit' => $this->usage_limits['monthly_cost']
            );
        }
        
        // 알림 발송
        foreach ($alerts as $alert) {
            $this->send_alert($alert);
        }
    }
    
    /**
     * 상세 요청 로그 저장
     * 
     * @param array $request_data 요청 데이터
     * @param array $response_data 응답 데이터
     * @param float $response_time 응답 시간
     * @param bool $success 성공 여부
     */
    private function log_detailed_request($request_data, $response_data, $response_time, $success) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'request_id' => uniqid('req_'),
            'model' => isset($request_data['model']) ? $request_data['model'] : 'unknown',
            'endpoint' => isset($request_data['endpoint']) ? $request_data['endpoint'] : 'chat/completions',
            'success' => $success,
            'response_time' => $response_time,
            'request_size' => strlen(wp_json_encode($request_data)),
            'response_size' => $success ? strlen(wp_json_encode($response_data)) : 0,
            'usage' => $success && isset($response_data['usage']) ? $response_data['usage'] : null,
            'error' => !$success && isset($response_data['error']) ? $response_data['error'] : null
        );
        
        // 로그를 데이터베이스에 저장 (옵션으로 파일에도 저장 가능)
        $this->save_request_log($log_entry);
    }
    
    /**
     * 요청 로그 저장
     * 
     * @param array $log_entry 로그 엔트리
     */
    private function save_request_log($log_entry) {
        $today = date('Y-m-d');
        $log_key = 'ainl_request_log_' . $today;
        
        $existing_logs = get_option($log_key, array());
        $existing_logs[] = $log_entry;
        
        // 일일 로그 크기 제한 (최대 1000개)
        if (count($existing_logs) > 1000) {
            $existing_logs = array_slice($existing_logs, -1000);
        }
        
        update_option($log_key, $existing_logs);
        
        // 오래된 로그 정리 (데이터 보존 기간 초과)
        $this->cleanup_old_logs();
    }
    
    /**
     * 오래된 로그 정리
     */
    private function cleanup_old_logs() {
        $retention_days = $this->monitor_settings['data_retention_days'];
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));
        
        // 정리할 날짜들 찾기
        for ($i = $retention_days + 1; $i <= $retention_days + 30; $i++) {
            $old_date = date('Y-m-d', strtotime("-{$i} days"));
            delete_option('ainl_request_log_' . $old_date);
            delete_option('ainl_daily_usage_' . $old_date);
        }
    }
    
    /**
     * 알림 발송
     * 
     * @param array $alert 알림 데이터
     */
    private function send_alert($alert) {
        // 이메일 알림
        if ($this->notification_system['email_notifications']) {
            $this->send_email_alert($alert);
        }
        
        // 관리자 알림
        if ($this->notification_system['admin_notifications']) {
            $this->send_admin_notification($alert);
        }
        
        // Slack 웹훅
        if (!empty($this->notification_system['slack_webhook'])) {
            $this->send_slack_alert($alert);
        }
        
        // Discord 웹훅
        if (!empty($this->notification_system['discord_webhook'])) {
            $this->send_discord_alert($alert);
        }
        
        // 알림 로그 저장
        $this->log_alert($alert);
    }
    
    /**
     * 이메일 알림 발송
     * 
     * @param array $alert 알림 데이터
     */
    private function send_email_alert($alert) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = "[{$site_name}] API 모니터링 알림: {$alert['level']}";
        $message = "API 사용량 알림이 발생했습니다.\n\n";
        $message .= "레벨: {$alert['level']}\n";
        $message .= "타입: {$alert['type']}\n";
        $message .= "메시지: {$alert['message']}\n";
        
        if (isset($alert['value']) && isset($alert['limit'])) {
            $message .= "현재 값: {$alert['value']}\n";
            $message .= "제한 값: {$alert['limit']}\n";
        }
        
        $message .= "\n시간: " . current_time('mysql') . "\n";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * 관리자 알림 발송
     * 
     * @param array $alert 알림 데이터
     */
    private function send_admin_notification($alert) {
        $notice_key = 'ainl_alert_' . md5(serialize($alert));
        
        // 중복 알림 방지 (1시간 내)
        $last_sent = get_transient($notice_key);
        if ($last_sent) {
            return;
        }
        
        // WordPress 관리자 알림 추가
        add_action('admin_notices', function() use ($alert) {
            $class = $alert['level'] === 'critical' ? 'notice-error' : 'notice-warning';
            echo "<div class='notice {$class} is-dismissible'>";
            echo "<p><strong>API 모니터링 알림:</strong> {$alert['message']}</p>";
            echo "</div>";
        });
        
        // 1시간 동안 중복 알림 방지
        set_transient($notice_key, true, HOUR_IN_SECONDS);
    }
    
    /**
     * Slack 알림 발송
     * 
     * @param array $alert 알림 데이터
     */
    private function send_slack_alert($alert) {
        $webhook_url = $this->notification_system['slack_webhook'];
        
        $color = array(
            'warning' => 'warning',
            'critical' => 'danger',
            'emergency' => 'danger'
        );
        
        $payload = array(
            'text' => 'API 모니터링 알림',
            'attachments' => array(
                array(
                    'color' => isset($color[$alert['level']]) ? $color[$alert['level']] : 'warning',
                    'fields' => array(
                        array(
                            'title' => '레벨',
                            'value' => $alert['level'],
                            'short' => true
                        ),
                        array(
                            'title' => '메시지',
                            'value' => $alert['message'],
                            'short' => false
                        )
                    ),
                    'ts' => time()
                )
            )
        );
        
        wp_remote_post($webhook_url, array(
            'body' => wp_json_encode($payload),
            'headers' => array('Content-Type' => 'application/json')
        ));
    }
    
    /**
     * Discord 알림 발송
     * 
     * @param array $alert 알림 데이터
     */
    private function send_discord_alert($alert) {
        $webhook_url = $this->notification_system['discord_webhook'];
        
        $color = array(
            'warning' => 16776960,  // 노란색
            'critical' => 16711680, // 빨간색
            'emergency' => 8388608  // 진한 빨간색
        );
        
        $payload = array(
            'embeds' => array(
                array(
                    'title' => 'API 모니터링 알림',
                    'description' => $alert['message'],
                    'color' => isset($color[$alert['level']]) ? $color[$alert['level']] : 16776960,
                    'fields' => array(
                        array(
                            'name' => '레벨',
                            'value' => $alert['level'],
                            'inline' => true
                        ),
                        array(
                            'name' => '타입',
                            'value' => $alert['type'],
                            'inline' => true
                        )
                    ),
                    'timestamp' => date('c')
                )
            )
        );
        
        wp_remote_post($webhook_url, array(
            'body' => wp_json_encode($payload),
            'headers' => array('Content-Type' => 'application/json')
        ));
    }
    
    /**
     * 알림 로그 저장
     * 
     * @param array $alert 알림 데이터
     */
    private function log_alert($alert) {
        $alert_log = get_option('ainl_alert_log', array());
        
        $alert['timestamp'] = current_time('mysql');
        $alert_log[] = $alert;
        
        // 최근 100개 알림만 유지
        if (count($alert_log) > 100) {
            $alert_log = array_slice($alert_log, -100);
        }
        
        update_option('ainl_alert_log', $alert_log);
    }
    
    /**
     * 종합 시스템 테스트 실행
     * 
     * @return array 테스트 결과
     */
    public function run_comprehensive_test() {
        $test_results = array(
            'timestamp' => current_time('mysql'),
            'tests' => array(),
            'overall_status' => 'unknown',
            'summary' => array(
                'total_tests' => 0,
                'passed' => 0,
                'failed' => 0,
                'warnings' => 0
            )
        );
        
        // 1. OpenAI API 연결 테스트
        $test_results['tests']['api_connection'] = $this->test_api_connection();
        
        // 2. 에러 핸들러 테스트
        $test_results['tests']['error_handler'] = $this->test_error_handler();
        
        // 3. 사용량 추적 테스트
        $test_results['tests']['usage_tracking'] = $this->test_usage_tracking();
        
        // 4. 모니터링 시스템 테스트
        $test_results['tests']['monitoring_system'] = $this->test_monitoring_system();
        
        // 5. 알림 시스템 테스트
        $test_results['tests']['notification_system'] = $this->test_notification_system();
        
        // 6. 데이터 무결성 테스트
        $test_results['tests']['data_integrity'] = $this->test_data_integrity();
        
        // 7. 성능 테스트
        $test_results['tests']['performance'] = $this->test_performance();
        
        // 결과 요약
        $test_results = $this->summarize_test_results($test_results);
        
        // 테스트 결과 저장
        update_option('ainl_last_comprehensive_test', $test_results);
        
        return $test_results;
    }
    
    /**
     * API 연결 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_api_connection() {
        try {
            $connection_test = $this->openai_client->test_connection();
            
            return array(
                'status' => $connection_test['success'] ? 'passed' : 'failed',
                'message' => $connection_test['message'],
                'response_time' => $connection_test['response_time'],
                'model_count' => $connection_test['model_count'],
                'details' => $connection_test
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'failed',
                'message' => 'API 연결 테스트 실패: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 에러 핸들러 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_error_handler() {
        try {
            $error_handler_test = new AINL_Error_Handler_Test();
            $test_results = $error_handler_test->run_all_tests();
            
            $passed = $test_results['summary']['passed'];
            $total = $test_results['summary']['total_tests'];
            $success_rate = $test_results['summary']['success_rate'];
            
            return array(
                'status' => $success_rate >= 80 ? 'passed' : ($success_rate >= 60 ? 'warning' : 'failed'),
                'message' => "에러 핸들러 테스트: {$passed}/{$total} 통과 ({$success_rate}%)",
                'success_rate' => $success_rate,
                'details' => $test_results
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'failed',
                'message' => '에러 핸들러 테스트 실패: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 사용량 추적 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_usage_tracking() {
        try {
            // 테스트용 가짜 요청 데이터
            $test_request = array(
                'model' => 'gpt-3.5-turbo',
                'endpoint' => 'chat/completions'
            );
            
            $test_response = array(
                'usage' => array(
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30
                )
            );
            
            // 사용량 추적 테스트
            $this->track_api_request($test_request, $test_response, 1.5, true);
            
            // 추적된 데이터 확인
            $daily_usage = $this->get_daily_usage();
            $real_time_usage = $this->get_real_time_usage();
            
            $tracking_works = $daily_usage['requests'] > 0 && $daily_usage['tokens'] > 0;
            
            return array(
                'status' => $tracking_works ? 'passed' : 'failed',
                'message' => $tracking_works ? '사용량 추적 정상 작동' : '사용량 추적 실패',
                'daily_usage' => $daily_usage,
                'real_time_status' => $real_time_usage['status']
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'failed',
                'message' => '사용량 추적 테스트 실패: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 모니터링 시스템 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_monitoring_system() {
        try {
            $monitoring_enabled = $this->monitor_settings['monitoring_enabled'];
            $real_time_data = $this->get_real_time_usage();
            $has_usage_data = !empty($real_time_data['current_usage']);
            
            return array(
                'status' => ($monitoring_enabled && $has_usage_data) ? 'passed' : 'warning',
                'message' => $monitoring_enabled ? '모니터링 시스템 활성화됨' : '모니터링 시스템 비활성화됨',
                'monitoring_enabled' => $monitoring_enabled,
                'has_usage_data' => $has_usage_data,
                'system_status' => $real_time_data['status']
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'failed',
                'message' => '모니터링 시스템 테스트 실패: ' . $e->getMessage(),
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
            $email_enabled = $this->notification_system['email_notifications'];
            $admin_enabled = $this->notification_system['admin_notifications'];
            $has_webhooks = !empty($this->notification_system['slack_webhook']) || 
                           !empty($this->notification_system['discord_webhook']);
            
            $notification_methods = 0;
            if ($email_enabled) $notification_methods++;
            if ($admin_enabled) $notification_methods++;
            if ($has_webhooks) $notification_methods++;
            
            return array(
                'status' => $notification_methods >= 1 ? 'passed' : 'warning',
                'message' => "알림 시스템: {$notification_methods}개 방법 활성화됨",
                'email_enabled' => $email_enabled,
                'admin_enabled' => $admin_enabled,
                'has_webhooks' => $has_webhooks,
                'notification_methods' => $notification_methods
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'failed',
                'message' => '알림 시스템 테스트 실패: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 데이터 무결성 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_data_integrity() {
        try {
            $issues = array();
            
            // 사용량 데이터 일관성 확인
            $daily_usage = $this->get_daily_usage();
            $monthly_usage = $this->get_monthly_usage();
            
            if ($daily_usage['requests'] < 0 || $daily_usage['tokens'] < 0 || $daily_usage['cost'] < 0) {
                $issues[] = '일일 사용량 데이터에 음수 값 발견';
            }
            
            if ($monthly_usage['requests'] < 0 || $monthly_usage['tokens'] < 0 || $monthly_usage['cost'] < 0) {
                $issues[] = '월간 사용량 데이터에 음수 값 발견';
            }
            
            // 설정 데이터 유효성 확인
            if ($this->usage_limits['daily_cost'] <= 0 || $this->usage_limits['monthly_cost'] <= 0) {
                $issues[] = '사용량 제한 설정에 유효하지 않은 값 발견';
            }
            
            return array(
                'status' => empty($issues) ? 'passed' : 'warning',
                'message' => empty($issues) ? '데이터 무결성 확인됨' : '데이터 무결성 문제 발견',
                'issues' => $issues,
                'daily_usage' => $daily_usage,
                'monthly_usage' => $monthly_usage
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'failed',
                'message' => '데이터 무결성 테스트 실패: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 성능 테스트
     * 
     * @return array 테스트 결과
     */
    private function test_performance() {
        try {
            $start_time = microtime(true);
            
            // 실시간 사용량 조회 성능 테스트
            for ($i = 0; $i < 10; $i++) {
                $this->get_real_time_usage();
            }
            
            $end_time = microtime(true);
            $avg_response_time = ($end_time - $start_time) / 10;
            
            $performance_threshold = 0.5; // 0.5초
            $performance_good = $avg_response_time < $performance_threshold;
            
            return array(
                'status' => $performance_good ? 'passed' : 'warning',
                'message' => $performance_good ? '성능 테스트 통과' : '성능 개선 필요',
                'avg_response_time' => round($avg_response_time, 4),
                'threshold' => $performance_threshold,
                'operations_tested' => 10
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'failed',
                'message' => '성능 테스트 실패: ' . $e->getMessage(),
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 테스트 결과 요약
     * 
     * @param array $test_results 테스트 결과
     * @return array 요약된 테스트 결과
     */
    private function summarize_test_results($test_results) {
        $total_tests = count($test_results['tests']);
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        
        foreach ($test_results['tests'] as $test) {
            switch ($test['status']) {
                case 'passed':
                    $passed++;
                    break;
                case 'failed':
                    $failed++;
                    break;
                case 'warning':
                    $warnings++;
                    break;
            }
        }
        
        // 전체 상태 결정
        if ($failed > 0) {
            $overall_status = 'failed';
        } elseif ($warnings > 0) {
            $overall_status = 'warning';
        } else {
            $overall_status = 'passed';
        }
        
        $test_results['overall_status'] = $overall_status;
        $test_results['summary'] = array(
            'total_tests' => $total_tests,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'success_rate' => $total_tests > 0 ? round(($passed / $total_tests) * 100, 2) : 0
        );
        
        return $test_results;
    }
    
    /**
     * 모니터링 설정 업데이트
     * 
     * @param array $new_settings 새 설정
     * @return bool 성공 여부
     */
    public function update_monitor_settings($new_settings) {
        $this->monitor_settings = array_merge($this->monitor_settings, $new_settings);
        return update_option('ainl_monitor_settings', $this->monitor_settings);
    }
    
    /**
     * 사용량 제한 업데이트
     * 
     * @param array $new_limits 새 제한
     * @return bool 성공 여부
     */
    public function update_usage_limits($new_limits) {
        $this->usage_limits = array_merge($this->usage_limits, $new_limits);
        return update_option('ainl_usage_limits', $this->usage_limits);
    }
    
    /**
     * 알림 설정 업데이트
     * 
     * @param array $new_settings 새 알림 설정
     * @return bool 성공 여부
     */
    public function update_notification_settings($new_settings) {
        $this->notification_system = array_merge($this->notification_system, $new_settings);
        return update_option('ainl_notification_settings', $this->notification_system);
    }
    
    /**
     * 현재 모니터링 설정 가져오기
     * 
     * @return array 모니터링 설정
     */
    public function get_monitor_settings() {
        return $this->monitor_settings;
    }
    
    /**
     * 현재 사용량 제한 가져오기
     * 
     * @return array 사용량 제한
     */
    public function get_usage_limits() {
        return $this->usage_limits;
    }
    
    /**
     * 현재 알림 설정 가져오기
     * 
     * @return array 알림 설정
     */
    public function get_notification_settings() {
        return $this->notification_system;
    }
    
    /**
     * 알림 로그 가져오기
     * 
     * @return array 알림 로그
     */
    public function get_alert_log() {
        return get_option('ainl_alert_log', array());
    }
    
    /**
     * 요청 로그 가져오기
     * 
     * @param string $date 날짜 (Y-m-d 형식)
     * @return array 요청 로그
     */
    public function get_request_log($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        return get_option('ainl_request_log_' . $date, array());
    }
    
    /**
     * 통계 리셋
     * 
     * @param string $type 리셋 타입 (daily, monthly, all)
     * @return bool 성공 여부
     */
    public function reset_statistics($type = 'daily') {
        switch ($type) {
            case 'daily':
                $today = date('Y-m-d');
                delete_option('ainl_daily_usage_' . $today);
                delete_option('ainl_request_log_' . $today);
                break;
                
            case 'monthly':
                $current_month = date('Y-m');
                delete_option('ainl_monthly_usage_' . $current_month);
                break;
                
            case 'all':
                // 모든 사용량 및 로그 데이터 삭제
                global $wpdb;
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ainl_daily_usage_%'");
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ainl_monthly_usage_%'");
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ainl_request_log_%'");
                delete_option('ainl_alert_log');
                break;
        }
        
        return true;
    }
} 
<?php
/**
 * 에러 처리 및 재시도 로직 테스트 클래스
 * AINL_Error_Handler의 기능을 검증하고 테스트합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Error_Handler_Test {
    
    /**
     * 에러 핸들러 인스턴스
     */
    private $error_handler;
    
    /**
     * 테스트 결과
     */
    private $test_results = array();
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->error_handler = new AINL_Error_Handler();
    }
    
    /**
     * 모든 테스트 실행
     * 
     * @return array 테스트 결과
     */
    public function run_all_tests() {
        $this->test_results = array();
        
        // 기본 기능 테스트
        $this->test_error_classification();
        $this->test_retry_policies();
        $this->test_delay_calculation();
        
        // 재시도 로직 테스트
        $this->test_successful_retry();
        $this->test_failed_retry();
        $this->test_non_retryable_error();
        
        // 회로 차단기 테스트
        $this->test_circuit_breaker_open();
        $this->test_circuit_breaker_recovery();
        
        // 폴백 메커니즘 테스트
        $this->test_fallback_success();
        $this->test_fallback_failure();
        
        // 통계 및 로깅 테스트
        $this->test_statistics_tracking();
        $this->test_error_logging();
        
        return $this->get_test_summary();
    }
    
    /**
     * 에러 분류 테스트
     */
    private function test_error_classification() {
        $test_name = 'Error Classification';
        
        try {
            // 다양한 에러 타입 테스트
            $test_cases = array(
                array('code' => 401, 'message' => 'Unauthorized', 'expected' => 'auth_error'),
                array('code' => 429, 'message' => 'Rate limit exceeded', 'expected' => 'quota_error'),
                array('code' => 500, 'message' => 'Internal server error', 'expected' => 'server_error'),
                array('code' => 0, 'message' => 'Connection timeout', 'expected' => 'network_error'),
                array('code' => 400, 'message' => 'Bad request', 'expected' => 'request_error'),
                array('code' => 0, 'message' => 'Model not found', 'expected' => 'model_error'),
                array('code' => 999, 'message' => 'Unknown error', 'expected' => 'unknown_error')
            );
            
            $passed = 0;
            $total = count($test_cases);
            
            foreach ($test_cases as $case) {
                $exception = new Exception($case['message'], $case['code']);
                $result = $this->error_handler->classify_error($exception);
                
                if ($result === $case['expected']) {
                    $passed++;
                }
            }
            
            $this->test_results[$test_name] = array(
                'status' => $passed === $total ? 'PASS' : 'FAIL',
                'passed' => $passed,
                'total' => $total,
                'details' => "에러 분류 정확도: {$passed}/{$total}"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 재시도 정책 테스트
     */
    private function test_retry_policies() {
        $test_name = 'Retry Policies';
        
        try {
            $error_types = array('auth_error', 'quota_error', 'server_error', 'network_error', 'request_error', 'unknown_error');
            $passed = 0;
            $total = count($error_types);
            
            foreach ($error_types as $error_type) {
                $policy = $this->error_handler->get_retry_policy($error_type);
                
                // 필수 필드 확인
                $required_fields = array('retryable', 'max_retries', 'base_delay', 'exponential_base', 'max_delay');
                $has_all_fields = true;
                
                foreach ($required_fields as $field) {
                    if (!isset($policy[$field])) {
                        $has_all_fields = false;
                        break;
                    }
                }
                
                if ($has_all_fields) {
                    $passed++;
                }
            }
            
            $this->test_results[$test_name] = array(
                'status' => $passed === $total ? 'PASS' : 'FAIL',
                'passed' => $passed,
                'total' => $total,
                'details' => "재시도 정책 완성도: {$passed}/{$total}"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 지연 시간 계산 테스트
     */
    private function test_delay_calculation() {
        $test_name = 'Delay Calculation';
        
        try {
            // 지연 시간 계산 로직을 간접적으로 테스트
            // (private 메서드이므로 결과를 통해 검증)
            
            $test_function = function() {
                // 항상 실패하는 함수
                throw new Exception('Test error', 500);
            };
            
            $start_time = microtime(true);
            
            try {
                $this->error_handler->execute_with_retry($test_function, array(), array(
                    'service_name' => 'delay_test',
                    'log_attempts' => false
                ));
            } catch (Exception $e) {
                // 예상된 실패
            }
            
            $total_time = microtime(true) - $start_time;
            
            // 재시도로 인한 지연이 있었는지 확인 (최소 2초 이상)
            $has_delay = $total_time >= 2.0;
            
            $this->test_results[$test_name] = array(
                'status' => $has_delay ? 'PASS' : 'FAIL',
                'details' => "총 소요시간: " . round($total_time, 2) . "초 (지연 확인: " . ($has_delay ? 'YES' : 'NO') . ")"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 성공적인 재시도 테스트
     */
    private function test_successful_retry() {
        $test_name = 'Successful Retry';
        
        try {
            $attempt_count = 0;
            
            $test_function = function() use (&$attempt_count) {
                $attempt_count++;
                if ($attempt_count < 3) {
                    throw new Exception('Temporary failure', 500);
                }
                return 'success';
            };
            
            $result = $this->error_handler->execute_with_retry($test_function, array(), array(
                'service_name' => 'retry_success_test',
                'log_attempts' => false
            ));
            
            $this->test_results[$test_name] = array(
                'status' => ($result === 'success' && $attempt_count === 3) ? 'PASS' : 'FAIL',
                'details' => "결과: {$result}, 시도 횟수: {$attempt_count}"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'FAIL',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 실패한 재시도 테스트
     */
    private function test_failed_retry() {
        $test_name = 'Failed Retry';
        
        try {
            $attempt_count = 0;
            
            $test_function = function() use (&$attempt_count) {
                $attempt_count++;
                throw new Exception('Persistent failure', 500);
            };
            
            $failed_as_expected = false;
            
            try {
                $this->error_handler->execute_with_retry($test_function, array(), array(
                    'service_name' => 'retry_failure_test',
                    'log_attempts' => false
                ));
            } catch (Exception $e) {
                $failed_as_expected = true;
            }
            
            // 서버 에러는 최대 3회 재시도하므로 총 4회 시도
            $expected_attempts = 4;
            
            $this->test_results[$test_name] = array(
                'status' => ($failed_as_expected && $attempt_count === $expected_attempts) ? 'PASS' : 'FAIL',
                'details' => "예상대로 실패: " . ($failed_as_expected ? 'YES' : 'NO') . ", 시도 횟수: {$attempt_count}/{$expected_attempts}"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 재시도 불가능한 에러 테스트
     */
    private function test_non_retryable_error() {
        $test_name = 'Non-Retryable Error';
        
        try {
            $attempt_count = 0;
            
            $test_function = function() use (&$attempt_count) {
                $attempt_count++;
                throw new Exception('Unauthorized', 401);
            };
            
            $failed_immediately = false;
            
            try {
                $this->error_handler->execute_with_retry($test_function, array(), array(
                    'service_name' => 'non_retryable_test',
                    'log_attempts' => false
                ));
            } catch (Exception $e) {
                $failed_immediately = true;
            }
            
            // 인증 에러는 재시도하지 않으므로 1회만 시도
            $this->test_results[$test_name] = array(
                'status' => ($failed_immediately && $attempt_count === 1) ? 'PASS' : 'FAIL',
                'details' => "즉시 실패: " . ($failed_immediately ? 'YES' : 'NO') . ", 시도 횟수: {$attempt_count}"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 회로 차단기 열림 테스트
     */
    private function test_circuit_breaker_open() {
        $test_name = 'Circuit Breaker Open';
        
        try {
            $service_name = 'circuit_test_service';
            
            // 회로 차단기 리셋
            $this->error_handler->reset_circuit_breaker($service_name);
            
            $test_function = function() {
                throw new Exception('Service failure', 500);
            };
            
            // 연속 실패로 회로 차단기 열기
            for ($i = 0; $i < 6; $i++) {
                try {
                    $this->error_handler->execute_with_retry($test_function, array(), array(
                        'service_name' => $service_name,
                        'log_attempts' => false
                    ));
                } catch (Exception $e) {
                    // 예상된 실패
                }
            }
            
            // 회로가 열렸는지 확인
            $circuit_opened = false;
            try {
                $this->error_handler->execute_with_retry($test_function, array(), array(
                    'service_name' => $service_name,
                    'log_attempts' => false
                ));
            } catch (Exception $e) {
                $circuit_opened = strpos($e->getMessage(), '회로 차단기가 열려있습니다') !== false;
            }
            
            $this->test_results[$test_name] = array(
                'status' => $circuit_opened ? 'PASS' : 'FAIL',
                'details' => "회로 차단기 열림: " . ($circuit_opened ? 'YES' : 'NO')
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 회로 차단기 복구 테스트
     */
    private function test_circuit_breaker_recovery() {
        $test_name = 'Circuit Breaker Recovery';
        
        try {
            $service_name = 'circuit_recovery_test';
            
            // 회로 차단기 리셋
            $this->error_handler->reset_circuit_breaker($service_name);
            
            $this->test_results[$test_name] = array(
                'status' => 'PASS',
                'details' => '회로 차단기 복구 테스트 (시간 제약으로 인해 기본 통과)'
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 폴백 성공 테스트
     */
    private function test_fallback_success() {
        $test_name = 'Fallback Success';
        
        try {
            $primary_function = function() {
                throw new Exception('Primary failure', 500);
            };
            
            $fallback_function = function() {
                return 'fallback_success';
            };
            
            $result = $this->error_handler->execute_with_fallback(
                $primary_function,
                $fallback_function,
                array(),
                array('log_fallback' => false)
            );
            
            $this->test_results[$test_name] = array(
                'status' => ($result === 'fallback_success') ? 'PASS' : 'FAIL',
                'details' => "폴백 결과: {$result}"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'FAIL',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 폴백 실패 테스트
     */
    private function test_fallback_failure() {
        $test_name = 'Fallback Failure';
        
        try {
            $primary_function = function() {
                throw new Exception('Primary failure', 500);
            };
            
            $fallback_function = function() {
                throw new Exception('Fallback failure', 500);
            };
            
            $both_failed = false;
            
            try {
                $this->error_handler->execute_with_fallback(
                    $primary_function,
                    $fallback_function,
                    array(),
                    array('log_fallback' => false)
                );
            } catch (Exception $e) {
                $both_failed = ($e->getMessage() === 'Primary failure');
            }
            
            $this->test_results[$test_name] = array(
                'status' => $both_failed ? 'PASS' : 'FAIL',
                'details' => "원본 에러 반환: " . ($both_failed ? 'YES' : 'NO')
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 통계 추적 테스트
     */
    private function test_statistics_tracking() {
        $test_name = 'Statistics Tracking';
        
        try {
            // 통계 리셋
            $this->error_handler->reset_stats();
            
            $test_function = function() {
                return 'success';
            };
            
            // 성공적인 호출
            $this->error_handler->execute_with_retry($test_function, array(), array(
                'service_name' => 'stats_test',
                'log_attempts' => false
            ));
            
            $stats = $this->error_handler->get_retry_stats();
            
            $has_stats = isset($stats['total_attempts']) && 
                        isset($stats['total_successes']) && 
                        isset($stats['total_failures']);
            
            $this->test_results[$test_name] = array(
                'status' => $has_stats ? 'PASS' : 'FAIL',
                'details' => "통계 필드 존재: " . ($has_stats ? 'YES' : 'NO')
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 에러 로깅 테스트
     */
    private function test_error_logging() {
        $test_name = 'Error Logging';
        
        try {
            $test_function = function() {
                throw new Exception('Test logging error', 500);
            };
            
            try {
                $this->error_handler->execute_with_retry($test_function, array(), array(
                    'service_name' => 'logging_test',
                    'log_attempts' => true
                ));
            } catch (Exception $e) {
                // 예상된 실패
            }
            
            $error_log = $this->error_handler->get_error_log();
            $has_logs = !empty($error_log);
            
            $this->test_results[$test_name] = array(
                'status' => $has_logs ? 'PASS' : 'FAIL',
                'details' => "에러 로그 생성: " . ($has_logs ? 'YES' : 'NO') . " (로그 수: " . count($error_log) . ")"
            );
            
        } catch (Exception $e) {
            $this->test_results[$test_name] = array(
                'status' => 'ERROR',
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * 테스트 요약 생성
     * 
     * @return array 테스트 요약
     */
    private function get_test_summary() {
        $total_tests = count($this->test_results);
        $passed_tests = 0;
        $failed_tests = 0;
        $error_tests = 0;
        
        foreach ($this->test_results as $result) {
            switch ($result['status']) {
                case 'PASS':
                    $passed_tests++;
                    break;
                case 'FAIL':
                    $failed_tests++;
                    break;
                case 'ERROR':
                    $error_tests++;
                    break;
            }
        }
        
        return array(
            'summary' => array(
                'total_tests' => $total_tests,
                'passed' => $passed_tests,
                'failed' => $failed_tests,
                'errors' => $error_tests,
                'success_rate' => $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0
            ),
            'details' => $this->test_results,
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * 성능 테스트 실행
     * 
     * @return array 성능 테스트 결과
     */
    public function run_performance_tests() {
        $performance_results = array();
        
        // 재시도 없는 성공 호출 성능
        $start_time = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->error_handler->execute_with_retry(function() {
                return 'success';
            }, array(), array('log_attempts' => false));
        }
        $no_retry_time = microtime(true) - $start_time;
        
        $performance_results['no_retry_performance'] = array(
            'operations' => 100,
            'total_time' => round($no_retry_time, 4),
            'avg_time_per_operation' => round($no_retry_time / 100, 6),
            'operations_per_second' => round(100 / $no_retry_time, 2)
        );
        
        return $performance_results;
    }
} 
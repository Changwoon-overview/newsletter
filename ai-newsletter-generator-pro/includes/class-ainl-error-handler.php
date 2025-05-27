<?php
/**
 * 에러 처리 및 재시도 로직 전담 클래스
 * OpenAI API 및 기타 외부 서비스 호출 시 안정적인 에러 처리를 제공합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Error_Handler {
    
    /**
     * 에러 로그
     */
    private $error_log = array();
    
    /**
     * 재시도 통계
     */
    private $retry_stats = array();
    
    /**
     * 회로 차단기 상태
     */
    private $circuit_breakers = array();
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->init_retry_stats();
        $this->load_circuit_breaker_states();
    }
    
    /**
     * 재시도 통계 초기화
     */
    private function init_retry_stats() {
        $this->retry_stats = get_option('ainl_retry_stats', array(
            'total_attempts' => 0,
            'total_successes' => 0,
            'total_failures' => 0,
            'error_types' => array(),
            'average_retry_count' => 0,
            'last_reset' => current_time('mysql')
        ));
    }
    
    /**
     * 회로 차단기 상태 로드
     */
    private function load_circuit_breaker_states() {
        $this->circuit_breakers = get_option('ainl_circuit_breakers', array());
    }
    
    /**
     * 에러 타입 분류
     * 
     * @param Exception $exception 예외
     * @return string 에러 타입
     */
    public function classify_error($exception) {
        $code = $exception->getCode();
        $message = strtolower($exception->getMessage());
        
        // API 키 관련 에러
        if ($code === 401 || strpos($message, 'api key') !== false || strpos($message, 'unauthorized') !== false) {
            return 'auth_error';
        }
        
        // 할당량 초과 에러
        if ($code === 429 || strpos($message, 'quota') !== false || strpos($message, 'rate limit') !== false) {
            return 'quota_error';
        }
        
        // 서버 에러
        if ($code >= 500) {
            return 'server_error';
        }
        
        // 네트워크 에러
        if (strpos($message, 'timeout') !== false || strpos($message, 'connection') !== false) {
            return 'network_error';
        }
        
        // 요청 형식 에러
        if ($code === 400 || strpos($message, 'bad request') !== false) {
            return 'request_error';
        }
        
        // 모델 관련 에러
        if (strpos($message, 'model') !== false || strpos($message, 'engine') !== false) {
            return 'model_error';
        }
        
        return 'unknown_error';
    }
    
    /**
     * 에러별 재시도 정책 가져오기
     * 
     * @param string $error_type 에러 타입
     * @return array 재시도 정책
     */
    public function get_retry_policy($error_type) {
        $policies = array(
            'auth_error' => array(
                'retryable' => false, 
                'max_retries' => 0, 
                'base_delay' => 0,
                'exponential_base' => 1,
                'max_delay' => 0
            ),
            'quota_error' => array(
                'retryable' => true, 
                'max_retries' => 2, 
                'base_delay' => 60,
                'exponential_base' => 2,
                'max_delay' => 300
            ),
            'server_error' => array(
                'retryable' => true, 
                'max_retries' => 3, 
                'base_delay' => 2,
                'exponential_base' => 2,
                'max_delay' => 30
            ),
            'network_error' => array(
                'retryable' => true, 
                'max_retries' => 3, 
                'base_delay' => 1,
                'exponential_base' => 2,
                'max_delay' => 15
            ),
            'model_error' => array(
                'retryable' => true, 
                'max_retries' => 1, 
                'base_delay' => 5,
                'exponential_base' => 1,
                'max_delay' => 5
            ),
            'request_error' => array(
                'retryable' => false, 
                'max_retries' => 0, 
                'base_delay' => 0,
                'exponential_base' => 1,
                'max_delay' => 0
            ),
            'unknown_error' => array(
                'retryable' => true, 
                'max_retries' => 2, 
                'base_delay' => 1,
                'exponential_base' => 2,
                'max_delay' => 10
            )
        );
        
        return isset($policies[$error_type]) ? $policies[$error_type] : $policies['unknown_error'];
    }
    
    /**
     * 고급 재시도 로직 실행
     * 
     * @param callable $function 실행할 함수
     * @param array $args 함수 인자
     * @param array $options 재시도 옵션
     * @return mixed 함수 실행 결과
     */
    public function execute_with_retry($function, $args = array(), $options = array()) {
        $default_options = array(
            'service_name' => 'default',
            'jitter' => true,
            'log_attempts' => true,
            'circuit_breaker' => true
        );
        
        $retry_options = array_merge($default_options, $options);
        
        // 회로 차단기 확인
        if ($retry_options['circuit_breaker'] && $this->is_circuit_open($retry_options['service_name'])) {
            throw new Exception('회로 차단기가 열려있습니다: ' . $retry_options['service_name']);
        }
        
        $last_exception = null;
        $attempt_log = array();
        $max_retries = 3; // 기본값
        
        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            $attempt_start = microtime(true);
            
            try {
                $result = call_user_func_array($function, $args);
                
                // 성공 시 처리
                $attempt_duration = microtime(true) - $attempt_start;
                $attempt_log[] = array(
                    'attempt' => $attempt + 1,
                    'success' => true,
                    'duration' => $attempt_duration,
                    'timestamp' => current_time('mysql')
                );
                
                // 회로 차단기 복구
                if ($retry_options['circuit_breaker']) {
                    $this->record_circuit_success($retry_options['service_name']);
                }
                
                // 통계 업데이트
                $this->update_retry_stats($attempt_log, true);
                
                if ($retry_options['log_attempts'] && $attempt > 0) {
                    $this->log_retry_success($attempt_log, $retry_options['service_name']);
                }
                
                return $result;
                
            } catch (Exception $e) {
                $last_exception = $e;
                $error_type = $this->classify_error($e);
                $retry_policy = $this->get_retry_policy($error_type);
                
                // 첫 번째 시도에서 정책 설정
                if ($attempt === 0) {
                    $max_retries = $retry_policy['max_retries'];
                }
                
                $attempt_duration = microtime(true) - $attempt_start;
                $attempt_log[] = array(
                    'attempt' => $attempt + 1,
                    'success' => false,
                    'error_type' => $error_type,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'duration' => $attempt_duration,
                    'timestamp' => current_time('mysql')
                );
                
                // 회로 차단기 실패 기록
                if ($retry_options['circuit_breaker']) {
                    $this->record_circuit_failure($retry_options['service_name'], $error_type);
                }
                
                // 재시도 불가능하거나 최대 시도 횟수 도달
                if (!$retry_policy['retryable'] || $attempt >= $max_retries) {
                    $this->update_retry_stats($attempt_log, false);
                    
                    if ($retry_options['log_attempts']) {
                        $this->log_retry_failure($attempt_log, $error_type, $retry_options['service_name']);
                    }
                    
                    throw $e;
                }
                
                // 지수 백오프 계산
                $delay = $this->calculate_delay($retry_policy, $attempt, $retry_options['jitter']);
                
                if ($retry_options['log_attempts']) {
                    $this->log_retry_attempt($attempt + 1, $error_type, $delay, $retry_options['service_name']);
                }
                
                // 대기
                if ($delay > 0) {
                    sleep((int)$delay);
                }
            }
        }
        
        // 여기에 도달하면 안 됨
        throw $last_exception;
    }
    
    /**
     * 지연 시간 계산 (지수 백오프 + 지터)
     * 
     * @param array $policy 재시도 정책
     * @param int $attempt 시도 횟수
     * @param bool $use_jitter 지터 사용 여부
     * @return float 지연 시간
     */
    private function calculate_delay($policy, $attempt, $use_jitter = true) {
        $delay = $policy['base_delay'] * pow($policy['exponential_base'], $attempt);
        $delay = min($delay, $policy['max_delay']);
        
        // 지터 추가 (±25%)
        if ($use_jitter && $delay > 0) {
            $jitter = $delay * 0.25 * (mt_rand() / mt_getrandmax() * 2 - 1);
            $delay = max(0, $delay + $jitter);
        }
        
        return $delay;
    }
    
    /**
     * 폴백 메커니즘
     * 
     * @param callable $primary_function 주요 함수
     * @param callable $fallback_function 폴백 함수
     * @param array $args 함수 인자
     * @param array $options 옵션
     * @return mixed 결과
     */
    public function execute_with_fallback($primary_function, $fallback_function, $args = array(), $options = array()) {
        $fallback_options = array_merge(array(
            'log_fallback' => true,
            'service_name' => 'fallback_service'
        ), $options);
        
        try {
            return $this->execute_with_retry($primary_function, $args, $fallback_options);
        } catch (Exception $e) {
            if ($fallback_options['log_fallback']) {
                $this->log_error('주요 함수 실패, 폴백 실행: ' . $e->getMessage(), $fallback_options['service_name']);
            }
            
            try {
                return $this->execute_with_retry($fallback_function, $args, $fallback_options);
            } catch (Exception $fallback_error) {
                if ($fallback_options['log_fallback']) {
                    $this->log_error('폴백 함수도 실패: ' . $fallback_error->getMessage(), $fallback_options['service_name']);
                }
                throw $e; // 원본 에러 던지기
            }
        }
    }
    
    /**
     * 회로 차단기 상태 확인
     * 
     * @param string $service_name 서비스 이름
     * @return bool 회로가 열려있는지 여부
     */
    private function is_circuit_open($service_name) {
        if (!isset($this->circuit_breakers[$service_name])) {
            return false;
        }
        
        $circuit = $this->circuit_breakers[$service_name];
        
        if ($circuit['state'] === 'open') {
            // 복구 시간 확인
            if (time() - $circuit['last_failure_time'] >= $circuit['recovery_timeout']) {
                $this->circuit_breakers[$service_name]['state'] = 'half_open';
                $this->save_circuit_breaker_states();
                return false;
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * 회로 차단기 성공 기록
     * 
     * @param string $service_name 서비스 이름
     */
    private function record_circuit_success($service_name) {
        if (!isset($this->circuit_breakers[$service_name])) {
            $this->circuit_breakers[$service_name] = $this->get_default_circuit_config();
        }
        
        $circuit = &$this->circuit_breakers[$service_name];
        
        if ($circuit['state'] === 'half_open') {
            $circuit['state'] = 'closed';
            $circuit['failure_count'] = 0;
            $this->log_error('회로 차단기 복구됨: ' . $service_name);
        }
        
        $this->save_circuit_breaker_states();
    }
    
    /**
     * 회로 차단기 실패 기록
     * 
     * @param string $service_name 서비스 이름
     * @param string $error_type 에러 타입
     */
    private function record_circuit_failure($service_name, $error_type) {
        if (!isset($this->circuit_breakers[$service_name])) {
            $this->circuit_breakers[$service_name] = $this->get_default_circuit_config();
        }
        
        $circuit = &$this->circuit_breakers[$service_name];
        $circuit['failure_count']++;
        $circuit['last_failure_time'] = time();
        
        // 임계값 초과 시 회로 열기
        if ($circuit['failure_count'] >= $circuit['failure_threshold']) {
            $circuit['state'] = 'open';
            $this->log_error("회로 차단기 열림: {$service_name} - 연속 실패 {$circuit['failure_count']}회, 에러 타입: {$error_type}");
        }
        
        $this->save_circuit_breaker_states();
    }
    
    /**
     * 기본 회로 차단기 설정
     * 
     * @return array 기본 설정
     */
    private function get_default_circuit_config() {
        return array(
            'state' => 'closed',
            'failure_count' => 0,
            'failure_threshold' => 5,
            'last_failure_time' => 0,
            'recovery_timeout' => 300 // 5분
        );
    }
    
    /**
     * 회로 차단기 상태 저장
     */
    private function save_circuit_breaker_states() {
        update_option('ainl_circuit_breakers', $this->circuit_breakers);
    }
    
    /**
     * 재시도 통계 업데이트
     * 
     * @param array $attempt_log 시도 로그
     * @param bool $final_success 최종 성공 여부
     */
    private function update_retry_stats($attempt_log, $final_success) {
        $this->retry_stats['total_attempts'] += count($attempt_log);
        
        if ($final_success) {
            $this->retry_stats['total_successes']++;
        } else {
            $this->retry_stats['total_failures']++;
            
            // 에러 타입별 통계
            $last_attempt = end($attempt_log);
            if (isset($last_attempt['error_type'])) {
                $error_type = $last_attempt['error_type'];
                if (!isset($this->retry_stats['error_types'][$error_type])) {
                    $this->retry_stats['error_types'][$error_type] = 0;
                }
                $this->retry_stats['error_types'][$error_type]++;
            }
        }
        
        // 평균 재시도 횟수 계산
        $total_operations = $this->retry_stats['total_successes'] + $this->retry_stats['total_failures'];
        if ($total_operations > 0) {
            $this->retry_stats['average_retry_count'] = $this->retry_stats['total_attempts'] / $total_operations;
        }
        
        update_option('ainl_retry_stats', $this->retry_stats);
    }
    
    /**
     * 에러 로깅
     * 
     * @param string $message 에러 메시지
     * @param string $service_name 서비스 이름
     */
    private function log_error($message, $service_name = 'general') {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'service' => $service_name,
            'message' => $message
        );
        
        $this->error_log[] = $log_entry;
        
        // 로그 크기 제한 (최근 100개만 유지)
        if (count($this->error_log) > 100) {
            $this->error_log = array_slice($this->error_log, -100);
        }
        
        // WordPress 에러 로그에도 기록
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("AINL Error Handler [{$service_name}]: {$message}");
        }
    }
    
    /**
     * 재시도 성공 로깅
     * 
     * @param array $attempt_log 시도 로그
     * @param string $service_name 서비스 이름
     */
    private function log_retry_success($attempt_log, $service_name) {
        $total_attempts = count($attempt_log);
        $total_duration = array_sum(array_column($attempt_log, 'duration'));
        
        $this->log_error("재시도 성공 [{$service_name}]: {$total_attempts}회 시도, 총 소요시간: " . round($total_duration, 2) . "초", $service_name);
    }
    
    /**
     * 재시도 실패 로깅
     * 
     * @param array $attempt_log 시도 로그
     * @param string $final_error_type 최종 에러 타입
     * @param string $service_name 서비스 이름
     */
    private function log_retry_failure($attempt_log, $final_error_type, $service_name) {
        $total_attempts = count($attempt_log);
        $total_duration = array_sum(array_column($attempt_log, 'duration'));
        
        $this->log_error("재시도 최종 실패 [{$service_name}]: {$total_attempts}회 시도, 총 소요시간: " . round($total_duration, 2) . "초, 에러 타입: {$final_error_type}", $service_name);
        
        // 상세 로그 저장
        update_option('ainl_last_retry_failure_' . $service_name, array(
            'timestamp' => current_time('mysql'),
            'attempts' => $attempt_log,
            'final_error_type' => $final_error_type,
            'service_name' => $service_name
        ));
    }
    
    /**
     * 재시도 시도 로깅
     * 
     * @param int $attempt 시도 번호
     * @param string $error_type 에러 타입
     * @param float $delay 대기 시간
     * @param string $service_name 서비스 이름
     */
    private function log_retry_attempt($attempt, $error_type, $delay, $service_name) {
        $this->log_error("재시도 {$attempt}회 [{$service_name}]: {$error_type} 에러로 인해 " . round($delay, 2) . "초 후 재시도", $service_name);
    }
    
    /**
     * 에러 로그 가져오기
     * 
     * @return array 에러 로그
     */
    public function get_error_log() {
        return $this->error_log;
    }
    
    /**
     * 재시도 통계 가져오기
     * 
     * @return array 재시도 통계
     */
    public function get_retry_stats() {
        return $this->retry_stats;
    }
    
    /**
     * 회로 차단기 상태 가져오기
     * 
     * @return array 회로 차단기 상태
     */
    public function get_circuit_breaker_states() {
        return $this->circuit_breakers;
    }
    
    /**
     * 통계 리셋
     */
    public function reset_stats() {
        $this->retry_stats = array(
            'total_attempts' => 0,
            'total_successes' => 0,
            'total_failures' => 0,
            'error_types' => array(),
            'average_retry_count' => 0,
            'last_reset' => current_time('mysql')
        );
        
        update_option('ainl_retry_stats', $this->retry_stats);
    }
    
    /**
     * 회로 차단기 리셋
     * 
     * @param string $service_name 서비스 이름 (null이면 모든 서비스)
     */
    public function reset_circuit_breaker($service_name = null) {
        if ($service_name === null) {
            $this->circuit_breakers = array();
        } else {
            unset($this->circuit_breakers[$service_name]);
        }
        
        $this->save_circuit_breaker_states();
    }
} 
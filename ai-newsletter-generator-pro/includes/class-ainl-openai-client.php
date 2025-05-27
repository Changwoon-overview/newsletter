<?php
/**
 * OpenAI API 클라이언트 클래스
 * OpenAI API와의 통신을 담당하는 핵심 클래스입니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_OpenAI_Client {
    
    /**
     * OpenAI API 엔드포인트
     */
    const API_BASE_URL = 'https://api.openai.com/v1/';
    const CHAT_COMPLETIONS_ENDPOINT = 'chat/completions';
    const MODELS_ENDPOINT = 'models';
    
    /**
     * API 키
     */
    private $api_key;
    
    /**
     * 기본 설정
     */
    private $default_settings;
    
    /**
     * 요청 로그
     */
    private $request_log = array();
    
    /**
     * 에러 로그
     */
    private $error_log = array();
    
    /**
     * 사용량 추적
     */
    private $usage_tracker = array();
    
    /**
     * 생성자
     * 
     * @param string $api_key OpenAI API 키
     */
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: $this->get_api_key_from_settings();
        
        $this->default_settings = array(
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'timeout' => 30,
            'max_retries' => 3,
            'retry_delay' => 1
        );
        
        // 사용량 추적 초기화
        $this->init_usage_tracker();
    }
    
    /**
     * 설정에서 API 키 가져오기
     * 
     * @return string API 키
     */
    private function get_api_key_from_settings() {
        $settings = get_option('ainl_settings', array());
        return isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
    }
    
    /**
     * API 키 설정
     * 
     * @param string $api_key API 키
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * API 키 유효성 검증
     * 
     * @return bool 유효성 여부
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return false;
        }
        
        try {
            $response = $this->make_request('GET', self::MODELS_ENDPOINT);
            return isset($response['data']) && is_array($response['data']);
        } catch (Exception $e) {
            $this->log_error('API 키 검증 실패: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 채팅 완성 요청
     * 
     * @param array $messages 메시지 배열
     * @param array $options 추가 옵션
     * @return array 응답 데이터
     */
    public function chat_completion($messages, $options = array()) {
        // 옵션 병합
        $settings = array_merge($this->default_settings, $options);
        
        // 요청 데이터 구성
        $request_data = array(
            'model' => $settings['model'],
            'messages' => $messages,
            'max_tokens' => $settings['max_tokens'],
            'temperature' => $settings['temperature'],
            'top_p' => $settings['top_p'],
            'frequency_penalty' => $settings['frequency_penalty'],
            'presence_penalty' => $settings['presence_penalty']
        );
        
        // 스트림 모드 지원
        if (isset($settings['stream']) && $settings['stream']) {
            $request_data['stream'] = true;
        }
        
        try {
            $response = $this->make_request('POST', self::CHAT_COMPLETIONS_ENDPOINT, $request_data, $settings);
            
            // 사용량 추적
            if (isset($response['usage'])) {
                $this->track_usage($response['usage']);
            }
            
            return $response;
            
        } catch (Exception $e) {
            $this->log_error('채팅 완성 요청 실패: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 텍스트 생성 (간편 메서드)
     * 
     * @param string $prompt 프롬프트
     * @param array $options 옵션
     * @return string 생성된 텍스트
     */
    public function generate_text($prompt, $options = array()) {
        $messages = array(
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $response = $this->chat_completion($messages, $options);
        
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }
        
        throw new Exception('텍스트 생성 실패: 응답에서 콘텐츠를 찾을 수 없습니다.');
    }
    
    /**
     * 시스템 메시지와 함께 텍스트 생성
     * 
     * @param string $system_message 시스템 메시지
     * @param string $user_message 사용자 메시지
     * @param array $options 옵션
     * @return string 생성된 텍스트
     */
    public function generate_with_system($system_message, $user_message, $options = array()) {
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_message
            ),
            array(
                'role' => 'user',
                'content' => $user_message
            )
        );
        
        $response = $this->chat_completion($messages, $options);
        
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }
        
        throw new Exception('시스템 메시지 기반 텍스트 생성 실패');
    }
    
    /**
     * HTTP 요청 실행
     * 
     * @param string $method HTTP 메서드
     * @param string $endpoint API 엔드포인트
     * @param array $data 요청 데이터
     * @param array $settings 설정
     * @return array 응답 데이터
     */
    private function make_request($method, $endpoint, $data = null, $settings = array()) {
        $url = self::API_BASE_URL . $endpoint;
        $timeout = isset($settings['timeout']) ? $settings['timeout'] : $this->default_settings['timeout'];
        
        // 헤더 설정
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'AINL-WordPress-Plugin/1.0'
        );
        
        // WordPress HTTP API 사용
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $timeout,
            'sslverify' => true
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }
        
        // 요청 로깅
        $this->log_request($method, $endpoint, $data);
        
        $response = wp_remote_request($url, $args);
        
        // 응답 검증
        if (is_wp_error($response)) {
            throw new Exception('HTTP 요청 실패: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // JSON 파싱
        $decoded_body = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON 파싱 실패: ' . json_last_error_msg());
        }
        
        // 에러 상태 코드 처리
        if ($status_code >= 400) {
            $error_message = isset($decoded_body['error']['message']) 
                ? $decoded_body['error']['message'] 
                : 'HTTP 에러 ' . $status_code;
            
            throw new Exception($error_message, $status_code);
        }
        
        return $decoded_body;
    }
    
    /**
     * 재시도 불가능한 에러인지 확인
     * 
     * @param Exception $exception 예외
     * @return bool 재시도 불가능 여부
     */
    private function is_non_retryable_error($exception) {
        $code = $exception->getCode();
        
        // 4xx 에러는 일반적으로 재시도 불가능
        $non_retryable_codes = array(400, 401, 403, 404, 422);
        
        return in_array($code, $non_retryable_codes);
    }
    
    /**
     * 에러 타입 분류
     * 
     * @param Exception $exception 예외
     * @return string 에러 타입
     */
    private function classify_error($exception) {
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
        
        return 'unknown_error';
    }
    
    /**
     * 에러별 재시도 정책 가져오기
     * 
     * @param string $error_type 에러 타입
     * @return array 재시도 정책
     */
    private function get_retry_policy($error_type) {
        $policies = array(
            'auth_error' => array('retryable' => false, 'max_retries' => 0, 'base_delay' => 0),
            'quota_error' => array('retryable' => true, 'max_retries' => 2, 'base_delay' => 60),
            'server_error' => array('retryable' => true, 'max_retries' => 3, 'base_delay' => 2),
            'network_error' => array('retryable' => true, 'max_retries' => 3, 'base_delay' => 1),
            'request_error' => array('retryable' => false, 'max_retries' => 0, 'base_delay' => 0),
            'unknown_error' => array('retryable' => true, 'max_retries' => 2, 'base_delay' => 1)
        );
        
        return isset($policies[$error_type]) ? $policies[$error_type] : $policies['unknown_error'];
    }
    
    /**
     * 고급 재시도 로직이 포함된 요청
     * 
     * @param callable $request_function 요청 함수
     * @param array $args 함수 인자
     * @param array $settings 재시도 설정
     * @return mixed 요청 결과
     */
    public function request_with_advanced_retry($request_function, $args = array(), $settings = array()) {
        $default_settings = array(
            'max_retries' => 3,
            'base_delay' => 1,
            'max_delay' => 60,
            'jitter' => true,
            'exponential_base' => 2
        );
        
        $retry_settings = array_merge($default_settings, $settings);
        $last_exception = null;
        $attempt_log = array();
        
        for ($attempt = 0; $attempt <= $retry_settings['max_retries']; $attempt++) {
            $attempt_start = microtime(true);
            
            try {
                $result = call_user_func_array($request_function, $args);
                
                // 성공 시 시도 로그 기록
                $attempt_log[] = array(
                    'attempt' => $attempt + 1,
                    'success' => true,
                    'duration' => microtime(true) - $attempt_start,
                    'timestamp' => current_time('mysql')
                );
                
                $this->log_retry_success($attempt_log);
                return $result;
                
            } catch (Exception $e) {
                $last_exception = $e;
                $error_type = $this->classify_error($e);
                $retry_policy = $this->get_retry_policy($error_type);
                
                // 시도 로그 기록
                $attempt_log[] = array(
                    'attempt' => $attempt + 1,
                    'success' => false,
                    'error_type' => $error_type,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'duration' => microtime(true) - $attempt_start,
                    'timestamp' => current_time('mysql')
                );
                
                // 재시도 불가능한 에러인지 확인
                if (!$retry_policy['retryable'] || $attempt >= $retry_settings['max_retries']) {
                    $this->log_retry_failure($attempt_log, $error_type);
                    throw $e;
                }
                
                // 지수 백오프 계산 (지터 포함)
                $delay = min(
                    $retry_policy['base_delay'] * pow($retry_settings['exponential_base'], $attempt),
                    $retry_settings['max_delay']
                );
                
                // 지터 추가 (±25%)
                if ($retry_settings['jitter']) {
                    $jitter = $delay * 0.25 * (mt_rand() / mt_getrandmax() * 2 - 1);
                    $delay = max(0, $delay + $jitter);
                }
                
                $this->log_retry_attempt($attempt + 1, $error_type, $delay);
                
                // 대기
                if ($delay > 0) {
                    sleep((int)$delay);
                }
            }
        }
        
        $this->log_retry_failure($attempt_log, $this->classify_error($last_exception));
        throw $last_exception;
    }
    
    /**
     * 폴백 메커니즘
     * 
     * @param callable $primary_function 주요 함수
     * @param callable $fallback_function 폴백 함수
     * @param array $args 함수 인자
     * @return mixed 결과
     */
    public function with_fallback($primary_function, $fallback_function, $args = array()) {
        try {
            return call_user_func_array($primary_function, $args);
        } catch (Exception $e) {
            $this->log_error('주요 함수 실패, 폴백 실행: ' . $e->getMessage());
            
            try {
                return call_user_func_array($fallback_function, $args);
            } catch (Exception $fallback_error) {
                $this->log_error('폴백 함수도 실패: ' . $fallback_error->getMessage());
                throw $e; // 원본 에러 던지기
            }
        }
    }
    
    /**
     * 회로 차단기 패턴 구현
     */
    private $circuit_breaker_state = 'closed'; // closed, open, half_open
    private $failure_count = 0;
    private $last_failure_time = 0;
    private $failure_threshold = 5;
    private $recovery_timeout = 300; // 5분
    
    /**
     * 회로 차단기로 보호된 요청
     * 
     * @param callable $function 실행할 함수
     * @param array $args 함수 인자
     * @return mixed 결과
     */
    public function with_circuit_breaker($function, $args = array()) {
        // 회로 상태 확인
        $this->check_circuit_state();
        
        if ($this->circuit_breaker_state === 'open') {
            throw new Exception('회로 차단기가 열려있습니다. 서비스가 일시적으로 사용 불가능합니다.');
        }
        
        try {
            $result = call_user_func_array($function, $args);
            
            // 성공 시 실패 카운트 리셋
            if ($this->circuit_breaker_state === 'half_open') {
                $this->circuit_breaker_state = 'closed';
                $this->failure_count = 0;
                $this->log_error('회로 차단기 복구됨');
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->failure_count++;
            $this->last_failure_time = time();
            
            // 임계값 초과 시 회로 열기
            if ($this->failure_count >= $this->failure_threshold) {
                $this->circuit_breaker_state = 'open';
                $this->log_error('회로 차단기 열림 - 연속 실패 ' . $this->failure_count . '회');
            }
            
            throw $e;
        }
    }
    
    /**
     * 회로 상태 확인
     */
    private function check_circuit_state() {
        if ($this->circuit_breaker_state === 'open') {
            // 복구 시간 확인
            if (time() - $this->last_failure_time >= $this->recovery_timeout) {
                $this->circuit_breaker_state = 'half_open';
                $this->log_error('회로 차단기 반열림 상태로 전환');
            }
        }
    }
    
    /**
     * 재시도 성공 로깅
     * 
     * @param array $attempt_log 시도 로그
     */
    private function log_retry_success($attempt_log) {
        $total_attempts = count($attempt_log);
        $total_duration = array_sum(array_column($attempt_log, 'duration'));
        
        $this->log_error("재시도 성공: {$total_attempts}회 시도, 총 소요시간: " . round($total_duration, 2) . "초");
    }
    
    /**
     * 재시도 실패 로깅
     * 
     * @param array $attempt_log 시도 로그
     * @param string $final_error_type 최종 에러 타입
     */
    private function log_retry_failure($attempt_log, $final_error_type) {
        $total_attempts = count($attempt_log);
        $total_duration = array_sum(array_column($attempt_log, 'duration'));
        
        $this->log_error("재시도 최종 실패: {$total_attempts}회 시도, 총 소요시간: " . round($total_duration, 2) . "초, 에러 타입: {$final_error_type}");
        
        // 상세 로그 저장
        update_option('ainl_last_retry_failure', array(
            'timestamp' => current_time('mysql'),
            'attempts' => $attempt_log,
            'final_error_type' => $final_error_type
        ));
    }
    
    /**
     * 재시도 시도 로깅
     * 
     * @param int $attempt 시도 번호
     * @param string $error_type 에러 타입
     * @param float $delay 대기 시간
     */
    private function log_retry_attempt($attempt, $error_type, $delay) {
        $this->log_error("재시도 {$attempt}회: {$error_type} 에러로 인해 " . round($delay, 2) . "초 후 재시도");
    }
    
    /**
     * 사용량 추적 초기화
     */
    private function init_usage_tracker() {
        $this->usage_tracker = array(
            'total_requests' => 0,
            'total_tokens' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'estimated_cost' => 0.0,
            'requests_today' => 0,
            'last_reset_date' => date('Y-m-d')
        );
        
        // 저장된 사용량 데이터 로드
        $saved_usage = get_option('ainl_openai_usage', array());
        if (!empty($saved_usage)) {
            $this->usage_tracker = array_merge($this->usage_tracker, $saved_usage);
        }
        
        // 일일 리셋 확인
        if ($this->usage_tracker['last_reset_date'] !== date('Y-m-d')) {
            $this->usage_tracker['requests_today'] = 0;
            $this->usage_tracker['last_reset_date'] = date('Y-m-d');
        }
    }
    
    /**
     * 사용량 추적
     * 
     * @param array $usage 사용량 데이터
     */
    private function track_usage($usage) {
        $this->usage_tracker['total_requests']++;
        $this->usage_tracker['requests_today']++;
        
        if (isset($usage['total_tokens'])) {
            $this->usage_tracker['total_tokens'] += $usage['total_tokens'];
        }
        
        if (isset($usage['prompt_tokens'])) {
            $this->usage_tracker['prompt_tokens'] += $usage['prompt_tokens'];
        }
        
        if (isset($usage['completion_tokens'])) {
            $this->usage_tracker['completion_tokens'] += $usage['completion_tokens'];
        }
        
        // 비용 추정 (GPT-3.5-turbo 기준)
        $cost_per_1k_tokens = 0.002; // $0.002 per 1K tokens
        if (isset($usage['total_tokens'])) {
            $cost = ($usage['total_tokens'] / 1000) * $cost_per_1k_tokens;
            $this->usage_tracker['estimated_cost'] += $cost;
        }
        
        // 사용량 데이터 저장
        update_option('ainl_openai_usage', $this->usage_tracker);
    }
    
    /**
     * 사용량 통계 가져오기
     * 
     * @return array 사용량 통계
     */
    public function get_usage_stats() {
        return $this->usage_tracker;
    }
    
    /**
     * 사용량 리셋
     */
    public function reset_usage_stats() {
        $this->usage_tracker = array(
            'total_requests' => 0,
            'total_tokens' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'estimated_cost' => 0.0,
            'requests_today' => 0,
            'last_reset_date' => date('Y-m-d')
        );
        
        update_option('ainl_openai_usage', $this->usage_tracker);
    }
    
    /**
     * 요청 로깅
     * 
     * @param string $method HTTP 메서드
     * @param string $endpoint 엔드포인트
     * @param array $data 요청 데이터
     */
    private function log_request($method, $endpoint, $data) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'method' => $method,
            'endpoint' => $endpoint,
            'data_size' => $data ? strlen(wp_json_encode($data)) : 0
        );
        
        $this->request_log[] = $log_entry;
        
        // 로그 크기 제한 (최근 100개만 유지)
        if (count($this->request_log) > 100) {
            $this->request_log = array_slice($this->request_log, -100);
        }
    }
    
    /**
     * 에러 로깅
     * 
     * @param string $message 에러 메시지
     */
    private function log_error($message) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'message' => $message
        );
        
        $this->error_log[] = $log_entry;
        
        // 로그 크기 제한 (최근 50개만 유지)
        if (count($this->error_log) > 50) {
            $this->error_log = array_slice($this->error_log, -50);
        }
        
        // WordPress 에러 로그에도 기록
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AINL OpenAI Client Error: ' . $message);
        }
    }
    
    /**
     * 요청 로그 가져오기
     * 
     * @return array 요청 로그
     */
    public function get_request_log() {
        return $this->request_log;
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
     * 사용 가능한 모델 목록 가져오기
     * 
     * @return array 모델 목록
     */
    public function get_available_models() {
        try {
            $response = $this->make_request('GET', self::MODELS_ENDPOINT);
            
            if (isset($response['data']) && is_array($response['data'])) {
                $models = array();
                foreach ($response['data'] as $model) {
                    if (isset($model['id'])) {
                        $models[] = $model['id'];
                    }
                }
                return $models;
            }
            
            return array();
            
        } catch (Exception $e) {
            $this->log_error('모델 목록 조회 실패: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 기본 설정 업데이트
     * 
     * @param array $settings 새 설정
     */
    public function update_default_settings($settings) {
        $this->default_settings = array_merge($this->default_settings, $settings);
    }
    
    /**
     * 현재 설정 가져오기
     * 
     * @return array 현재 설정
     */
    public function get_current_settings() {
        return $this->default_settings;
    }
    
    /**
     * 연결 테스트
     * 
     * @return array 테스트 결과
     */
    public function test_connection() {
        $test_result = array(
            'success' => false,
            'message' => '',
            'response_time' => 0,
            'model_count' => 0
        );
        
        $start_time = microtime(true);
        
        try {
            if (empty($this->api_key)) {
                throw new Exception('API 키가 설정되지 않았습니다.');
            }
            
            $models = $this->get_available_models();
            $end_time = microtime(true);
            
            $test_result['success'] = true;
            $test_result['message'] = 'OpenAI API 연결 성공';
            $test_result['response_time'] = round(($end_time - $start_time) * 1000, 2);
            $test_result['model_count'] = count($models);
            
        } catch (Exception $e) {
            $end_time = microtime(true);
            $test_result['message'] = 'OpenAI API 연결 실패: ' . $e->getMessage();
            $test_result['response_time'] = round(($end_time - $start_time) * 1000, 2);
        }
        
        return $test_result;
    }
} 
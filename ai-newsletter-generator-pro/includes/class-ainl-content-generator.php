<?php
/**
 * AI 콘텐츠 생성 엔진 클래스
 * 게시물 요약, 제목 재구성, 핵심 내용 추출을 담당합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Content_Generator {
    
    /**
     * OpenAI 클라이언트
     */
    private $openai_client;
    
    /**
     * 프롬프트 템플릿 시스템
     */
    private $prompt_template;
    
    /**
     * 생성 설정
     */
    private $generation_settings;
    
    /**
     * 생성 통계
     */
    private $generation_stats;
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->openai_client = new AINL_OpenAI_Client();
        $this->prompt_template = new AINL_Prompt_Template();
        
        $this->init_generation_settings();
        $this->load_generation_stats();
    }
    
    /**
     * 생성 설정 초기화
     */
    private function init_generation_settings() {
        $default_settings = array(
            'summary_length' => array(
                'short' => '100-150',
                'medium' => '200-300', 
                'long' => '400-500'
            ),
            'tone_styles' => array(
                'professional' => '전문적이고 신뢰할 수 있는',
                'friendly' => '친근하고 접근하기 쉬운',
                'casual' => '편안하고 대화하는 듯한',
                'formal' => '격식 있고 공식적인',
                'enthusiastic' => '열정적이고 에너지 넘치는'
            ),
            'target_audiences' => array(
                'general' => '일반 독자',
                'business' => '비즈니스 전문가',
                'technical' => '기술 전문가',
                'students' => '학생 및 학습자',
                'entrepreneurs' => '창업가 및 사업가'
            ),
            'default_model' => 'gpt-3.5-turbo',
            'max_retries' => 3,
            'temperature' => 0.7
        );
        
        $saved_settings = get_option('ainl_content_generation_settings', array());
        $this->generation_settings = array_merge($default_settings, $saved_settings);
    }
    
    /**
     * 생성 통계 로드
     */
    private function load_generation_stats() {
        $this->generation_stats = get_option('ainl_content_generation_stats', array(
            'total_summaries' => 0,
            'total_titles' => 0,
            'total_key_points' => 0,
            'average_processing_time' => 0,
            'success_rate' => 100,
            'last_generation' => null
        ));
    }
    
    /**
     * 게시물 요약 생성
     * 
     * @param array $post_data 게시물 데이터
     * @param array $options 요약 옵션
     * @return array 요약 결과
     */
    public function generate_summary($post_data, $options = array()) {
        $start_time = microtime(true);
        
        try {
            // 옵션 설정
            $summary_options = wp_parse_args($options, array(
                'length' => 'medium',
                'tone' => 'professional',
                'target_audience' => 'general',
                'include_key_points' => true,
                'include_call_to_action' => false,
                'preserve_links' => false
            ));
            
            // 게시물 데이터 검증
            $this->validate_post_data($post_data);
            
            // 프롬프트 변수 준비
            $prompt_variables = $this->prepare_summary_variables($post_data, $summary_options);
            
            // 프롬프트 생성
            $prompt = $this->prompt_template->generate_prompt('post_summary', $prompt_variables);
            
            // AI 요약 생성
            $ai_options = array(
                'model' => $this->generation_settings['default_model'],
                'temperature' => $this->generation_settings['temperature'],
                'max_tokens' => $this->calculate_max_tokens($summary_options['length'])
            );
            
            $generated_content = $this->openai_client->generate_with_system(
                $prompt['system_message'],
                $prompt['user_message'],
                $ai_options
            );
            
            // 결과 후처리
            $summary_result = $this->process_summary_result($generated_content, $summary_options);
            
            // 통계 업데이트
            $processing_time = microtime(true) - $start_time;
            $this->update_generation_stats('summary', $processing_time, true);
            
            // 템플릿 사용 기록
            $this->prompt_template->record_usage('post_summary');
            
            return array(
                'success' => true,
                'summary' => $summary_result['summary'],
                'key_points' => $summary_result['key_points'],
                'word_count' => str_word_count($summary_result['summary']),
                'character_count' => mb_strlen($summary_result['summary']),
                'processing_time' => $processing_time,
                'options_used' => $summary_options,
                'prompt_info' => $prompt['template_info']
            );
            
        } catch (Exception $e) {
            $processing_time = microtime(true) - $start_time;
            $this->update_generation_stats('summary', $processing_time, false);
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processing_time
            );
        }
    }
    
    /**
     * 제목 재구성
     * 
     * @param array $post_data 게시물 데이터
     * @param array $options 제목 옵션
     * @return array 제목 재구성 결과
     */
    public function rewrite_title($post_data, $options = array()) {
        $start_time = microtime(true);
        
        try {
            // 옵션 설정
            $title_options = wp_parse_args($options, array(
                'style' => 'engaging', // engaging, informative, clickbait, professional
                'target_audience' => 'general',
                'max_length' => 60,
                'include_numbers' => false,
                'include_power_words' => true,
                'generate_multiple' => true,
                'count' => 3
            ));
            
            // 게시물 데이터 검증
            $this->validate_post_data($post_data);
            
            // 콘텐츠 요약 생성 (제목 재구성용)
            $content_summary = $this->generate_content_summary($post_data['content']);
            
            // 프롬프트 변수 준비
            $prompt_variables = array(
                'original_title' => $post_data['title'],
                'content_summary' => $content_summary,
                'target_audience' => $this->generation_settings['target_audiences'][$title_options['target_audience']]
            );
            
            // 프롬프트 생성
            $prompt = $this->prompt_template->generate_prompt('title_rewrite', $prompt_variables);
            
            // AI 제목 생성
            $ai_options = array(
                'model' => $this->generation_settings['default_model'],
                'temperature' => 0.8, // 창의성을 위해 높은 temperature
                'max_tokens' => 200
            );
            
            $generated_content = $this->openai_client->generate_with_system(
                $prompt['system_message'],
                $prompt['user_message'],
                $ai_options
            );
            
            // 제목 파싱 및 후처리
            $titles = $this->parse_generated_titles($generated_content, $title_options);
            
            // 통계 업데이트
            $processing_time = microtime(true) - $start_time;
            $this->update_generation_stats('title', $processing_time, true);
            
            // 템플릿 사용 기록
            $this->prompt_template->record_usage('title_rewrite');
            
            return array(
                'success' => true,
                'original_title' => $post_data['title'],
                'generated_titles' => $titles,
                'recommended_title' => $titles[0], // 첫 번째를 추천
                'processing_time' => $processing_time,
                'options_used' => $title_options,
                'prompt_info' => $prompt['template_info']
            );
            
        } catch (Exception $e) {
            $processing_time = microtime(true) - $start_time;
            $this->update_generation_stats('title', $processing_time, false);
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processing_time
            );
        }
    }
    
    /**
     * 핵심 내용 추출
     * 
     * @param array $post_data 게시물 데이터
     * @param array $options 추출 옵션
     * @return array 핵심 내용 추출 결과
     */
    public function extract_key_points($post_data, $options = array()) {
        $start_time = microtime(true);
        
        try {
            // 옵션 설정
            $extract_options = wp_parse_args($options, array(
                'max_points' => 5,
                'point_length' => 'short', // short, medium, long
                'include_quotes' => false,
                'include_statistics' => true,
                'prioritize_actionable' => true
            ));
            
            // 게시물 데이터 검증
            $this->validate_post_data($post_data);
            
            // 커스텀 프롬프트 생성 (핵심 내용 추출용)
            $system_message = '당신은 콘텐츠 분석 전문가입니다. 주어진 게시물에서 가장 중요하고 가치 있는 핵심 포인트들을 추출해주세요.';
            
            $user_message = $this->build_key_points_prompt($post_data, $extract_options);
            
            // AI 핵심 내용 추출
            $ai_options = array(
                'model' => $this->generation_settings['default_model'],
                'temperature' => 0.5, // 정확성을 위해 낮은 temperature
                'max_tokens' => 500
            );
            
            $generated_content = $this->openai_client->generate_with_system(
                $system_message,
                $user_message,
                $ai_options
            );
            
            // 핵심 포인트 파싱
            $key_points = $this->parse_key_points($generated_content, $extract_options);
            
            // 통계 업데이트
            $processing_time = microtime(true) - $start_time;
            $this->update_generation_stats('key_points', $processing_time, true);
            
            return array(
                'success' => true,
                'key_points' => $key_points,
                'total_points' => count($key_points),
                'processing_time' => $processing_time,
                'options_used' => $extract_options
            );
            
        } catch (Exception $e) {
            $processing_time = microtime(true) - $start_time;
            $this->update_generation_stats('key_points', $processing_time, false);
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processing_time
            );
        }
    }
    
    /**
     * 톤앤매너 조정
     * 
     * @param string $content 원본 콘텐츠
     * @param string $target_tone 목표 톤
     * @param array $options 조정 옵션
     * @return array 톤 조정 결과
     */
    public function adjust_tone($content, $target_tone, $options = array()) {
        $start_time = microtime(true);
        
        try {
            // 옵션 설정
            $tone_options = wp_parse_args($options, array(
                'preserve_meaning' => true,
                'preserve_length' => true,
                'target_audience' => 'general'
            ));
            
            // 톤 스타일 확인
            if (!isset($this->generation_settings['tone_styles'][$target_tone])) {
                throw new Exception("지원하지 않는 톤 스타일입니다: {$target_tone}");
            }
            
            $tone_description = $this->generation_settings['tone_styles'][$target_tone];
            
            // 프롬프트 생성
            $system_message = "당신은 콘텐츠 편집 전문가입니다. 주어진 텍스트의 의미와 정보는 유지하면서 톤앤매너만 조정해주세요.";
            
            $user_message = "다음 텍스트를 '{$tone_description}' 톤으로 조정해주세요:\n\n{$content}\n\n조정된 텍스트:";
            
            // AI 톤 조정
            $ai_options = array(
                'model' => $this->generation_settings['default_model'],
                'temperature' => 0.6,
                'max_tokens' => strlen($content) + 200
            );
            
            $adjusted_content = $this->openai_client->generate_with_system(
                $system_message,
                $user_message,
                $ai_options
            );
            
            $processing_time = microtime(true) - $start_time;
            
            return array(
                'success' => true,
                'original_content' => $content,
                'adjusted_content' => trim($adjusted_content),
                'target_tone' => $target_tone,
                'tone_description' => $tone_description,
                'processing_time' => $processing_time
            );
            
        } catch (Exception $e) {
            $processing_time = microtime(true) - $start_time;
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processing_time
            );
        }
    }
    
    /**
     * 배치 콘텐츠 생성
     * 
     * @param array $posts_data 여러 게시물 데이터
     * @param array $options 생성 옵션
     * @return array 배치 생성 결과
     */
    public function generate_batch_content($posts_data, $options = array()) {
        $start_time = microtime(true);
        $results = array();
        $successful = 0;
        $failed = 0;
        
        // 옵션 설정
        $batch_options = wp_parse_args($options, array(
            'include_summary' => true,
            'include_title_rewrite' => true,
            'include_key_points' => false,
            'delay_between_requests' => 1, // 초 단위
            'max_concurrent' => 5
        ));
        
        foreach ($posts_data as $index => $post_data) {
            try {
                $post_result = array(
                    'post_id' => isset($post_data['id']) ? $post_data['id'] : $index,
                    'original_title' => $post_data['title']
                );
                
                // 요약 생성
                if ($batch_options['include_summary']) {
                    $summary_result = $this->generate_summary($post_data, $options);
                    $post_result['summary'] = $summary_result;
                }
                
                // 제목 재구성
                if ($batch_options['include_title_rewrite']) {
                    $title_result = $this->rewrite_title($post_data, $options);
                    $post_result['title_rewrite'] = $title_result;
                }
                
                // 핵심 내용 추출
                if ($batch_options['include_key_points']) {
                    $key_points_result = $this->extract_key_points($post_data, $options);
                    $post_result['key_points'] = $key_points_result;
                }
                
                $post_result['success'] = true;
                $successful++;
                
                // 요청 간 지연
                if ($batch_options['delay_between_requests'] > 0 && $index < count($posts_data) - 1) {
                    sleep($batch_options['delay_between_requests']);
                }
                
            } catch (Exception $e) {
                $post_result = array(
                    'post_id' => isset($post_data['id']) ? $post_data['id'] : $index,
                    'success' => false,
                    'error' => $e->getMessage()
                );
                $failed++;
            }
            
            $results[] = $post_result;
        }
        
        $processing_time = microtime(true) - $start_time;
        
        return array(
            'success' => $failed === 0,
            'total_processed' => count($posts_data),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results,
            'processing_time' => $processing_time,
            'average_time_per_post' => $processing_time / count($posts_data)
        );
    }
    
    /**
     * 게시물 데이터 검증
     * 
     * @param array $post_data 게시물 데이터
     * @throws Exception 검증 실패 시
     */
    private function validate_post_data($post_data) {
        if (!is_array($post_data)) {
            throw new Exception('게시물 데이터는 배열이어야 합니다.');
        }
        
        $required_fields = array('title', 'content');
        foreach ($required_fields as $field) {
            if (!isset($post_data[$field]) || empty($post_data[$field])) {
                throw new Exception("필수 필드가 누락되었습니다: {$field}");
            }
        }
        
        // 콘텐츠 길이 확인
        if (strlen($post_data['content']) < 100) {
            throw new Exception('게시물 내용이 너무 짧습니다 (최소 100자 필요).');
        }
        
        if (strlen($post_data['content']) > 10000) {
            throw new Exception('게시물 내용이 너무 깁니다 (최대 10,000자).');
        }
    }
    
    /**
     * 요약용 프롬프트 변수 준비
     * 
     * @param array $post_data 게시물 데이터
     * @param array $options 요약 옵션
     * @return array 프롬프트 변수
     */
    private function prepare_summary_variables($post_data, $options) {
        return array(
            'post_title' => $post_data['title'],
            'post_content' => $this->clean_content_for_ai($post_data['content']),
            'target_length' => $this->generation_settings['summary_length'][$options['length']]
        );
    }
    
    /**
     * AI용 콘텐츠 정리
     * 
     * @param string $content 원본 콘텐츠
     * @return string 정리된 콘텐츠
     */
    private function clean_content_for_ai($content) {
        // HTML 태그 제거
        $content = wp_strip_all_tags($content);
        
        // 연속된 공백 정리
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 특수 문자 정리
        $content = preg_replace('/[^\p{L}\p{N}\s\.,!?;:()\-]/u', '', $content);
        
        // 길이 제한 (토큰 제한 고려)
        if (strlen($content) > 3000) {
            $content = substr($content, 0, 3000) . '...';
        }
        
        return trim($content);
    }
    
    /**
     * 최대 토큰 수 계산
     * 
     * @param string $length 길이 옵션
     * @return int 최대 토큰 수
     */
    private function calculate_max_tokens($length) {
        $token_limits = array(
            'short' => 200,
            'medium' => 400,
            'long' => 600
        );
        
        return isset($token_limits[$length]) ? $token_limits[$length] : 400;
    }
    
    /**
     * 요약 결과 후처리
     * 
     * @param string $generated_content AI 생성 콘텐츠
     * @param array $options 요약 옵션
     * @return array 처리된 결과
     */
    private function process_summary_result($generated_content, $options) {
        $summary = trim($generated_content);
        
        // 핵심 포인트 추출 (옵션에 따라)
        $key_points = array();
        if ($options['include_key_points']) {
            $key_points = $this->extract_bullet_points($summary);
        }
        
        return array(
            'summary' => $summary,
            'key_points' => $key_points
        );
    }
    
    /**
     * 콘텐츠 요약 생성 (제목 재구성용)
     * 
     * @param string $content 게시물 내용
     * @return string 요약
     */
    private function generate_content_summary($content) {
        $cleaned_content = $this->clean_content_for_ai($content);
        
        // 간단한 요약 (첫 200자 + 마지막 100자)
        if (strlen($cleaned_content) > 300) {
            $summary = substr($cleaned_content, 0, 200) . '...' . substr($cleaned_content, -100);
        } else {
            $summary = $cleaned_content;
        }
        
        return $summary;
    }
    
    /**
     * 생성된 제목 파싱
     * 
     * @param string $generated_content AI 생성 콘텐츠
     * @param array $options 제목 옵션
     * @return array 파싱된 제목들
     */
    private function parse_generated_titles($generated_content, $options) {
        $titles = array();
        
        // 번호가 있는 제목 추출
        preg_match_all('/\d+\.\s*(.+?)(?=\n|$)/m', $generated_content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $title) {
                $cleaned_title = trim($title);
                
                // 길이 제한 적용
                if (strlen($cleaned_title) <= $options['max_length']) {
                    $titles[] = $cleaned_title;
                }
            }
        }
        
        // 제목이 충분하지 않으면 줄바꿈으로 분할
        if (count($titles) < 2) {
            $lines = explode("\n", $generated_content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) <= $options['max_length']) {
                    $titles[] = $line;
                }
                
                if (count($titles) >= $options['count']) {
                    break;
                }
            }
        }
        
        return array_slice($titles, 0, $options['count']);
    }
    
    /**
     * 핵심 포인트 프롬프트 생성
     * 
     * @param array $post_data 게시물 데이터
     * @param array $options 추출 옵션
     * @return string 프롬프트
     */
    private function build_key_points_prompt($post_data, $options) {
        $prompt = "다음 게시물에서 가장 중요한 핵심 포인트 {$options['max_points']}개를 추출해주세요:\n\n";
        $prompt .= "제목: {$post_data['title']}\n";
        $prompt .= "내용: " . $this->clean_content_for_ai($post_data['content']) . "\n\n";
        $prompt .= "추출 기준:\n";
        $prompt .= "- 독자에게 가장 유용하고 실용적인 정보\n";
        $prompt .= "- 명확하고 구체적인 내용\n";
        
        if ($options['prioritize_actionable']) {
            $prompt .= "- 실행 가능한 조언이나 팁 우선\n";
        }
        
        if ($options['include_statistics']) {
            $prompt .= "- 통계나 수치 데이터 포함\n";
        }
        
        $prompt .= "\n각 포인트를 번호와 함께 나열해주세요:";
        
        return $prompt;
    }
    
    /**
     * 핵심 포인트 파싱
     * 
     * @param string $generated_content AI 생성 콘텐츠
     * @param array $options 추출 옵션
     * @return array 파싱된 핵심 포인트들
     */
    private function parse_key_points($generated_content, $options) {
        $key_points = array();
        
        // 번호가 있는 포인트 추출
        preg_match_all('/\d+\.\s*(.+?)(?=\n\d+\.|$)/s', $generated_content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $point) {
                $cleaned_point = trim($point);
                $cleaned_point = preg_replace('/\s+/', ' ', $cleaned_point);
                
                if (!empty($cleaned_point)) {
                    $key_points[] = $cleaned_point;
                }
                
                if (count($key_points) >= $options['max_points']) {
                    break;
                }
            }
        }
        
        return $key_points;
    }
    
    /**
     * 불릿 포인트 추출
     * 
     * @param string $content 콘텐츠
     * @return array 불릿 포인트들
     */
    private function extract_bullet_points($content) {
        $bullet_points = array();
        
        // 다양한 불릿 포인트 패턴 매칭
        $patterns = array(
            '/^[-•*]\s+(.+)$/m',
            '/^\d+\.\s+(.+)$/m'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[1])) {
                $bullet_points = array_merge($bullet_points, $matches[1]);
            }
        }
        
        return array_unique($bullet_points);
    }
    
    /**
     * 생성 통계 업데이트
     * 
     * @param string $type 생성 타입
     * @param float $processing_time 처리 시간
     * @param bool $success 성공 여부
     */
    private function update_generation_stats($type, $processing_time, $success) {
        $field_map = array(
            'summary' => 'total_summaries',
            'title' => 'total_titles',
            'key_points' => 'total_key_points'
        );
        
        if (isset($field_map[$type])) {
            $this->generation_stats[$field_map[$type]]++;
        }
        
        // 평균 처리 시간 업데이트
        $total_operations = array_sum(array_intersect_key($this->generation_stats, $field_map));
        $current_avg = $this->generation_stats['average_processing_time'];
        $this->generation_stats['average_processing_time'] = 
            (($current_avg * ($total_operations - 1)) + $processing_time) / $total_operations;
        
        // 성공률 업데이트
        if (!$success) {
            $success_count = $total_operations - 1; // 실패한 것 제외
            $this->generation_stats['success_rate'] = ($success_count / $total_operations) * 100;
        }
        
        $this->generation_stats['last_generation'] = current_time('mysql');
        
        // 통계 저장
        update_option('ainl_content_generation_stats', $this->generation_stats);
    }
    
    /**
     * 생성 통계 가져오기
     * 
     * @return array 생성 통계
     */
    public function get_generation_stats() {
        return $this->generation_stats;
    }
    
    /**
     * 생성 설정 업데이트
     * 
     * @param array $new_settings 새 설정
     * @return bool 성공 여부
     */
    public function update_generation_settings($new_settings) {
        $this->generation_settings = array_merge($this->generation_settings, $new_settings);
        return update_option('ainl_content_generation_settings', $this->generation_settings);
    }
    
    /**
     * 현재 생성 설정 가져오기
     * 
     * @return array 현재 설정
     */
    public function get_generation_settings() {
        return $this->generation_settings;
    }
} 
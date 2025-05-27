<?php
/**
 * CTA 자동 생성 시스템 클래스
 * 게시물 내용에 맞는 효과적인 CTA 버튼을 자동으로 생성합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_CTA_Generator {
    
    /**
     * OpenAI 클라이언트
     */
    private $openai_client;
    
    /**
     * 프롬프트 템플릿 시스템
     */
    private $prompt_template;
    
    /**
     * CTA 설정
     */
    private $cta_settings;
    
    /**
     * CTA 통계
     */
    private $cta_stats;
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->openai_client = new AINL_OpenAI_Client();
        $this->prompt_template = new AINL_Prompt_Template();
        
        $this->init_cta_settings();
        $this->load_cta_stats();
    }
    
    /**
     * CTA 설정 초기화
     */
    private function init_cta_settings() {
        $default_settings = array(
            'cta_styles' => array(
                'direct' => array(
                    'name' => '직접적 스타일',
                    'description' => '명확하고 직접적인 행동 지시',
                    'examples' => array('지금 읽기', '자세히 보기', '다운로드하기', '신청하기')
                ),
                'curiosity' => array(
                    'name' => '호기심 유발 스타일',
                    'description' => '궁금증을 자극하는 표현',
                    'examples' => array('비밀 확인하기', '진실 알아보기', '놀라운 결과 보기', '숨겨진 팁 발견')
                ),
                'benefit' => array(
                    'name' => '혜택 강조 스타일',
                    'description' => '구체적인 혜택이나 가치 제시',
                    'examples' => array('무료로 받기', '50% 할인받기', '전문가 되기', '시간 절약하기')
                ),
                'urgency' => array(
                    'name' => '긴급성 스타일',
                    'description' => '긴급함이나 희소성 강조',
                    'examples' => array('지금 바로', '마감 임박', '한정 수량', '오늘만')
                ),
                'social' => array(
                    'name' => '사회적 증명 스타일',
                    'description' => '다른 사람들의 참여 강조',
                    'examples' => array('1만명이 선택한', '베스트셀러', '인기 급상승', '추천 1위')
                )
            ),
            'cta_purposes' => array(
                'read_more' => '게시물 읽기',
                'download' => '다운로드',
                'subscribe' => '구독하기',
                'contact' => '문의하기',
                'purchase' => '구매하기',
                'register' => '등록하기',
                'learn_more' => '더 알아보기',
                'share' => '공유하기',
                'comment' => '댓글 달기',
                'follow' => '팔로우하기'
            ),
            'button_colors' => array(
                'primary' => '#007cba',
                'success' => '#46b450',
                'warning' => '#ffb900',
                'danger' => '#dc3232',
                'info' => '#00a0d2',
                'dark' => '#23282d'
            ),
            'button_sizes' => array(
                'small' => array('padding' => '8px 16px', 'font_size' => '14px'),
                'medium' => array('padding' => '12px 24px', 'font_size' => '16px'),
                'large' => array('padding' => '16px 32px', 'font_size' => '18px')
            ),
            'max_cta_length' => 20,
            'default_model' => 'gpt-3.5-turbo',
            'temperature' => 0.8
        );
        
        $saved_settings = get_option('ainl_cta_settings', array());
        $this->cta_settings = array_merge($default_settings, $saved_settings);
    }
    
    /**
     * CTA 통계 로드
     */
    private function load_cta_stats() {
        $this->cta_stats = get_option('ainl_cta_stats', array(
            'total_generated' => 0,
            'style_usage' => array(),
            'purpose_usage' => array(),
            'average_length' => 0,
            'success_rate' => 100,
            'last_generation' => null
        ));
    }
    
    /**
     * CTA 버튼 생성
     * 
     * @param array $post_data 게시물 데이터
     * @param array $options CTA 생성 옵션
     * @return array CTA 생성 결과
     */
    public function generate_cta($post_data, $options = array()) {
        $start_time = microtime(true);
        
        try {
            // 옵션 설정
            $cta_options = wp_parse_args($options, array(
                'purpose' => 'read_more',
                'styles' => array('direct', 'benefit', 'curiosity'),
                'count' => 3,
                'include_html' => true,
                'include_analytics' => false,
                'button_color' => 'primary',
                'button_size' => 'medium',
                'target_url' => '',
                'custom_context' => ''
            ));
            
            // 게시물 데이터 검증
            $this->validate_post_data($post_data);
            
            // 게시물 요약 생성 (CTA 생성용)
            $post_summary = $this->generate_post_summary($post_data);
            
            // 프롬프트 변수 준비
            $prompt_variables = array(
                'post_title' => $post_data['title'],
                'post_summary' => $post_summary,
                'post_url' => isset($post_data['url']) ? $post_data['url'] : $cta_options['target_url'],
                'cta_purpose' => $this->cta_settings['cta_purposes'][$cta_options['purpose']]
            );
            
            // 커스텀 컨텍스트 추가
            if (!empty($cta_options['custom_context'])) {
                $prompt_variables['custom_context'] = $cta_options['custom_context'];
            }
            
            // 프롬프트 생성
            $prompt = $this->prompt_template->generate_prompt('cta_generation', $prompt_variables);
            
            // AI CTA 생성
            $ai_options = array(
                'model' => $this->cta_settings['default_model'],
                'temperature' => $this->cta_settings['temperature'],
                'max_tokens' => 200
            );
            
            $generated_content = $this->openai_client->generate_with_system(
                $prompt['system_message'],
                $prompt['user_message'],
                $ai_options
            );
            
            // CTA 파싱 및 후처리
            $cta_texts = $this->parse_generated_ctas($generated_content, $cta_options);
            
            // CTA 버튼 HTML 생성
            $cta_buttons = array();
            foreach ($cta_texts as $index => $cta_text) {
                $style = isset($cta_options['styles'][$index]) ? $cta_options['styles'][$index] : $cta_options['styles'][0];
                
                $cta_button = array(
                    'text' => $cta_text,
                    'style' => $style,
                    'style_info' => $this->cta_settings['cta_styles'][$style],
                    'length' => mb_strlen($cta_text)
                );
                
                if ($cta_options['include_html']) {
                    $cta_button['html'] = $this->generate_cta_html($cta_text, $cta_options, $style);
                }
                
                if ($cta_options['include_analytics']) {
                    $cta_button['analytics'] = $this->generate_analytics_code($cta_text, $cta_options);
                }
                
                $cta_buttons[] = $cta_button;
            }
            
            // A/B 테스트 변형 생성
            $ab_test_variants = $this->generate_ab_test_variants($cta_buttons, $cta_options);
            
            // 통계 업데이트
            $processing_time = microtime(true) - $start_time;
            $this->update_cta_stats($cta_options, $cta_buttons, $processing_time, true);
            
            // 템플릿 사용 기록
            $this->prompt_template->record_usage('cta_generation');
            
            return array(
                'success' => true,
                'cta_buttons' => $cta_buttons,
                'recommended_cta' => $cta_buttons[0], // 첫 번째를 추천
                'ab_test_variants' => $ab_test_variants,
                'post_info' => array(
                    'title' => $post_data['title'],
                    'summary' => $post_summary
                ),
                'processing_time' => $processing_time,
                'options_used' => $cta_options,
                'prompt_info' => $prompt['template_info']
            );
            
        } catch (Exception $e) {
            $processing_time = microtime(true) - $start_time;
            $this->update_cta_stats($cta_options, array(), $processing_time, false);
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processing_time
            );
        }
    }
    
    /**
     * 배치 CTA 생성
     * 
     * @param array $posts_data 여러 게시물 데이터
     * @param array $options 생성 옵션
     * @return array 배치 생성 결과
     */
    public function generate_batch_ctas($posts_data, $options = array()) {
        $start_time = microtime(true);
        $results = array();
        $successful = 0;
        $failed = 0;
        
        // 옵션 설정
        $batch_options = wp_parse_args($options, array(
            'delay_between_requests' => 1,
            'max_concurrent' => 3,
            'consistent_style' => false,
            'consistent_purpose' => true
        ));
        
        foreach ($posts_data as $index => $post_data) {
            try {
                // 일관된 스타일/목적 적용
                if ($batch_options['consistent_style'] && $index > 0) {
                    $options['styles'] = $results[0]['cta_result']['options_used']['styles'];
                }
                
                if ($batch_options['consistent_purpose'] && $index > 0) {
                    $options['purpose'] = $results[0]['cta_result']['options_used']['purpose'];
                }
                
                $cta_result = $this->generate_cta($post_data, $options);
                
                $post_result = array(
                    'post_id' => isset($post_data['id']) ? $post_data['id'] : $index,
                    'post_title' => $post_data['title'],
                    'success' => $cta_result['success'],
                    'cta_result' => $cta_result
                );
                
                if ($cta_result['success']) {
                    $successful++;
                } else {
                    $failed++;
                }
                
                $results[] = $post_result;
                
                // 요청 간 지연
                if ($batch_options['delay_between_requests'] > 0 && $index < count($posts_data) - 1) {
                    sleep($batch_options['delay_between_requests']);
                }
                
            } catch (Exception $e) {
                $post_result = array(
                    'post_id' => isset($post_data['id']) ? $post_data['id'] : $index,
                    'post_title' => isset($post_data['title']) ? $post_data['title'] : 'Unknown',
                    'success' => false,
                    'error' => $e->getMessage()
                );
                
                $results[] = $post_result;
                $failed++;
            }
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
     * CTA 성능 분석
     * 
     * @param array $cta_data CTA 데이터
     * @return array 분석 결과
     */
    public function analyze_cta_performance($cta_data) {
        $analysis = array(
            'readability_score' => $this->calculate_readability_score($cta_data['text']),
            'urgency_level' => $this->detect_urgency_level($cta_data['text']),
            'emotional_appeal' => $this->analyze_emotional_appeal($cta_data['text']),
            'action_clarity' => $this->assess_action_clarity($cta_data['text']),
            'length_optimization' => $this->evaluate_length($cta_data['text']),
            'power_words_count' => $this->count_power_words($cta_data['text']),
            'overall_score' => 0
        );
        
        // 종합 점수 계산
        $analysis['overall_score'] = $this->calculate_overall_score($analysis);
        
        // 개선 제안
        $analysis['improvement_suggestions'] = $this->generate_improvement_suggestions($analysis, $cta_data);
        
        return $analysis;
    }
    
    /**
     * CTA 최적화 제안
     * 
     * @param string $cta_text CTA 텍스트
     * @param array $context 컨텍스트 정보
     * @return array 최적화 제안
     */
    public function suggest_optimizations($cta_text, $context = array()) {
        $suggestions = array();
        
        // 길이 최적화
        if (mb_strlen($cta_text) > $this->cta_settings['max_cta_length']) {
            $suggestions[] = array(
                'type' => 'length',
                'priority' => 'high',
                'message' => 'CTA가 너무 깁니다. ' . $this->cta_settings['max_cta_length'] . '자 이내로 줄이는 것을 권장합니다.',
                'suggestion' => $this->shorten_cta($cta_text)
            );
        }
        
        // 액션 워드 확인
        if (!$this->has_action_word($cta_text)) {
            $suggestions[] = array(
                'type' => 'action',
                'priority' => 'medium',
                'message' => '명확한 액션 워드가 없습니다. 동사를 추가하는 것을 권장합니다.',
                'suggestion' => $this->add_action_word($cta_text)
            );
        }
        
        // 개인화 제안
        if (isset($context['target_audience']) && !$this->is_personalized($cta_text)) {
            $suggestions[] = array(
                'type' => 'personalization',
                'priority' => 'low',
                'message' => '타겟 독자에 맞는 개인화를 고려해보세요.',
                'suggestion' => $this->personalize_cta($cta_text, $context['target_audience'])
            );
        }
        
        return $suggestions;
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
        
        if (!isset($post_data['title']) || empty($post_data['title'])) {
            throw new Exception('게시물 제목이 필요합니다.');
        }
        
        if (!isset($post_data['content']) || empty($post_data['content'])) {
            throw new Exception('게시물 내용이 필요합니다.');
        }
    }
    
    /**
     * 게시물 요약 생성 (CTA 생성용)
     * 
     * @param array $post_data 게시물 데이터
     * @return string 요약
     */
    private function generate_post_summary($post_data) {
        $content = wp_strip_all_tags($post_data['content']);
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 간단한 요약 (첫 300자)
        if (strlen($content) > 300) {
            $summary = substr($content, 0, 300) . '...';
        } else {
            $summary = $content;
        }
        
        return trim($summary);
    }
    
    /**
     * 생성된 CTA 파싱
     * 
     * @param string $generated_content AI 생성 콘텐츠
     * @param array $options CTA 옵션
     * @return array 파싱된 CTA들
     */
    private function parse_generated_ctas($generated_content, $options) {
        $ctas = array();
        
        // 번호가 있는 CTA 추출
        preg_match_all('/\d+\.\s*[^:]*:\s*(.+?)(?=\n|$)/m', $generated_content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $cta) {
                $cleaned_cta = trim($cta);
                // 다양한 따옴표 문자 제거
                $cleaned_cta = preg_replace('/["\'""]/', '', $cleaned_cta);
                $cleaned_cta = preg_replace('/[\u2018\u2019\u201C\u201D]/u', '', $cleaned_cta);
                
                if (!empty($cleaned_cta) && mb_strlen($cleaned_cta) <= $this->cta_settings['max_cta_length']) {
                    $ctas[] = $cleaned_cta;
                }
                
                if (count($ctas) >= $options['count']) {
                    break;
                }
            }
        }
        
        // CTA가 충분하지 않으면 줄바꿈으로 분할
        if (count($ctas) < $options['count']) {
            $lines = explode("\n", $generated_content);
            foreach ($lines as $line) {
                $line = trim($line);
                $line = preg_replace('/^\d+\.\s*/', '', $line); // 번호 제거
                // 다양한 따옴표 문자 제거
                $line = preg_replace('/["\'""]/', '', $line);
                $line = preg_replace('/[\u2018\u2019\u201C\u201D]/u', '', $line);
                
                if (!empty($line) && mb_strlen($line) <= $this->cta_settings['max_cta_length']) {
                    $ctas[] = $line;
                }
                
                if (count($ctas) >= $options['count']) {
                    break;
                }
            }
        }
        
        return array_slice($ctas, 0, $options['count']);
    }
    
    /**
     * CTA HTML 생성
     * 
     * @param string $cta_text CTA 텍스트
     * @param array $options CTA 옵션
     * @param string $style CTA 스타일
     * @return string HTML 코드
     */
    private function generate_cta_html($cta_text, $options, $style) {
        $button_color = $this->cta_settings['button_colors'][$options['button_color']];
        $button_size = $this->cta_settings['button_sizes'][$options['button_size']];
        $target_url = !empty($options['target_url']) ? $options['target_url'] : '#';
        
        $html = '<a href="' . esc_url($target_url) . '" ';
        $html .= 'class="ainl-cta-button ainl-cta-' . $style . '" ';
        $html .= 'style="';
        $html .= 'background-color: ' . $button_color . '; ';
        $html .= 'color: white; ';
        $html .= 'padding: ' . $button_size['padding'] . '; ';
        $html .= 'font-size: ' . $button_size['font_size'] . '; ';
        $html .= 'text-decoration: none; ';
        $html .= 'border-radius: 5px; ';
        $html .= 'display: inline-block; ';
        $html .= 'font-weight: bold; ';
        $html .= 'text-align: center; ';
        $html .= 'transition: background-color 0.3s ease;';
        $html .= '">';
        $html .= esc_html($cta_text);
        $html .= '</a>';
        
        return $html;
    }
    
    /**
     * 분석 코드 생성
     * 
     * @param string $cta_text CTA 텍스트
     * @param array $options CTA 옵션
     * @return array 분석 코드
     */
    private function generate_analytics_code($cta_text, $options) {
        return array(
            'google_analytics' => "gtag('event', 'click', {'event_category': 'CTA', 'event_label': '" . esc_js($cta_text) . "'});",
            'facebook_pixel' => "fbq('track', 'Lead', {'content_name': '" . esc_js($cta_text) . "'});",
            'custom_tracking' => array(
                'cta_text' => $cta_text,
                'cta_purpose' => $options['purpose'],
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * A/B 테스트 변형 생성
     * 
     * @param array $cta_buttons CTA 버튼들
     * @param array $options 옵션
     * @return array A/B 테스트 변형들
     */
    private function generate_ab_test_variants($cta_buttons, $options) {
        $variants = array();
        
        foreach ($cta_buttons as $index => $cta_button) {
            $variant = array(
                'id' => 'variant_' . ($index + 1),
                'name' => $cta_button['style_info']['name'],
                'cta_text' => $cta_button['text'],
                'html' => isset($cta_button['html']) ? $cta_button['html'] : '',
                'expected_performance' => $this->predict_performance($cta_button),
                'test_weight' => 100 / count($cta_buttons) // 균등 분배
            );
            
            $variants[] = $variant;
        }
        
        return $variants;
    }
    
    /**
     * 성능 예측
     * 
     * @param array $cta_button CTA 버튼 데이터
     * @return array 성능 예측
     */
    private function predict_performance($cta_button) {
        $score = 50; // 기본 점수
        
        // 길이에 따른 점수 조정
        if ($cta_button['length'] <= 10) {
            $score += 10;
        } elseif ($cta_button['length'] <= 15) {
            $score += 5;
        } else {
            $score -= 5;
        }
        
        // 스타일에 따른 점수 조정
        $style_scores = array(
            'direct' => 15,
            'benefit' => 20,
            'urgency' => 18,
            'curiosity' => 12,
            'social' => 10
        );
        
        if (isset($style_scores[$cta_button['style']])) {
            $score += $style_scores[$cta_button['style']];
        }
        
        return array(
            'predicted_ctr' => min(100, max(0, $score)) . '%',
            'confidence_level' => 'medium',
            'factors' => array(
                'length' => $cta_button['length'] <= 15 ? 'optimal' : 'too_long',
                'style' => $cta_button['style'],
                'clarity' => $this->assess_clarity($cta_button['text'])
            )
        );
    }
    
    /**
     * 명확성 평가
     * 
     * @param string $text CTA 텍스트
     * @return string 명확성 수준
     */
    private function assess_clarity($text) {
        $action_words = array('보기', '읽기', '다운로드', '신청', '구매', '등록', '확인', '시작', '참여', '구독');
        
        foreach ($action_words as $word) {
            if (strpos($text, $word) !== false) {
                return 'high';
            }
        }
        
        return 'medium';
    }
    
    /**
     * 가독성 점수 계산
     * 
     * @param string $text 텍스트
     * @return int 가독성 점수 (0-100)
     */
    private function calculate_readability_score($text) {
        $score = 100;
        
        // 길이 패널티
        if (mb_strlen($text) > 15) {
            $score -= (mb_strlen($text) - 15) * 2;
        }
        
        // 복잡한 단어 패널티
        $complex_words = array('이용하여', '활용하여', '통하여', '관련하여');
        foreach ($complex_words as $word) {
            if (strpos($text, $word) !== false) {
                $score -= 10;
            }
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * 긴급성 수준 감지
     * 
     * @param string $text 텍스트
     * @return string 긴급성 수준
     */
    private function detect_urgency_level($text) {
        $urgency_words = array(
            'high' => array('지금', '바로', '즉시', '마감', '한정', '오늘만'),
            'medium' => array('빨리', '서둘러', '곧', '이번'),
            'low' => array('언제든', '천천히', '여유롭게')
        );
        
        foreach ($urgency_words as $level => $words) {
            foreach ($words as $word) {
                if (strpos($text, $word) !== false) {
                    return $level;
                }
            }
        }
        
        return 'none';
    }
    
    /**
     * 감정적 어필 분석
     * 
     * @param string $text 텍스트
     * @return array 감정적 어필 분석
     */
    private function analyze_emotional_appeal($text) {
        $emotions = array(
            'excitement' => array('놀라운', '환상적인', '멋진', '최고의'),
            'curiosity' => array('비밀', '숨겨진', '특별한', '독특한'),
            'fear' => array('놓치지', '마지막', '위험', '손실'),
            'trust' => array('안전한', '검증된', '신뢰할', '보장')
        );
        
        $detected_emotions = array();
        
        foreach ($emotions as $emotion => $words) {
            foreach ($words as $word) {
                if (strpos($text, $word) !== false) {
                    $detected_emotions[] = $emotion;
                    break;
                }
            }
        }
        
        return array(
            'emotions' => $detected_emotions,
            'primary_emotion' => !empty($detected_emotions) ? $detected_emotions[0] : 'neutral',
            'emotional_intensity' => count($detected_emotions) > 1 ? 'high' : (count($detected_emotions) === 1 ? 'medium' : 'low')
        );
    }
    
    /**
     * 액션 명확성 평가
     * 
     * @param string $text 텍스트
     * @return string 명확성 수준
     */
    private function assess_action_clarity($text) {
        $clear_actions = array('클릭', '보기', '읽기', '다운로드', '신청', '구매', '등록', '확인', '시작', '참여');
        
        $action_count = 0;
        foreach ($clear_actions as $action) {
            if (strpos($text, $action) !== false) {
                $action_count++;
            }
        }
        
        if ($action_count >= 2) {
            return 'confused'; // 너무 많은 액션
        } elseif ($action_count === 1) {
            return 'clear';
        } else {
            return 'unclear';
        }
    }
    
    /**
     * 길이 최적화 평가
     * 
     * @param string $text 텍스트
     * @return array 길이 평가
     */
    private function evaluate_length($text) {
        $length = mb_strlen($text);
        $optimal_min = 5;
        $optimal_max = 15;
        
        if ($length < $optimal_min) {
            return array('status' => 'too_short', 'score' => 30, 'recommendation' => '더 구체적인 표현을 추가하세요.');
        } elseif ($length > $optimal_max) {
            return array('status' => 'too_long', 'score' => 50, 'recommendation' => '더 간결하게 줄이세요.');
        } else {
            return array('status' => 'optimal', 'score' => 100, 'recommendation' => '적절한 길이입니다.');
        }
    }
    
    /**
     * 파워 워드 개수 계산
     * 
     * @param string $text 텍스트
     * @return int 파워 워드 개수
     */
    private function count_power_words($text) {
        $power_words = array(
            '무료', '할인', '특별', '독점', '한정', '비밀', '놀라운', '최고', '완벽', '보장',
            '즉시', '빠른', '쉬운', '간단', '효과적', '강력한', '혁신적', '새로운'
        );
        
        $count = 0;
        foreach ($power_words as $word) {
            if (strpos($text, $word) !== false) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * 종합 점수 계산
     * 
     * @param array $analysis 분석 결과
     * @return int 종합 점수
     */
    private function calculate_overall_score($analysis) {
        $weights = array(
            'readability_score' => 0.25,
            'length_optimization' => 0.20,
            'action_clarity' => 0.20,
            'power_words_count' => 0.15,
            'urgency_level' => 0.10,
            'emotional_appeal' => 0.10
        );
        
        $score = 0;
        
        // 가독성 점수
        $score += $analysis['readability_score'] * $weights['readability_score'];
        
        // 길이 최적화 점수
        $score += $analysis['length_optimization']['score'] * $weights['length_optimization'];
        
        // 액션 명확성 점수
        $clarity_scores = array('clear' => 100, 'unclear' => 50, 'confused' => 20);
        $score += $clarity_scores[$analysis['action_clarity']] * $weights['action_clarity'];
        
        // 파워 워드 점수 (최대 3개까지 긍정적)
        $power_word_score = min(100, $analysis['power_words_count'] * 33);
        $score += $power_word_score * $weights['power_words_count'];
        
        // 긴급성 점수
        $urgency_scores = array('high' => 100, 'medium' => 70, 'low' => 40, 'none' => 20);
        $score += $urgency_scores[$analysis['urgency_level']] * $weights['urgency_level'];
        
        // 감정적 어필 점수
        $emotion_scores = array('high' => 100, 'medium' => 70, 'low' => 40);
        $score += $emotion_scores[$analysis['emotional_appeal']['emotional_intensity']] * $weights['emotional_appeal'];
        
        return round($score);
    }
    
    /**
     * 개선 제안 생성
     * 
     * @param array $analysis 분석 결과
     * @param array $cta_data CTA 데이터
     * @return array 개선 제안
     */
    private function generate_improvement_suggestions($analysis, $cta_data) {
        $suggestions = array();
        
        if ($analysis['overall_score'] < 70) {
            if ($analysis['readability_score'] < 70) {
                $suggestions[] = '더 간단하고 명확한 표현을 사용하세요.';
            }
            
            if ($analysis['action_clarity'] === 'unclear') {
                $suggestions[] = '명확한 액션 워드(보기, 다운로드, 신청 등)를 추가하세요.';
            }
            
            if ($analysis['power_words_count'] === 0) {
                $suggestions[] = '파워 워드(무료, 특별, 한정 등)를 활용하세요.';
            }
            
            if ($analysis['urgency_level'] === 'none') {
                $suggestions[] = '긴급성을 나타내는 표현을 고려해보세요.';
            }
        }
        
        return $suggestions;
    }
    
    /**
     * CTA 통계 업데이트
     * 
     * @param array $options 옵션
     * @param array $cta_buttons CTA 버튼들
     * @param float $processing_time 처리 시간
     * @param bool $success 성공 여부
     */
    private function update_cta_stats($options, $cta_buttons, $processing_time, $success) {
        $this->cta_stats['total_generated']++;
        
        // 스타일 사용 통계
        if (!empty($cta_buttons)) {
            foreach ($cta_buttons as $button) {
                $style = $button['style'];
                if (!isset($this->cta_stats['style_usage'][$style])) {
                    $this->cta_stats['style_usage'][$style] = 0;
                }
                $this->cta_stats['style_usage'][$style]++;
            }
            
            // 평균 길이 업데이트
            $total_length = array_sum(array_column($cta_buttons, 'length'));
            $avg_length = $total_length / count($cta_buttons);
            
            $current_avg = $this->cta_stats['average_length'];
            $total_generated = $this->cta_stats['total_generated'];
            $this->cta_stats['average_length'] = 
                (($current_avg * ($total_generated - 1)) + $avg_length) / $total_generated;
        }
        
        // 목적 사용 통계
        $purpose = $options['purpose'];
        if (!isset($this->cta_stats['purpose_usage'][$purpose])) {
            $this->cta_stats['purpose_usage'][$purpose] = 0;
        }
        $this->cta_stats['purpose_usage'][$purpose]++;
        
        // 성공률 업데이트
        if (!$success) {
            $success_count = $this->cta_stats['total_generated'] - 1;
            $this->cta_stats['success_rate'] = ($success_count / $this->cta_stats['total_generated']) * 100;
        }
        
        $this->cta_stats['last_generation'] = current_time('mysql');
        
        // 통계 저장
        update_option('ainl_cta_stats', $this->cta_stats);
    }
    
    /**
     * CTA 통계 가져오기
     * 
     * @return array CTA 통계
     */
    public function get_cta_stats() {
        return $this->cta_stats;
    }
    
    /**
     * CTA 설정 업데이트
     * 
     * @param array $new_settings 새 설정
     * @return bool 성공 여부
     */
    public function update_cta_settings($new_settings) {
        $this->cta_settings = array_merge($this->cta_settings, $new_settings);
        return update_option('ainl_cta_settings', $this->cta_settings);
    }
    
    /**
     * 현재 CTA 설정 가져오기
     * 
     * @return array 현재 설정
     */
    public function get_cta_settings() {
        return $this->cta_settings;
    }
} 
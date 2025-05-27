<?php
/**
 * 프롬프트 템플릿 시스템 클래스
 * AI 콘텐츠 생성을 위한 프롬프트 템플릿을 관리합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Prompt_Template {
    
    /**
     * 기본 템플릿들
     */
    private $default_templates;
    
    /**
     * 사용자 정의 템플릿들
     */
    private $custom_templates;
    
    /**
     * 템플릿 변수 패턴
     */
    const VARIABLE_PATTERN = '/\{\{([a-zA-Z0-9_]+)\}\}/';
    
    /**
     * 생성자
     */
    public function __construct() {
        $this->init_default_templates();
        $this->load_custom_templates();
    }
    
    /**
     * 기본 템플릿 초기화
     */
    private function init_default_templates() {
        $this->default_templates = array(
            
            // 게시물 요약 템플릿
            'post_summary' => array(
                'name' => '게시물 요약',
                'description' => '게시물을 뉴스레터용으로 요약합니다.',
                'system_message' => '당신은 전문적인 뉴스레터 에디터입니다. 주어진 게시물을 독자들이 쉽게 이해할 수 있도록 간결하고 매력적으로 요약해주세요.',
                'user_template' => '다음 게시물을 {{target_length}}자 내외로 요약해주세요:

제목: {{post_title}}
내용: {{post_content}}

요약 시 다음 사항을 고려해주세요:
- 핵심 메시지를 명확히 전달
- 독자의 관심을 끌 수 있는 표현 사용
- 전문 용어는 쉽게 설명
- 행동을 유도하는 마무리

요약:',
                'variables' => array('post_title', 'post_content', 'target_length'),
                'default_values' => array('target_length' => '200-300')
            ),
            
            // 제목 재구성 템플릿
            'title_rewrite' => array(
                'name' => '제목 재구성',
                'description' => '뉴스레터에 적합한 제목으로 재구성합니다.',
                'system_message' => '당신은 뉴스레터 제목 전문가입니다. 클릭률을 높이고 독자의 관심을 끄는 매력적인 제목을 만들어주세요.',
                'user_template' => '다음 게시물의 제목을 뉴스레터용으로 재구성해주세요:

원본 제목: {{original_title}}
게시물 내용 요약: {{content_summary}}
타겟 독자: {{target_audience}}

제목 재구성 시 고려사항:
- 호기심을 자극하는 표현
- 구체적인 혜택이나 가치 제시
- 감정적 어필
- 적절한 길이 (50자 이내)

3가지 버전의 제목을 제안해주세요:
1. 호기심 유발형:
2. 혜택 강조형:
3. 감정 어필형:',
                'variables' => array('original_title', 'content_summary', 'target_audience'),
                'default_values' => array('target_audience' => '일반 독자')
            ),
            
            // CTA 생성 템플릿
            'cta_generation' => array(
                'name' => 'CTA 버튼 생성',
                'description' => '게시물에 맞는 CTA 버튼을 생성합니다.',
                'system_message' => '당신은 마케팅 전문가입니다. 게시물 내용에 맞는 효과적인 CTA(Call-to-Action)를 만들어주세요.',
                'user_template' => '다음 게시물에 적합한 CTA 버튼을 생성해주세요:

게시물 제목: {{post_title}}
게시물 요약: {{post_summary}}
게시물 링크: {{post_url}}
CTA 목적: {{cta_purpose}}

CTA 생성 가이드라인:
- 명확하고 구체적인 행동 지시
- 긴급성이나 희소성 활용
- 혜택을 명시
- 15자 이내의 간결한 표현

3가지 스타일의 CTA를 제안해주세요:
1. 직접적 스타일:
2. 호기심 유발 스타일:
3. 혜택 강조 스타일:',
                'variables' => array('post_title', 'post_summary', 'post_url', 'cta_purpose'),
                'default_values' => array('cta_purpose' => '게시물 읽기')
            ),
            
            // 뉴스레터 섹션 생성 템플릿
            'newsletter_section' => array(
                'name' => '뉴스레터 섹션',
                'description' => '여러 게시물을 하나의 뉴스레터 섹션으로 구성합니다.',
                'system_message' => '당신은 뉴스레터 편집자입니다. 여러 게시물을 하나의 일관된 섹션으로 구성해주세요.',
                'user_template' => '다음 게시물들을 "{{section_title}}" 섹션으로 구성해주세요:

{{posts_data}}

섹션 구성 요구사항:
- 섹션 소개 문구 (50자 내외)
- 각 게시물별 간단한 설명 (100자 내외)
- 섹션 마무리 문구
- 일관된 톤앤매너 유지

섹션 구성:',
                'variables' => array('section_title', 'posts_data'),
                'default_values' => array('section_title' => '이번 주 주요 소식')
            ),
            
            // 개인화 메시지 템플릿
            'personalized_intro' => array(
                'name' => '개인화 인사말',
                'description' => '구독자에게 개인화된 인사말을 생성합니다.',
                'system_message' => '당신은 친근하고 전문적인 뉴스레터 호스트입니다. 구독자에게 따뜻하고 개인적인 인사말을 작성해주세요.',
                'user_template' => '다음 정보를 바탕으로 개인화된 인사말을 작성해주세요:

구독자 이름: {{subscriber_name}}
뉴스레터 주제: {{newsletter_topic}}
이번 주 하이라이트: {{week_highlight}}
계절/시기: {{season_context}}

인사말 요구사항:
- 친근하고 따뜻한 톤
- 개인적인 느낌
- 이번 주 내용에 대한 기대감 조성
- 100자 내외

개인화된 인사말:',
                'variables' => array('subscriber_name', 'newsletter_topic', 'week_highlight', 'season_context'),
                'default_values' => array(
                    'subscriber_name' => '구독자님',
                    'season_context' => '요즘'
                )
            ),
            
            // 마무리 메시지 템플릿
            'closing_message' => array(
                'name' => '마무리 메시지',
                'description' => '뉴스레터 마무리 메시지를 생성합니다.',
                'system_message' => '당신은 뉴스레터 편집자입니다. 독자와의 연결감을 높이는 따뜻한 마무리 메시지를 작성해주세요.',
                'user_template' => '다음 뉴스레터의 마무리 메시지를 작성해주세요:

뉴스레터 주요 내용: {{newsletter_summary}}
다음 주 예고: {{next_week_preview}}
피드백 요청사항: {{feedback_request}}

마무리 메시지 요구사항:
- 감사 인사
- 다음 주에 대한 기대감
- 소통 유도
- 따뜻하고 친근한 톤
- 150자 내외

마무리 메시지:',
                'variables' => array('newsletter_summary', 'next_week_preview', 'feedback_request'),
                'default_values' => array(
                    'feedback_request' => '의견이나 제안사항'
                )
            ),
            
            // 카테고리별 소개 템플릿
            'category_intro' => array(
                'name' => '카테고리 소개',
                'description' => '특정 카테고리의 게시물들을 소개합니다.',
                'system_message' => '당신은 콘텐츠 큐레이터입니다. 특정 카테고리의 게시물들을 매력적으로 소개해주세요.',
                'user_template' => '{{category_name}} 카테고리의 게시물들을 소개해주세요:

이번 주 {{category_name}} 게시물:
{{category_posts}}

카테고리 특징: {{category_description}}
독자 관심사: {{reader_interests}}

소개 요구사항:
- 카테고리의 가치와 중요성 강조
- 이번 주 게시물들의 공통 주제나 트렌드 언급
- 독자의 관심을 끄는 표현
- 200자 내외

카테고리 소개:',
                'variables' => array('category_name', 'category_posts', 'category_description', 'reader_interests'),
                'default_values' => array()
            )
        );
    }
    
    /**
     * 사용자 정의 템플릿 로드
     */
    private function load_custom_templates() {
        $this->custom_templates = get_option('ainl_custom_templates', array());
    }
    
    /**
     * 템플릿 가져오기
     * 
     * @param string $template_id 템플릿 ID
     * @return array|null 템플릿 데이터
     */
    public function get_template($template_id) {
        // 사용자 정의 템플릿 우선 확인
        if (isset($this->custom_templates[$template_id])) {
            return $this->custom_templates[$template_id];
        }
        
        // 기본 템플릿 확인
        if (isset($this->default_templates[$template_id])) {
            return $this->default_templates[$template_id];
        }
        
        return null;
    }
    
    /**
     * 모든 템플릿 목록 가져오기
     * 
     * @return array 템플릿 목록
     */
    public function get_all_templates() {
        return array_merge($this->default_templates, $this->custom_templates);
    }
    
    /**
     * 템플릿 카테고리별 목록 가져오기
     * 
     * @return array 카테고리별 템플릿 목록
     */
    public function get_templates_by_category() {
        $categorized = array(
            'content_generation' => array(),
            'personalization' => array(),
            'structure' => array(),
            'custom' => array()
        );
        
        $all_templates = $this->get_all_templates();
        
        foreach ($all_templates as $id => $template) {
            $category = isset($template['category']) ? $template['category'] : 'content_generation';
            
            if (isset($this->custom_templates[$id])) {
                $category = 'custom';
            }
            
            $categorized[$category][$id] = $template;
        }
        
        return $categorized;
    }
    
    /**
     * 프롬프트 생성
     * 
     * @param string $template_id 템플릿 ID
     * @param array $variables 변수 값들
     * @return array 생성된 프롬프트 (system_message, user_message)
     */
    public function generate_prompt($template_id, $variables = array()) {
        $template = $this->get_template($template_id);
        
        if (!$template) {
            throw new Exception("템플릿을 찾을 수 없습니다: {$template_id}");
        }
        
        // 기본값과 변수 병합
        $default_values = isset($template['default_values']) ? $template['default_values'] : array();
        $merged_variables = array_merge($default_values, $variables);
        
        // 필수 변수 확인
        $required_variables = isset($template['variables']) ? $template['variables'] : array();
        $missing_variables = array();
        
        foreach ($required_variables as $var) {
            if (!isset($merged_variables[$var]) || empty($merged_variables[$var])) {
                $missing_variables[] = $var;
            }
        }
        
        if (!empty($missing_variables)) {
            throw new Exception("필수 변수가 누락되었습니다: " . implode(', ', $missing_variables));
        }
        
        // 템플릿 변수 치환
        $system_message = isset($template['system_message']) ? $template['system_message'] : '';
        $user_message = $this->replace_variables($template['user_template'], $merged_variables);
        
        return array(
            'system_message' => $system_message,
            'user_message' => $user_message,
            'template_info' => array(
                'id' => $template_id,
                'name' => $template['name'],
                'variables_used' => $merged_variables
            )
        );
    }
    
    /**
     * 변수 치환
     * 
     * @param string $template 템플릿 문자열
     * @param array $variables 변수 배열
     * @return string 치환된 문자열
     */
    private function replace_variables($template, $variables) {
        return preg_replace_callback(
            self::VARIABLE_PATTERN,
            function($matches) use ($variables) {
                $var_name = $matches[1];
                return isset($variables[$var_name]) ? $variables[$var_name] : $matches[0];
            },
            $template
        );
    }
    
    /**
     * 템플릿 변수 추출
     * 
     * @param string $template 템플릿 문자열
     * @return array 변수 목록
     */
    public function extract_variables($template) {
        preg_match_all(self::VARIABLE_PATTERN, $template, $matches);
        return array_unique($matches[1]);
    }
    
    /**
     * 템플릿 유효성 검증
     * 
     * @param array $template 템플릿 데이터
     * @return array 검증 결과
     */
    public function validate_template($template) {
        $errors = array();
        $warnings = array();
        
        // 필수 필드 확인
        $required_fields = array('name', 'user_template');
        foreach ($required_fields as $field) {
            if (!isset($template[$field]) || empty($template[$field])) {
                $errors[] = "필수 필드가 누락되었습니다: {$field}";
            }
        }
        
        // 템플릿 변수 확인
        if (isset($template['user_template'])) {
            $template_variables = $this->extract_variables($template['user_template']);
            $declared_variables = isset($template['variables']) ? $template['variables'] : array();
            
            // 선언되지 않은 변수 확인
            $undeclared = array_diff($template_variables, $declared_variables);
            if (!empty($undeclared)) {
                $warnings[] = "선언되지 않은 변수가 있습니다: " . implode(', ', $undeclared);
            }
            
            // 사용되지 않은 선언 변수 확인
            $unused = array_diff($declared_variables, $template_variables);
            if (!empty($unused)) {
                $warnings[] = "사용되지 않은 선언 변수가 있습니다: " . implode(', ', $unused);
            }
        }
        
        // 시스템 메시지 길이 확인
        if (isset($template['system_message']) && strlen($template['system_message']) > 1000) {
            $warnings[] = "시스템 메시지가 너무 깁니다 (1000자 초과)";
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        );
    }
    
    /**
     * 사용자 정의 템플릿 추가
     * 
     * @param string $template_id 템플릿 ID
     * @param array $template 템플릿 데이터
     * @return bool 성공 여부
     */
    public function add_custom_template($template_id, $template) {
        // 템플릿 유효성 검증
        $validation = $this->validate_template($template);
        if (!$validation['valid']) {
            throw new Exception("템플릿 유효성 검증 실패: " . implode(', ', $validation['errors']));
        }
        
        // 생성 시간 추가
        $template['created_at'] = current_time('mysql');
        $template['updated_at'] = current_time('mysql');
        
        $this->custom_templates[$template_id] = $template;
        
        return update_option('ainl_custom_templates', $this->custom_templates);
    }
    
    /**
     * 사용자 정의 템플릿 수정
     * 
     * @param string $template_id 템플릿 ID
     * @param array $template 템플릿 데이터
     * @return bool 성공 여부
     */
    public function update_custom_template($template_id, $template) {
        if (!isset($this->custom_templates[$template_id])) {
            throw new Exception("수정할 템플릿을 찾을 수 없습니다: {$template_id}");
        }
        
        // 템플릿 유효성 검증
        $validation = $this->validate_template($template);
        if (!$validation['valid']) {
            throw new Exception("템플릿 유효성 검증 실패: " . implode(', ', $validation['errors']));
        }
        
        // 기존 생성 시간 유지, 수정 시간 업데이트
        $template['created_at'] = $this->custom_templates[$template_id]['created_at'];
        $template['updated_at'] = current_time('mysql');
        
        $this->custom_templates[$template_id] = $template;
        
        return update_option('ainl_custom_templates', $this->custom_templates);
    }
    
    /**
     * 사용자 정의 템플릿 삭제
     * 
     * @param string $template_id 템플릿 ID
     * @return bool 성공 여부
     */
    public function delete_custom_template($template_id) {
        if (!isset($this->custom_templates[$template_id])) {
            return false;
        }
        
        unset($this->custom_templates[$template_id]);
        
        return update_option('ainl_custom_templates', $this->custom_templates);
    }
    
    /**
     * 템플릿 복제
     * 
     * @param string $source_id 원본 템플릿 ID
     * @param string $new_id 새 템플릿 ID
     * @param string $new_name 새 템플릿 이름
     * @return bool 성공 여부
     */
    public function duplicate_template($source_id, $new_id, $new_name) {
        $source_template = $this->get_template($source_id);
        
        if (!$source_template) {
            throw new Exception("복제할 템플릿을 찾을 수 없습니다: {$source_id}");
        }
        
        if (isset($this->custom_templates[$new_id])) {
            throw new Exception("이미 존재하는 템플릿 ID입니다: {$new_id}");
        }
        
        $new_template = $source_template;
        $new_template['name'] = $new_name;
        $new_template['description'] = $source_template['description'] . ' (복제본)';
        
        return $this->add_custom_template($new_id, $new_template);
    }
    
    /**
     * 템플릿 사용 통계
     * 
     * @return array 사용 통계
     */
    public function get_usage_statistics() {
        $stats = get_option('ainl_template_usage_stats', array());
        
        return array(
            'total_generations' => isset($stats['total_generations']) ? $stats['total_generations'] : 0,
            'template_usage' => isset($stats['template_usage']) ? $stats['template_usage'] : array(),
            'most_used_template' => $this->get_most_used_template($stats),
            'last_used' => isset($stats['last_used']) ? $stats['last_used'] : null
        );
    }
    
    /**
     * 템플릿 사용 기록
     * 
     * @param string $template_id 사용된 템플릿 ID
     */
    public function record_usage($template_id) {
        $stats = get_option('ainl_template_usage_stats', array(
            'total_generations' => 0,
            'template_usage' => array(),
            'last_used' => null
        ));
        
        $stats['total_generations']++;
        
        if (!isset($stats['template_usage'][$template_id])) {
            $stats['template_usage'][$template_id] = 0;
        }
        $stats['template_usage'][$template_id]++;
        
        $stats['last_used'] = current_time('mysql');
        
        update_option('ainl_template_usage_stats', $stats);
    }
    
    /**
     * 가장 많이 사용된 템플릿 찾기
     * 
     * @param array $stats 통계 데이터
     * @return string|null 가장 많이 사용된 템플릿 ID
     */
    private function get_most_used_template($stats) {
        if (!isset($stats['template_usage']) || empty($stats['template_usage'])) {
            return null;
        }
        
        return array_search(max($stats['template_usage']), $stats['template_usage']);
    }
    
    /**
     * 템플릿 내보내기
     * 
     * @param array $template_ids 내보낼 템플릿 ID 배열
     * @return string JSON 형태의 템플릿 데이터
     */
    public function export_templates($template_ids = null) {
        if ($template_ids === null) {
            $templates_to_export = $this->custom_templates;
        } else {
            $templates_to_export = array();
            foreach ($template_ids as $id) {
                if (isset($this->custom_templates[$id])) {
                    $templates_to_export[$id] = $this->custom_templates[$id];
                }
            }
        }
        
        $export_data = array(
            'version' => '1.0',
            'exported_at' => current_time('mysql'),
            'templates' => $templates_to_export
        );
        
        return wp_json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * 템플릿 가져오기
     * 
     * @param string $json_data JSON 형태의 템플릿 데이터
     * @param bool $overwrite 기존 템플릿 덮어쓰기 여부
     * @return array 가져오기 결과
     */
    public function import_templates($json_data, $overwrite = false) {
        $import_data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON 파싱 실패: ' . json_last_error_msg());
        }
        
        if (!isset($import_data['templates']) || !is_array($import_data['templates'])) {
            throw new Exception('유효하지 않은 템플릿 데이터 형식입니다.');
        }
        
        $imported = array();
        $skipped = array();
        $errors = array();
        
        foreach ($import_data['templates'] as $id => $template) {
            try {
                // 기존 템플릿 존재 확인
                if (isset($this->custom_templates[$id]) && !$overwrite) {
                    $skipped[] = $id;
                    continue;
                }
                
                // 템플릿 유효성 검증
                $validation = $this->validate_template($template);
                if (!$validation['valid']) {
                    $errors[$id] = implode(', ', $validation['errors']);
                    continue;
                }
                
                // 템플릿 추가/수정
                if (isset($this->custom_templates[$id])) {
                    $this->update_custom_template($id, $template);
                } else {
                    $this->add_custom_template($id, $template);
                }
                
                $imported[] = $id;
                
            } catch (Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }
        
        return array(
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_processed' => count($import_data['templates'])
        );
    }
} 
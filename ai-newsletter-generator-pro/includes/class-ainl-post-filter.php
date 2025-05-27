<?php
/**
 * 게시물 고급 필터링 클래스
 * 복잡한 필터 조건과 사용자 정의 필터를 관리합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Post_Filter {
    
    /**
     * 필터 프리셋
     */
    private $filter_presets = array(
        'recent_popular' => array(
            'name' => '최근 인기 게시물',
            'description' => '최근 7일간 댓글이 많은 게시물',
            'filters' => array(
                'date_range' => 7,
                'order_by' => 'comment_count',
                'order' => 'DESC',
                'min_comments' => 1
            )
        ),
        'featured_content' => array(
            'name' => '추천 콘텐츠',
            'description' => '대표 이미지가 있는 최신 게시물',
            'filters' => array(
                'date_range' => 30,
                'has_featured_image' => true,
                'min_content_length' => 500
            )
        ),
        'author_highlights' => array(
            'name' => '작성자 하이라이트',
            'description' => '특정 작성자의 우수 게시물',
            'filters' => array(
                'date_range' => 60,
                'min_content_length' => 1000,
                'order_by' => 'modified'
            )
        )
    );
    
    /**
     * 커스텀 필드 매핑
     */
    private $custom_field_filters = array(
        'reading_time' => array(
            'meta_key' => '_reading_time',
            'type' => 'numeric',
            'label' => '읽기 시간 (분)'
        ),
        'difficulty_level' => array(
            'meta_key' => '_difficulty_level',
            'type' => 'select',
            'label' => '난이도',
            'options' => array('beginner', 'intermediate', 'advanced')
        ),
        'content_type' => array(
            'meta_key' => '_content_type',
            'type' => 'select',
            'label' => '콘텐츠 타입',
            'options' => array('tutorial', 'news', 'review', 'opinion')
        )
    );
    
    /**
     * 생성자
     */
    public function __construct() {
        // 사용자 정의 필터 프리셋 로드
        $this->load_user_presets();
    }
    
    /**
     * 고급 필터 적용
     * 
     * @param array $base_query_args 기본 쿼리 인수
     * @param array $advanced_filters 고급 필터 옵션
     * @return array 수정된 쿼리 인수
     */
    public function apply_advanced_filters($base_query_args, $advanced_filters) {
        $query_args = $base_query_args;
        
        // 댓글 수 필터
        if (isset($advanced_filters['min_comments']) && $advanced_filters['min_comments'] > 0) {
            $query_args['meta_query'][] = array(
                'key' => 'comment_count',
                'value' => intval($advanced_filters['min_comments']),
                'compare' => '>='
            );
        }
        
        // 대표 이미지 필수 여부
        if (isset($advanced_filters['has_featured_image']) && $advanced_filters['has_featured_image']) {
            $query_args['meta_query'][] = array(
                'key' => '_thumbnail_id',
                'compare' => 'EXISTS'
            );
        }
        
        // 특정 단어 포함/제외
        if (!empty($advanced_filters['include_keywords'])) {
            $keywords = $this->sanitize_keywords($advanced_filters['include_keywords']);
            $query_args['s'] = implode(' ', $keywords);
        }
        
        if (!empty($advanced_filters['exclude_keywords'])) {
            $exclude_keywords = $this->sanitize_keywords($advanced_filters['exclude_keywords']);
            // WordPress는 기본적으로 exclude 검색을 지원하지 않으므로 post__not_in 사용
            $excluded_posts = $this->get_posts_with_keywords($exclude_keywords);
            if (!empty($excluded_posts)) {
                $query_args['post__not_in'] = array_merge(
                    isset($query_args['post__not_in']) ? $query_args['post__not_in'] : array(),
                    $excluded_posts
                );
            }
        }
        
        // 게시물 상태 조합
        if (!empty($advanced_filters['post_status_combination'])) {
            $query_args['post_status'] = $this->sanitize_post_status_combination($advanced_filters['post_status_combination']);
        }
        
        // 날짜 범위 세부 설정
        if (!empty($advanced_filters['date_range_custom'])) {
            $query_args['date_query'] = $this->build_custom_date_query($advanced_filters['date_range_custom']);
        }
        
        // 커스텀 필드 필터
        if (!empty($advanced_filters['custom_fields'])) {
            $custom_meta_query = $this->build_custom_field_query($advanced_filters['custom_fields']);
            if (!empty($custom_meta_query)) {
                $query_args['meta_query'] = array_merge(
                    isset($query_args['meta_query']) ? $query_args['meta_query'] : array(),
                    $custom_meta_query
                );
            }
        }
        
        // 메타 쿼리 관계 설정
        if (isset($query_args['meta_query']) && count($query_args['meta_query']) > 1) {
            $query_args['meta_query']['relation'] = 'AND';
        }
        
        // 분류 체계 고급 필터
        if (!empty($advanced_filters['taxonomy_advanced'])) {
            $query_args['tax_query'] = $this->build_advanced_taxonomy_query($advanced_filters['taxonomy_advanced']);
        }
        
        return $query_args;
    }
    
    /**
     * 키워드 정리
     */
    private function sanitize_keywords($keywords) {
        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }
        
        $sanitized = array();
        foreach ($keywords as $keyword) {
            $keyword = trim(sanitize_text_field($keyword));
            if (!empty($keyword)) {
                $sanitized[] = $keyword;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * 특정 키워드를 포함한 게시물 ID 가져오기
     */
    private function get_posts_with_keywords($keywords) {
        global $wpdb;
        
        $keyword_conditions = array();
        $placeholders = array();
        
        foreach ($keywords as $keyword) {
            $keyword_conditions[] = "(post_title LIKE %s OR post_content LIKE %s)";
            $placeholders[] = '%' . $wpdb->esc_like($keyword) . '%';
            $placeholders[] = '%' . $wpdb->esc_like($keyword) . '%';
        }
        
        if (empty($keyword_conditions)) {
            return array();
        }
        
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE " . implode(' OR ', $keyword_conditions);
        $prepared_sql = $wpdb->prepare($sql, $placeholders);
        
        return $wpdb->get_col($prepared_sql);
    }
    
    /**
     * 게시물 상태 조합 정리
     */
    private function sanitize_post_status_combination($status_combination) {
        $valid_statuses = array('publish', 'private', 'draft', 'pending', 'future', 'trash');
        $sanitized = array();
        
        if (is_string($status_combination)) {
            $status_combination = explode(',', $status_combination);
        }
        
        foreach ($status_combination as $status) {
            $status = trim(sanitize_key($status));
            if (in_array($status, $valid_statuses)) {
                $sanitized[] = $status;
            }
        }
        
        return empty($sanitized) ? array('publish') : $sanitized;
    }
    
    /**
     * 커스텀 날짜 쿼리 구성
     */
    private function build_custom_date_query($date_config) {
        $date_query = array();
        
        // 시작 날짜
        if (!empty($date_config['after'])) {
            $date_query['after'] = sanitize_text_field($date_config['after']);
        }
        
        // 종료 날짜
        if (!empty($date_config['before'])) {
            $date_query['before'] = sanitize_text_field($date_config['before']);
        }
        
        // 특정 연도
        if (!empty($date_config['year'])) {
            $date_query['year'] = intval($date_config['year']);
        }
        
        // 특정 월
        if (!empty($date_config['month'])) {
            $date_query['month'] = intval($date_config['month']);
        }
        
        // 특정 요일
        if (!empty($date_config['dayofweek'])) {
            $date_query['dayofweek'] = intval($date_config['dayofweek']);
        }
        
        // 시간 범위
        if (!empty($date_config['hour_after']) || !empty($date_config['hour_before'])) {
            $date_query['hour'] = array();
            if (!empty($date_config['hour_after'])) {
                $date_query['hour']['after'] = intval($date_config['hour_after']);
            }
            if (!empty($date_config['hour_before'])) {
                $date_query['hour']['before'] = intval($date_config['hour_before']);
            }
        }
        
        return array($date_query);
    }
    
    /**
     * 커스텀 필드 쿼리 구성
     */
    private function build_custom_field_query($custom_fields) {
        $meta_query = array();
        
        foreach ($custom_fields as $field_key => $field_config) {
            if (!isset($this->custom_field_filters[$field_key])) {
                continue;
            }
            
            $field_def = $this->custom_field_filters[$field_key];
            $meta_key = $field_def['meta_key'];
            
            switch ($field_def['type']) {
                case 'numeric':
                    if (isset($field_config['min']) || isset($field_config['max'])) {
                        if (isset($field_config['min']) && isset($field_config['max'])) {
                            $meta_query[] = array(
                                'key' => $meta_key,
                                'value' => array(intval($field_config['min']), intval($field_config['max'])),
                                'type' => 'NUMERIC',
                                'compare' => 'BETWEEN'
                            );
                        } elseif (isset($field_config['min'])) {
                            $meta_query[] = array(
                                'key' => $meta_key,
                                'value' => intval($field_config['min']),
                                'type' => 'NUMERIC',
                                'compare' => '>='
                            );
                        } elseif (isset($field_config['max'])) {
                            $meta_query[] = array(
                                'key' => $meta_key,
                                'value' => intval($field_config['max']),
                                'type' => 'NUMERIC',
                                'compare' => '<='
                            );
                        }
                    }
                    break;
                
                case 'select':
                    if (!empty($field_config['value'])) {
                        $values = is_array($field_config['value']) ? $field_config['value'] : array($field_config['value']);
                        $sanitized_values = array();
                        
                        foreach ($values as $value) {
                            if (in_array($value, $field_def['options'])) {
                                $sanitized_values[] = sanitize_text_field($value);
                            }
                        }
                        
                        if (!empty($sanitized_values)) {
                            $meta_query[] = array(
                                'key' => $meta_key,
                                'value' => $sanitized_values,
                                'compare' => 'IN'
                            );
                        }
                    }
                    break;
            }
        }
        
        return $meta_query;
    }
    
    /**
     * 고급 분류 체계 쿼리 구성
     */
    private function build_advanced_taxonomy_query($taxonomy_config) {
        $tax_query = array();
        
        foreach ($taxonomy_config as $taxonomy => $config) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            
            $tax_query_item = array(
                'taxonomy' => $taxonomy,
                'field' => isset($config['field']) ? $config['field'] : 'term_id',
                'operator' => isset($config['operator']) ? $config['operator'] : 'IN'
            );
            
            // 용어 처리
            if (!empty($config['terms'])) {
                if (is_array($config['terms'])) {
                    $tax_query_item['terms'] = array_map('intval', $config['terms']);
                } else {
                    $tax_query_item['terms'] = intval($config['terms']);
                }
            }
            
            // 하위 용어 포함 여부
            if (isset($config['include_children'])) {
                $tax_query_item['include_children'] = (bool) $config['include_children'];
            }
            
            $tax_query[] = $tax_query_item;
        }
        
        // 관계 설정
        if (count($tax_query) > 1) {
            $tax_query['relation'] = isset($taxonomy_config['relation']) ? $taxonomy_config['relation'] : 'AND';
        }
        
        return $tax_query;
    }
    
    /**
     * 필터 프리셋 적용
     * 
     * @param string $preset_name 프리셋 이름
     * @param array $additional_filters 추가 필터
     * @return array 필터 옵션
     */
    public function apply_filter_preset($preset_name, $additional_filters = array()) {
        if (!isset($this->filter_presets[$preset_name])) {
            return $additional_filters;
        }
        
        $preset_filters = $this->filter_presets[$preset_name]['filters'];
        return array_merge($preset_filters, $additional_filters);
    }
    
    /**
     * 사용자 정의 프리셋 저장
     * 
     * @param string $name 프리셋 이름
     * @param array $filters 필터 설정
     * @param string $description 설명
     * @return bool 성공 여부
     */
    public function save_user_preset($name, $filters, $description = '') {
        $user_presets = get_option('ainl_user_filter_presets', array());
        
        $user_presets[sanitize_key($name)] = array(
            'name' => sanitize_text_field($name),
            'description' => sanitize_textarea_field($description),
            'filters' => $this->sanitize_filter_array($filters),
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        return update_option('ainl_user_filter_presets', $user_presets);
    }
    
    /**
     * 사용자 정의 프리셋 삭제
     * 
     * @param string $name 프리셋 이름
     * @return bool 성공 여부
     */
    public function delete_user_preset($name) {
        $user_presets = get_option('ainl_user_filter_presets', array());
        $key = sanitize_key($name);
        
        if (isset($user_presets[$key])) {
            unset($user_presets[$key]);
            return update_option('ainl_user_filter_presets', $user_presets);
        }
        
        return false;
    }
    
    /**
     * 사용자 정의 프리셋 로드
     */
    private function load_user_presets() {
        $user_presets = get_option('ainl_user_filter_presets', array());
        
        foreach ($user_presets as $key => $preset) {
            $this->filter_presets['user_' . $key] = $preset;
        }
    }
    
    /**
     * 모든 프리셋 가져오기
     * 
     * @return array 프리셋 목록
     */
    public function get_all_presets() {
        return $this->filter_presets;
    }
    
    /**
     * 필터 배열 정리
     */
    private function sanitize_filter_array($filters) {
        $sanitized = array();
        
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'date_range':
                case 'max_posts':
                case 'min_content_length':
                case 'max_content_length':
                case 'min_comments':
                    $sanitized[$key] = absint($value);
                    break;
                
                case 'post_types':
                case 'include_categories':
                case 'exclude_categories':
                case 'include_tags':
                case 'exclude_tags':
                case 'include_authors':
                case 'exclude_authors':
                    $sanitized[$key] = is_array($value) ? array_map('absint', $value) : array(absint($value));
                    break;
                
                case 'order_by':
                case 'order':
                    $sanitized[$key] = sanitize_key($value);
                    break;
                
                case 'include_featured_image':
                case 'include_meta_data':
                case 'cache_results':
                case 'has_featured_image':
                    $sanitized[$key] = (bool) $value;
                    break;
                
                case 'include_keywords':
                case 'exclude_keywords':
                    $sanitized[$key] = $this->sanitize_keywords($value);
                    break;
                
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * 필터 통계 가져오기
     * 
     * @param array $filters 적용된 필터
     * @return array 통계 정보
     */
    public function get_filter_statistics($filters) {
        $stats = array(
            'total_filters_applied' => 0,
            'filter_types' => array(),
            'complexity_score' => 0
        );
        
        // 적용된 필터 수 계산
        foreach ($filters as $key => $value) {
            if (!empty($value) && $value !== false) {
                $stats['total_filters_applied']++;
                
                // 필터 타입 분류
                if (in_array($key, array('include_categories', 'exclude_categories', 'include_tags', 'exclude_tags'))) {
                    $stats['filter_types']['taxonomy'] = true;
                } elseif (in_array($key, array('date_range', 'date_range_custom'))) {
                    $stats['filter_types']['date'] = true;
                } elseif (in_array($key, array('include_authors', 'exclude_authors'))) {
                    $stats['filter_types']['author'] = true;
                } elseif (in_array($key, array('min_content_length', 'max_content_length'))) {
                    $stats['filter_types']['content'] = true;
                } elseif (strpos($key, 'custom_') === 0) {
                    $stats['filter_types']['custom'] = true;
                }
            }
        }
        
        // 복잡도 점수 계산 (1-10)
        $stats['complexity_score'] = min(10, $stats['total_filters_applied'] + count($stats['filter_types']));
        
        return $stats;
    }
    
    /**
     * 필터 검증
     * 
     * @param array $filters 검증할 필터
     * @return array 검증 결과
     */
    public function validate_filters($filters) {
        $validation = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array()
        );
        
        // 날짜 범위 검증
        if (isset($filters['date_range']) && $filters['date_range'] > 365) {
            $validation['warnings'][] = '날짜 범위가 1년을 초과합니다. 성능에 영향을 줄 수 있습니다.';
        }
        
        // 최대 게시물 수 검증
        if (isset($filters['max_posts']) && $filters['max_posts'] > 100) {
            $validation['warnings'][] = '최대 게시물 수가 100개를 초과합니다. 성능에 영향을 줄 수 있습니다.';
        }
        
        // 콘텐츠 길이 검증
        if (isset($filters['min_content_length']) && isset($filters['max_content_length'])) {
            if ($filters['min_content_length'] >= $filters['max_content_length']) {
                $validation['valid'] = false;
                $validation['errors'][] = '최소 콘텐츠 길이가 최대 길이보다 크거나 같습니다.';
            }
        }
        
        // 분류 체계 검증
        if (!empty($filters['include_categories']) && !empty($filters['exclude_categories'])) {
            $overlap = array_intersect($filters['include_categories'], $filters['exclude_categories']);
            if (!empty($overlap)) {
                $validation['valid'] = false;
                $validation['errors'][] = '포함 카테고리와 제외 카테고리에 중복이 있습니다.';
            }
        }
        
        return $validation;
    }
} 
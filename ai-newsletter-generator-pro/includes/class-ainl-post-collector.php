<?php
/**
 * 게시물 수집 클래스
 * WordPress 게시물을 필터링하고 수집하는 핵심 시스템입니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Post_Collector {
    
    /**
     * 기본 필터 옵션
     */
    private $default_options = array(
        'post_types' => array('post'),
        'post_status' => array('publish'),
        'date_range' => 7,                    // 최근 7일
        'max_posts' => 10,                    // 최대 10개 게시물
        'min_content_length' => 100,          // 최소 100자
        'max_content_length' => 5000,         // 최대 5000자
        'include_categories' => array(),      // 포함할 카테고리
        'exclude_categories' => array(),      // 제외할 카테고리
        'include_tags' => array(),            // 포함할 태그
        'exclude_tags' => array(),            // 제외할 태그
        'include_authors' => array(),         // 포함할 작성자
        'exclude_authors' => array(),         // 제외할 작성자
        'include_featured_image' => true,     // 대표 이미지 포함
        'include_meta_data' => true,          // 메타데이터 포함
        'order_by' => 'date',                 // 정렬 기준
        'order' => 'DESC',                    // 정렬 순서
        'cache_results' => true,              // 결과 캐싱
        'cache_duration' => 3600              // 캐시 지속 시간 (1시간)
    );
    
    /**
     * 캐시 키 접두사
     */
    const CACHE_PREFIX = 'ainl_posts_';
    const CACHE_VERSION = '1.0';
    const MAX_CACHE_SIZE = 50; // 최대 캐시 항목 수
    
    /**
     * 고급 필터 인스턴스
     */
    private $advanced_filter;
    
    /**
     * 성능 모니터링 데이터
     */
    private $performance_data = array();
    
    /**
     * 메모리 사용량 추적
     */
    private $memory_tracker = array();
    
    /**
     * 쿼리 실행 시간 추적
     */
    private $query_timer = array();
    
    /**
     * 생성자
     */
    public function __construct() {
        // 고급 필터 인스턴스 생성
        $this->advanced_filter = new AINL_Post_Filter();
    }
    
    /**
     * 게시물 수집 메인 메서드 (성능 최적화 버전)
     * 
     * @param array $options 필터링 옵션
     * @return array 수집된 게시물 배열
     */
    public function collect_posts($options = array()) {
        // 성능 모니터링 시작
        $this->start_performance_monitoring('collect_posts');
        
        // 옵션 병합
        $options = wp_parse_args($options, $this->default_options);
        
        // 성능 최적화 옵션 추가
        $performance_options = array(
            'enable_query_optimization' => true,
            'enable_memory_management' => true,
            'batch_size' => 100,
            'use_object_cache' => true,
            'preload_meta' => false,
            'lazy_load_images' => true
        );
        
        $options = array_merge($performance_options, $options);
        
        // 필터 프리셋 적용
        if (!empty($options['filter_preset'])) {
            $preset_filters = $this->advanced_filter->apply_filter_preset($options['filter_preset']);
            $options = array_merge($preset_filters, $options);
            unset($options['filter_preset']);
        }
        
        // 필터 검증
        if (!empty($options['validate_filters'])) {
            $validation = $this->advanced_filter->validate_filters($options);
            if (!$validation['valid']) {
                $this->end_performance_monitoring('collect_posts');
                return array(
                    'error' => true,
                    'message' => '필터 검증 실패',
                    'errors' => $validation['errors']
                );
            }
        }
        
        // 보안 검증
        $options = $this->sanitize_options($options);
        
        // 고급 캐시 확인
        if ($options['cache_results']) {
            $cached_result = $this->get_advanced_cached_result($options);
            if ($cached_result !== false) {
                $this->end_performance_monitoring('collect_posts');
                return $cached_result;
            }
        }
        
        // 메모리 관리 시작
        if ($options['enable_memory_management']) {
            $this->start_memory_tracking();
        }
        
        // 최적화된 게시물 쿼리 실행
        if ($options['enable_query_optimization']) {
            $posts = $this->query_posts_optimized($options);
        } else {
            $posts = $this->query_posts($options);
        }
        
        // 배치 처리로 게시물 데이터 처리
        $processed_posts = $this->process_posts_batch($posts, $options);
        
        // 고급 결과 캐싱
        if ($options['cache_results']) {
            $this->cache_advanced_result($options, $processed_posts);
        }
        
        // 메모리 정리
        if ($options['enable_memory_management']) {
            $this->cleanup_memory();
        }
        
        // 성능 모니터링 종료
        $this->end_performance_monitoring('collect_posts');
        
        return $processed_posts;
    }
    
    /**
     * 최적화된 게시물 쿼리
     * 
     * @param array $options 필터링 옵션
     * @return array WP_Post 객체 배열
     */
    private function query_posts_optimized($options) {
        $this->start_performance_monitoring('query_posts_optimized');
        
        // 기본 쿼리 인수 (최적화됨)
        $query_args = array(
            'post_type' => $options['post_types'],
            'post_status' => $options['post_status'],
            'posts_per_page' => $options['max_posts'],
            'orderby' => $options['order_by'],
            'order' => $options['order'],
            'no_found_rows' => true,  // 성능 최적화
            'update_post_meta_cache' => $options['preload_meta'], // 조건부 메타 캐시
            'update_post_term_cache' => true,
            'suppress_filters' => false, // 필터 허용
            'cache_results' => $options['use_object_cache'],
            'fields' => 'all' // 필요한 필드만 선택 가능
        );
        
        // 메모리 절약을 위한 필드 선택
        if (!$options['include_meta_data'] && !$options['include_featured_image']) {
            $query_args['fields'] = 'ids'; // ID만 가져오기
        }
        
        // 날짜 범위 설정 (인덱스 활용)
        if ($options['date_range'] > 0) {
            $query_args['date_query'] = array(
                array(
                    'after' => date('Y-m-d', strtotime("-{$options['date_range']} days")),
                    'inclusive' => true,
                    'column' => 'post_date' // 명시적 컬럼 지정
                )
            );
        }
        
        // 최적화된 분류체계 쿼리
        $this->add_optimized_taxonomy_queries($query_args, $options);
        
        // 작성자 필터 (인덱스 활용)
        if (!empty($options['include_authors'])) {
            $query_args['author__in'] = $options['include_authors'];
        }
        
        if (!empty($options['exclude_authors'])) {
            $query_args['author__not_in'] = $options['exclude_authors'];
        }
        
        // 고급 필터링 시스템 적용
        if (!empty($options['advanced_filters'])) {
            $query_args = $this->advanced_filter->apply_advanced_filters($query_args, $options['advanced_filters']);
        }
        
        // 쿼리 실행 및 시간 측정
        $start_time = microtime(true);
        $query = new WP_Query($query_args);
        $query_time = microtime(true) - $start_time;
        
        // 성능 데이터 기록
        $this->record_query_performance($query_args, $query_time, $query->found_posts);
        
        $this->end_performance_monitoring('query_posts_optimized');
        
        return $query->posts;
    }
    
    /**
     * 최적화된 분류체계 쿼리 추가
     */
    private function add_optimized_taxonomy_queries(&$query_args, $options) {
        $tax_queries = array();
        
        // 카테고리 필터 (최적화됨)
        if (!empty($options['include_categories']) || !empty($options['exclude_categories'])) {
            if (!empty($options['include_categories'])) {
                $tax_queries[] = array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $options['include_categories'],
                    'operator' => 'IN',
                    'include_children' => false // 성능 최적화
                );
            }
            
            if (!empty($options['exclude_categories'])) {
                $tax_queries[] = array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $options['exclude_categories'],
                    'operator' => 'NOT IN',
                    'include_children' => false
                );
            }
        }
        
        // 태그 필터 (최적화됨)
        if (!empty($options['include_tags']) || !empty($options['exclude_tags'])) {
            if (!empty($options['include_tags'])) {
                $tax_queries[] = array(
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => $options['include_tags'],
                    'operator' => 'IN'
                );
            }
            
            if (!empty($options['exclude_tags'])) {
                $tax_queries[] = array(
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => $options['exclude_tags'],
                    'operator' => 'NOT IN'
                );
            }
        }
        
        // 분류체계 쿼리 설정
        if (!empty($tax_queries)) {
            $query_args['tax_query'] = $tax_queries;
            if (count($tax_queries) > 1) {
                $query_args['tax_query']['relation'] = 'AND';
            }
        }
    }
    
    /**
     * 배치 처리로 게시물 데이터 처리
     * 
     * @param array $posts WP_Post 객체 배열
     * @param array $options 처리 옵션
     * @return array 처리된 게시물 데이터 배열
     */
    private function process_posts_batch($posts, $options) {
        $this->start_performance_monitoring('process_posts_batch');
        
        $processed_posts = array();
        $batch_size = isset($options['batch_size']) ? $options['batch_size'] : 50;
        $total_posts = count($posts);
        
        // 배치 단위로 처리
        for ($i = 0; $i < $total_posts; $i += $batch_size) {
            $batch = array_slice($posts, $i, $batch_size);
            $batch_processed = $this->process_post_batch($batch, $options);
            $processed_posts = array_merge($processed_posts, $batch_processed);
            
            // 메모리 정리 (배치마다)
            if ($options['enable_memory_management']) {
                $this->cleanup_batch_memory();
            }
        }
        
        $this->end_performance_monitoring('process_posts_batch');
        
        return $processed_posts;
    }
    
    /**
     * 단일 배치 처리
     */
    private function process_post_batch($batch, $options) {
        $processed_batch = array();
        
        foreach ($batch as $post) {
            $post_data = $this->process_single_post_optimized($post, $options);
            
            // 콘텐츠 길이 필터링
            if ($this->passes_content_filter($post_data, $options)) {
                $processed_batch[] = $post_data;
            }
        }
        
        return $processed_batch;
    }
    
    /**
     * 최적화된 단일 게시물 처리
     */
    private function process_single_post_optimized($post, $options) {
        // 기본 데이터만 먼저 처리
        $post_data = array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'permalink' => get_permalink($post),
            'date' => get_the_date('Y-m-d H:i:s', $post),
            'modified' => get_the_modified_date('Y-m-d H:i:s', $post),
            'author' => array(
                'id' => $post->post_author,
                'name' => get_the_author_meta('display_name', $post->post_author)
            )
        );
        
        // 조건부 데이터 로딩 (지연 로딩)
        if (!$options['lazy_load_images'] || $options['include_featured_image']) {
            $post_data['featured_image'] = $this->get_featured_image_data($post);
        }
        
        // 콘텐츠 처리 (포맷팅 옵션 적용)
        $content_format = isset($options['content_format']) ? $options['content_format'] : array();
        $post_data['content'] = $this->get_post_content($post, $content_format);
        
        // 요약 처리
        $excerpt_format = isset($options['excerpt_format']) ? $options['excerpt_format'] : array();
        $post_data['excerpt'] = $this->get_post_excerpt($post, $excerpt_format);
        
        // 분류체계 정보 (캐시 활용)
        $post_data['categories'] = $this->get_post_categories_cached($post);
        $post_data['tags'] = $this->get_post_tags_cached($post);
        
        // 메타데이터 (조건부)
        if ($options['include_meta_data']) {
            $post_data['meta_data'] = $this->get_post_meta_data($post);
        }
        
        return $post_data;
    }
    
    /**
     * 콘텐츠 필터 통과 여부 확인
     */
    private function passes_content_filter($post_data, $options) {
        $content_length = strlen(strip_tags($post_data['content']));
        
        return ($content_length >= $options['min_content_length'] && 
                $content_length <= $options['max_content_length']);
    }
    
    /**
     * 캐시된 카테고리 정보 가져오기
     */
    private function get_post_categories_cached($post) {
        $cache_key = 'post_categories_' . $post->ID;
        $cached = wp_cache_get($cache_key, 'ainl_posts');
        
        if ($cached !== false) {
            return $cached;
        }
        
        $categories = $this->get_post_categories($post);
        wp_cache_set($cache_key, $categories, 'ainl_posts', 300); // 5분 캐시
        
        return $categories;
    }
    
    /**
     * 캐시된 태그 정보 가져오기
     */
    private function get_post_tags_cached($post) {
        $cache_key = 'post_tags_' . $post->ID;
        $cached = wp_cache_get($cache_key, 'ainl_posts');
        
        if ($cached !== false) {
            return $cached;
        }
        
        $tags = $this->get_post_tags($post);
        wp_cache_set($cache_key, $tags, 'ainl_posts', 300); // 5분 캐시
        
        return $tags;
    }
    
    /**
     * 게시물 수집 메인 메서드
     * 
     * @param array $options 필터링 옵션
     * @return array 수집된 게시물 배열
     */
    public function collect_posts($options = array()) {
        // 옵션 병합
        $options = wp_parse_args($options, $this->default_options);
        
        // 필터 프리셋 적용
        if (!empty($options['filter_preset'])) {
            $preset_filters = $this->advanced_filter->apply_filter_preset($options['filter_preset']);
            $options = array_merge($preset_filters, $options);
            unset($options['filter_preset']); // 프리셋 키 제거
        }
        
        // 필터 검증
        if (!empty($options['validate_filters'])) {
            $validation = $this->advanced_filter->validate_filters($options);
            if (!$validation['valid']) {
                return array(
                    'error' => true,
                    'message' => '필터 검증 실패',
                    'errors' => $validation['errors']
                );
            }
        }
        
        // 보안 검증
        $options = $this->sanitize_options($options);
        
        // 캐시 확인
        if ($options['cache_results']) {
            $cached_result = $this->get_cached_result($options);
            if ($cached_result !== false) {
                return $cached_result;
            }
        }
        
        // 게시물 쿼리 실행
        $posts = $this->query_posts($options);
        
        // 게시물 데이터 처리
        $processed_posts = $this->process_posts($posts, $options);
        
        // 결과 캐싱
        if ($options['cache_results']) {
            $this->cache_result($options, $processed_posts);
        }
        
        return $processed_posts;
    }
    
    /**
     * 옵션 보안 검증 및 정리
     * 
     * @param array $options 원본 옵션
     * @return array 정리된 옵션
     */
    private function sanitize_options($options) {
        $sanitized = array();
        
        // 게시물 타입 검증
        $sanitized['post_types'] = $this->sanitize_post_types($options['post_types']);
        
        // 게시물 상태 검증
        $sanitized['post_status'] = $this->sanitize_post_status($options['post_status']);
        
        // 숫자 값 검증
        $sanitized['date_range'] = absint($options['date_range']);
        $sanitized['max_posts'] = min(absint($options['max_posts']), 100); // 최대 100개 제한
        $sanitized['min_content_length'] = absint($options['min_content_length']);
        $sanitized['max_content_length'] = absint($options['max_content_length']);
        
        // 배열 값 검증
        $sanitized['include_categories'] = $this->sanitize_term_ids($options['include_categories']);
        $sanitized['exclude_categories'] = $this->sanitize_term_ids($options['exclude_categories']);
        $sanitized['include_tags'] = $this->sanitize_term_ids($options['include_tags']);
        $sanitized['exclude_tags'] = $this->sanitize_term_ids($options['exclude_tags']);
        $sanitized['include_authors'] = $this->sanitize_user_ids($options['include_authors']);
        $sanitized['exclude_authors'] = $this->sanitize_user_ids($options['exclude_authors']);
        
        // 불린 값 검증
        $sanitized['include_featured_image'] = (bool) $options['include_featured_image'];
        $sanitized['include_meta_data'] = (bool) $options['include_meta_data'];
        $sanitized['cache_results'] = (bool) $options['cache_results'];
        
        // 정렬 옵션 검증
        $sanitized['order_by'] = $this->sanitize_order_by($options['order_by']);
        $sanitized['order'] = in_array(strtoupper($options['order']), array('ASC', 'DESC')) ? 
                             strtoupper($options['order']) : 'DESC';
        
        // 캐시 지속 시간 검증
        $sanitized['cache_duration'] = min(absint($options['cache_duration']), 86400); // 최대 24시간
        
        return $sanitized;
    }
    
    /**
     * 게시물 타입 검증
     */
    private function sanitize_post_types($post_types) {
        if (!is_array($post_types)) {
            $post_types = array($post_types);
        }
        
        $valid_post_types = get_post_types(array('public' => true));
        $sanitized = array();
        
        foreach ($post_types as $post_type) {
            $post_type = sanitize_key($post_type);
            if (in_array($post_type, $valid_post_types)) {
                $sanitized[] = $post_type;
            }
        }
        
        return empty($sanitized) ? array('post') : $sanitized;
    }
    
    /**
     * 게시물 상태 검증
     */
    private function sanitize_post_status($post_status) {
        if (!is_array($post_status)) {
            $post_status = array($post_status);
        }
        
        $valid_statuses = array('publish', 'private', 'draft', 'pending', 'future');
        $sanitized = array();
        
        foreach ($post_status as $status) {
            $status = sanitize_key($status);
            if (in_array($status, $valid_statuses)) {
                $sanitized[] = $status;
            }
        }
        
        return empty($sanitized) ? array('publish') : $sanitized;
    }
    
    /**
     * 용어 ID 검증
     */
    private function sanitize_term_ids($term_ids) {
        if (!is_array($term_ids)) {
            $term_ids = array($term_ids);
        }
        
        $sanitized = array();
        foreach ($term_ids as $term_id) {
            $term_id = absint($term_id);
            if ($term_id > 0) {
                $sanitized[] = $term_id;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * 사용자 ID 검증
     */
    private function sanitize_user_ids($user_ids) {
        if (!is_array($user_ids)) {
            $user_ids = array($user_ids);
        }
        
        $sanitized = array();
        foreach ($user_ids as $user_id) {
            $user_id = absint($user_id);
            if ($user_id > 0 && get_userdata($user_id)) {
                $sanitized[] = $user_id;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * 정렬 기준 검증
     */
    private function sanitize_order_by($order_by) {
        $valid_order_by = array(
            'date', 'modified', 'title', 'menu_order', 'rand',
            'comment_count', 'meta_value', 'meta_value_num'
        );
        
        return in_array($order_by, $valid_order_by) ? $order_by : 'date';
    }
    
    /**
     * WP_Query를 사용한 게시물 쿼리
     * 
     * @param array $options 필터링 옵션
     * @return array WP_Post 객체 배열
     */
    private function query_posts($options) {
        // 기본 쿼리 인수
        $query_args = array(
            'post_type' => $options['post_types'],
            'post_status' => $options['post_status'],
            'posts_per_page' => $options['max_posts'],
            'orderby' => $options['order_by'],
            'order' => $options['order'],
            'no_found_rows' => true,  // 성능 최적화
            'update_post_meta_cache' => $options['include_meta_data'],
            'update_post_term_cache' => true
        );
        
        // 날짜 범위 설정
        if ($options['date_range'] > 0) {
            $query_args['date_query'] = array(
                array(
                    'after' => date('Y-m-d', strtotime("-{$options['date_range']} days")),
                    'inclusive' => true
                )
            );
        }
        
        // 카테고리 필터
        if (!empty($options['include_categories']) || !empty($options['exclude_categories'])) {
            $query_args['tax_query'] = array();
            
            if (!empty($options['include_categories'])) {
                $query_args['tax_query'][] = array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $options['include_categories'],
                    'operator' => 'IN'
                );
            }
            
            if (!empty($options['exclude_categories'])) {
                $query_args['tax_query'][] = array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $options['exclude_categories'],
                    'operator' => 'NOT IN'
                );
            }
            
            if (count($query_args['tax_query']) > 1) {
                $query_args['tax_query']['relation'] = 'AND';
            }
        }
        
        // 태그 필터
        if (!empty($options['include_tags']) || !empty($options['exclude_tags'])) {
            if (!isset($query_args['tax_query'])) {
                $query_args['tax_query'] = array();
            }
            
            if (!empty($options['include_tags'])) {
                $query_args['tax_query'][] = array(
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => $options['include_tags'],
                    'operator' => 'IN'
                );
            }
            
            if (!empty($options['exclude_tags'])) {
                $query_args['tax_query'][] = array(
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => $options['exclude_tags'],
                    'operator' => 'NOT IN'
                );
            }
            
            if (count($query_args['tax_query']) > 1) {
                $query_args['tax_query']['relation'] = 'AND';
            }
        }
        
        // 작성자 필터
        if (!empty($options['include_authors'])) {
            $query_args['author__in'] = $options['include_authors'];
        }
        
        if (!empty($options['exclude_authors'])) {
            $query_args['author__not_in'] = $options['exclude_authors'];
        }
        
        // 고급 필터링 시스템 적용
        if (!empty($options['advanced_filters'])) {
            $query_args = $this->advanced_filter->apply_advanced_filters($query_args, $options['advanced_filters']);
        }
        
        // 쿼리 실행
        $query = new WP_Query($query_args);
        
        return $query->posts;
    }
    
    /**
     * 게시물 데이터 처리
     * 
     * @param array $posts WP_Post 객체 배열
     * @param array $options 처리 옵션
     * @return array 처리된 게시물 데이터 배열
     */
    private function process_posts($posts, $options) {
        $processed_posts = array();
        
        foreach ($posts as $post) {
            $post_data = $this->process_single_post($post, $options);
            
            // 콘텐츠 길이 필터링
            $content_length = strlen(strip_tags($post_data['content']));
            if ($content_length >= $options['min_content_length'] && 
                $content_length <= $options['max_content_length']) {
                $processed_posts[] = $post_data;
            }
        }
        
        return $processed_posts;
    }
    
    /**
     * 단일 게시물 데이터 처리
     * 
     * @param WP_Post $post 게시물 객체
     * @param array $options 처리 옵션
     * @return array 처리된 게시물 데이터
     */
    private function process_single_post($post, $options) {
        $post_data = array(
            'id' => $post->ID,
            'title' => get_the_title($post),
            'content' => $this->get_post_content($post),
            'excerpt' => $this->get_post_excerpt($post),
            'permalink' => get_permalink($post),
            'date' => get_the_date('Y-m-d H:i:s', $post),
            'modified' => get_the_modified_date('Y-m-d H:i:s', $post),
            'author' => array(
                'id' => $post->post_author,
                'name' => get_the_author_meta('display_name', $post->post_author),
                'email' => get_the_author_meta('email', $post->post_author)
            ),
            'categories' => $this->get_post_categories($post),
            'tags' => $this->get_post_tags($post)
        );
        
        // 대표 이미지 포함
        if ($options['include_featured_image']) {
            $post_data['featured_image'] = $this->get_featured_image_data($post);
        }
        
        // 메타데이터 포함
        if ($options['include_meta_data']) {
            $post_data['meta_data'] = $this->get_post_meta_data($post);
        }
        
        return $post_data;
    }
    
    /**
     * 게시물 콘텐츠 가져오기 및 정리
     * 
     * @param WP_Post $post 게시물 객체
     * @param array $format_options 포맷팅 옵션
     * @return string 정리된 콘텐츠
     */
    private function get_post_content($post, $format_options = array()) {
        $content = $post->post_content;
        
        // 기본 포맷팅 옵션
        $default_format_options = array(
            'remove_shortcodes' => true,
            'strip_html_tags' => false,
            'preserve_paragraphs' => true,
            'remove_empty_paragraphs' => true,
            'convert_links' => true,
            'max_length' => 0, // 0 = 제한 없음
            'excerpt_length' => 150,
            'remove_images' => false,
            'convert_headings' => true,
            'clean_whitespace' => true,
            'decode_entities' => true
        );
        
        $options = array_merge($default_format_options, $format_options);
        
        // 1. 숏코드 처리
        if ($options['remove_shortcodes']) {
            $content = $this->remove_shortcodes($content);
        } else {
            $content = do_shortcode($content);
        }
        
        // 2. HTML 엔티티 디코딩
        if ($options['decode_entities']) {
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        }
        
        // 3. 이미지 처리
        if ($options['remove_images']) {
            $content = $this->remove_images($content);
        } else {
            $content = $this->process_images($content);
        }
        
        // 4. 링크 처리
        if ($options['convert_links']) {
            $content = $this->process_links($content);
        }
        
        // 5. 제목 태그 처리
        if ($options['convert_headings']) {
            $content = $this->process_headings($content);
        }
        
        // 6. HTML 태그 제거 또는 정리
        if ($options['strip_html_tags']) {
            $content = $this->strip_html_safely($content);
        } else {
            $content = $this->clean_html($content);
        }
        
        // 7. 단락 처리
        if ($options['preserve_paragraphs'] && !$options['strip_html_tags']) {
            $content = wpautop($content);
        }
        
        // 8. 빈 단락 제거
        if ($options['remove_empty_paragraphs']) {
            $content = $this->remove_empty_paragraphs($content);
        }
        
        // 9. 공백 정리
        if ($options['clean_whitespace']) {
            $content = $this->clean_whitespace($content);
        }
        
        // 10. 길이 제한
        if ($options['max_length'] > 0) {
            $content = $this->limit_content_length($content, $options['max_length']);
        }
        
        return $content;
    }
    
    /**
     * 숏코드 제거
     */
    private function remove_shortcodes($content) {
        // WordPress 숏코드 패턴 제거
        $content = preg_replace('/\[[\w\s\-_="\'\/]*\]/', '', $content);
        $content = preg_replace('/\[\/[\w\s\-_]*\]/', '', $content);
        
        // 일반적인 숏코드 패턴 제거
        $shortcode_patterns = array(
            '/\[gallery[^\]]*\]/',
            '/\[caption[^\]]*\].*?\[\/caption\]/s',
            '/\[embed[^\]]*\].*?\[\/embed\]/s',
            '/\[video[^\]]*\].*?\[\/video\]/s',
            '/\[audio[^\]]*\].*?\[\/audio\]/s'
        );
        
        foreach ($shortcode_patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }
        
        return $content;
    }
    
    /**
     * 이미지 제거
     */
    private function remove_images($content) {
        // img 태그 제거
        $content = preg_replace('/<img[^>]*>/i', '', $content);
        
        // figure 태그 내 이미지 제거
        $content = preg_replace('/<figure[^>]*>.*?<\/figure>/is', '', $content);
        
        return $content;
    }
    
    /**
     * 이미지 처리 (alt 텍스트로 대체 등)
     */
    private function process_images($content) {
        // img 태그를 alt 텍스트로 대체
        $content = preg_replace_callback(
            '/<img[^>]*alt=["\']([^"\']*)["\'][^>]*>/i',
            function($matches) {
                $alt_text = trim($matches[1]);
                return $alt_text ? "[이미지: {$alt_text}]" : '[이미지]';
            },
            $content
        );
        
        // alt 속성이 없는 이미지 처리
        $content = preg_replace('/<img[^>]*>/i', '[이미지]', $content);
        
        return $content;
    }
    
    /**
     * 링크 처리
     */
    private function process_links($content) {
        // 링크를 텍스트와 URL로 변환
        $content = preg_replace_callback(
            '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
            function($matches) {
                $url = trim($matches[1]);
                $text = strip_tags(trim($matches[2]));
                
                if (empty($text)) {
                    return $url;
                }
                
                // 텍스트와 URL이 같으면 URL만 반환
                if ($text === $url) {
                    return $url;
                }
                
                return "{$text} ({$url})";
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * 제목 태그 처리
     */
    private function process_headings($content) {
        // h1-h6 태그를 마크다운 스타일로 변환
        $heading_patterns = array(
            '/<h1[^>]*>(.*?)<\/h1>/is' => "\n\n# $1\n\n",
            '/<h2[^>]*>(.*?)<\/h2>/is' => "\n\n## $1\n\n",
            '/<h3[^>]*>(.*?)<\/h3>/is' => "\n\n### $1\n\n",
            '/<h4[^>]*>(.*?)<\/h4>/is' => "\n\n#### $1\n\n",
            '/<h5[^>]*>(.*?)<\/h5>/is' => "\n\n##### $1\n\n",
            '/<h6[^>]*>(.*?)<\/h6>/is' => "\n\n###### $1\n\n"
        );
        
        foreach ($heading_patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        return $content;
    }
    
    /**
     * HTML 안전하게 제거
     */
    private function strip_html_safely($content) {
        // 허용할 태그 목록
        $allowed_tags = '<p><br><strong><b><em><i><u><ul><ol><li><blockquote>';
        
        // 스크립트와 스타일 태그 완전 제거
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
        
        // 허용된 태그만 남기고 제거
        $content = strip_tags($content, $allowed_tags);
        
        return $content;
    }
    
    /**
     * HTML 정리
     */
    private function clean_html($content) {
        // 위험한 태그 제거
        $dangerous_tags = array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input');
        
        foreach ($dangerous_tags as $tag) {
            $content = preg_replace("/<{$tag}[^>]*>.*?<\/{$tag}>/is", '', $content);
            $content = preg_replace("/<{$tag}[^>]*>/i", '', $content);
        }
        
        // 빈 태그 제거
        $content = preg_replace('/<([^>]+)>\s*<\/\1>/', '', $content);
        
        // 중복 공백 제거
        $content = preg_replace('/\s+/', ' ', $content);
        
        return $content;
    }
    
    /**
     * 빈 단락 제거
     */
    private function remove_empty_paragraphs($content) {
        // 빈 p 태그 제거
        $content = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $content);
        
        // 공백만 있는 p 태그 제거
        $content = preg_replace('/<p[^>]*>\s*(&nbsp;|\s)*\s*<\/p>/i', '', $content);
        
        return $content;
    }
    
    /**
     * 공백 정리
     */
    private function clean_whitespace($content) {
        // 연속된 공백을 하나로
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 연속된 줄바꿈을 최대 2개로 제한
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // 앞뒤 공백 제거
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * 콘텐츠 길이 제한
     */
    private function limit_content_length($content, $max_length) {
        if (strlen($content) <= $max_length) {
            return $content;
        }
        
        // 단어 단위로 자르기
        $content = wp_trim_words($content, $max_length / 6, '...');
        
        return $content;
    }
    
    /**
     * 게시물 요약 가져오기 및 생성
     * 
     * @param WP_Post $post 게시물 객체
     * @param array $excerpt_options 요약 옵션
     * @return string 생성된 요약
     */
    private function get_post_excerpt($post, $excerpt_options = array()) {
        $default_options = array(
            'length' => 25, // 단어 수
            'use_manual_excerpt' => true,
            'use_smart_generation' => true,
            'preserve_sentences' => true,
            'remove_quotes' => false,
            'ending' => '...',
            'min_length' => 10, // 최소 단어 수
            'max_length' => 50  // 최대 단어 수
        );
        
        $options = array_merge($default_options, $excerpt_options);
        
        // 1. 수동 요약이 있고 사용하도록 설정된 경우
        if ($options['use_manual_excerpt'] && !empty($post->post_excerpt)) {
            $excerpt = $post->post_excerpt;
            
            // 수동 요약도 길이 조정
            $word_count = str_word_count(strip_tags($excerpt));
            if ($word_count > $options['max_length']) {
                $excerpt = wp_trim_words($excerpt, $options['length'], $options['ending']);
            }
            
            return $excerpt;
        }
        
        // 2. 스마트 요약 생성
        if ($options['use_smart_generation']) {
            return $this->generate_smart_excerpt($post->post_content, $options['length'], $options);
        }
        
        // 3. 기본 요약 생성 (첫 N개 단어)
        $content = strip_tags($post->post_content);
        return wp_trim_words($content, $options['length'], $options['ending']);
    }
    
    /**
     * 게시물 카테고리 가져오기
     */
    private function get_post_categories($post) {
        $categories = get_the_category($post->ID);
        $category_data = array();
        
        foreach ($categories as $category) {
            $category_data[] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug
            );
        }
        
        return $category_data;
    }
    
    /**
     * 게시물 태그 가져오기
     */
    private function get_post_tags($post) {
        $tags = get_the_tags($post->ID);
        $tag_data = array();
        
        if ($tags) {
            foreach ($tags as $tag) {
                $tag_data[] = array(
                    'id' => $tag->term_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug
                );
            }
        }
        
        return $tag_data;
    }
    
    /**
     * 대표 이미지 데이터 가져오기
     */
    private function get_featured_image_data($post) {
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        
        if (!$thumbnail_id) {
            return null;
        }
        
        // 이미지 메타데이터 가져오기
        $attachment_meta = wp_get_attachment_metadata($thumbnail_id);
        $attachment_post = get_post($thumbnail_id);
        
        $image_data = array(
            'id' => $thumbnail_id,
            'url' => get_the_post_thumbnail_url($post->ID, 'full'),
            'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            'medium' => get_the_post_thumbnail_url($post->ID, 'medium'),
            'large' => get_the_post_thumbnail_url($post->ID, 'large'),
            'alt' => get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true),
            'caption' => wp_get_attachment_caption($thumbnail_id),
            'title' => $attachment_post ? $attachment_post->post_title : '',
            'description' => $attachment_post ? $attachment_post->post_content : '',
            'mime_type' => get_post_mime_type($thumbnail_id),
            'file_size' => 0,
            'dimensions' => array(
                'width' => 0,
                'height' => 0
            )
        );
        
        // 파일 크기 및 치수 정보 추가
        if ($attachment_meta) {
            $image_data['dimensions']['width'] = isset($attachment_meta['width']) ? $attachment_meta['width'] : 0;
            $image_data['dimensions']['height'] = isset($attachment_meta['height']) ? $attachment_meta['height'] : 0;
            $image_data['file_size'] = isset($attachment_meta['filesize']) ? $attachment_meta['filesize'] : 0;
            
            // 파일 크기가 메타데이터에 없으면 직접 계산
            if (!$image_data['file_size']) {
                $upload_dir = wp_upload_dir();
                $file_path = $upload_dir['basedir'] . '/' . $attachment_meta['file'];
                if (file_exists($file_path)) {
                    $image_data['file_size'] = filesize($file_path);
                }
            }
        }
        
        return $image_data;
    }
    
    /**
     * 게시물 메타데이터 가져오기
     */
    private function get_post_meta_data($post) {
        $meta_data = get_post_meta($post->ID);
        $filtered_meta = array();
        
        // SEO 관련 메타데이터
        $seo_keys = array(
            '_yoast_wpseo_title' => 'seo_title',
            '_yoast_wpseo_metadesc' => 'seo_description',
            '_yoast_wpseo_focuskw' => 'focus_keyword',
            '_yoast_wpseo_canonical' => 'canonical_url',
            '_aioseop_title' => 'aioseop_title',
            '_aioseop_description' => 'aioseop_description'
        );
        
        // 커스텀 필드
        $custom_keys = array(
            '_wp_page_template' => 'page_template',
            '_reading_time' => 'reading_time',
            '_difficulty_level' => 'difficulty_level',
            '_content_type' => 'content_type',
            '_featured_video' => 'featured_video',
            '_external_link' => 'external_link',
            '_post_views' => 'post_views',
            '_post_rating' => 'post_rating'
        );
        
        // 소셜 미디어 관련
        $social_keys = array(
            '_social_image' => 'social_image',
            '_twitter_title' => 'twitter_title',
            '_twitter_description' => 'twitter_description',
            '_facebook_title' => 'facebook_title',
            '_facebook_description' => 'facebook_description'
        );
        
        // 모든 키 병합
        $all_keys = array_merge($seo_keys, $custom_keys, $social_keys);
        
        foreach ($all_keys as $meta_key => $display_key) {
            if (isset($meta_data[$meta_key]) && !empty($meta_data[$meta_key][0])) {
                $filtered_meta[$display_key] = $meta_data[$meta_key][0];
            }
        }
        
        // 추가 계산된 메타데이터
        $filtered_meta['word_count'] = str_word_count(strip_tags($post->post_content));
        $filtered_meta['character_count'] = strlen(strip_tags($post->post_content));
        $filtered_meta['estimated_reading_time'] = $this->calculate_reading_time($post->post_content);
        $filtered_meta['has_shortcodes'] = $this->has_shortcodes($post->post_content);
        $filtered_meta['image_count'] = $this->count_content_images($post->post_content);
        $filtered_meta['link_count'] = $this->count_content_links($post->post_content);
        
        return $filtered_meta;
    }
    
    /**
     * 읽기 시간 계산 (분)
     */
    private function calculate_reading_time($content) {
        $word_count = str_word_count(strip_tags($content));
        $reading_speed = 200; // 분당 평균 읽기 속도 (단어)
        return max(1, ceil($word_count / $reading_speed));
    }
    
    /**
     * 숏코드 포함 여부 확인
     */
    private function has_shortcodes($content) {
        return (bool) preg_match('/\[[\w\s\-_="\'\/]*\]/', $content);
    }
    
    /**
     * 콘텐츠 내 이미지 개수 계산
     */
    private function count_content_images($content) {
        return preg_match_all('/<img[^>]+>/i', $content);
    }
    
    /**
     * 콘텐츠 내 링크 개수 계산
     */
    private function count_content_links($content) {
        return preg_match_all('/<a[^>]+href=["\'][^"\']*["\'][^>]*>/i', $content);
    }
    
    /**
     * 캐시된 결과 가져오기
     */
    private function get_cached_result($options) {
        $cache_key = $this->generate_cache_key($options);
        return get_transient($cache_key);
    }
    
    /**
     * 결과 캐싱
     */
    private function cache_result($options, $result) {
        $cache_key = $this->generate_cache_key($options);
        set_transient($cache_key, $result, $options['cache_duration']);
    }
    
    /**
     * 캐시 키 생성
     */
    private function generate_cache_key($options) {
        $key_data = array(
            'post_types' => $options['post_types'],
            'date_range' => $options['date_range'],
            'max_posts' => $options['max_posts'],
            'categories' => array_merge($options['include_categories'], $options['exclude_categories']),
            'tags' => array_merge($options['include_tags'], $options['exclude_tags']),
            'authors' => array_merge($options['include_authors'], $options['exclude_authors'])
        );
        
        return self::CACHE_PREFIX . md5(serialize($key_data));
    }
    
    /**
     * 캐시 삭제
     */
    public function clear_cache($options = null) {
        if ($options) {
            $cache_key = $this->generate_cache_key($options);
            delete_transient($cache_key);
        } else {
            // 모든 캐시 삭제
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            ));
        }
    }
    
    /**
     * 게시물 수집 통계 가져오기
     */
    public function get_collection_stats($options = array()) {
        $options = wp_parse_args($options, $this->default_options);
        
        // 캐시된 통계 확인
        $cache_key = 'ainl_stats_' . md5(serialize($options));
        $cached_stats = get_transient($cache_key);
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        // 통계 수집
        $stats = array(
            'total_posts' => 0,
            'posts_by_category' => array(),
            'posts_by_author' => array(),
            'posts_by_date' => array(),
            'average_content_length' => 0,
            'filter_stats' => array()
        );
        
        // 필터 통계 추가
        if (!empty($options['advanced_filters'])) {
            $stats['filter_stats'] = $this->advanced_filter->get_filter_statistics($options['advanced_filters']);
        }
        
        // 기본 통계 수집을 위한 쿼리
        $query_args = array(
            'post_type' => $options['post_types'],
            'post_status' => $options['post_status'],
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($query_args);
        $stats['total_posts'] = $query->found_posts;
        
        // 통계 캐싱 (5분)
        set_transient($cache_key, $stats, 300);
        
        return $stats;
    }
    
    /**
     * 고급 필터를 사용한 게시물 수집
     * 
     * @param array $advanced_filters 고급 필터 옵션
     * @param array $base_options 기본 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_with_advanced_filters($advanced_filters, $base_options = array()) {
        $options = wp_parse_args($base_options, $this->default_options);
        $options['advanced_filters'] = $advanced_filters;
        
        return $this->collect_posts($options);
    }
    
    /**
     * 필터 프리셋을 사용한 게시물 수집
     * 
     * @param string $preset_name 프리셋 이름
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_with_preset($preset_name, $additional_options = array()) {
        $options = wp_parse_args($additional_options, $this->default_options);
        $options['filter_preset'] = $preset_name;
        
        return $this->collect_posts($options);
    }
    
    /**
     * 키워드 기반 게시물 수집
     * 
     * @param array $include_keywords 포함할 키워드
     * @param array $exclude_keywords 제외할 키워드
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_by_keywords($include_keywords = array(), $exclude_keywords = array(), $additional_options = array()) {
        $advanced_filters = array();
        
        if (!empty($include_keywords)) {
            $advanced_filters['include_keywords'] = $include_keywords;
        }
        
        if (!empty($exclude_keywords)) {
            $advanced_filters['exclude_keywords'] = $exclude_keywords;
        }
        
        return $this->collect_posts_with_advanced_filters($advanced_filters, $additional_options);
    }
    
    /**
     * 날짜 범위별 게시물 수집
     * 
     * @param string $start_date 시작 날짜 (Y-m-d 형식)
     * @param string $end_date 종료 날짜 (Y-m-d 형식)
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_by_date_range($start_date, $end_date, $additional_options = array()) {
        $advanced_filters = array(
            'date_range_custom' => array(
                'after' => $start_date,
                'before' => $end_date
            )
        );
        
        return $this->collect_posts_with_advanced_filters($advanced_filters, $additional_options);
    }
    
    /**
     * 커스텀 필드 기반 게시물 수집
     * 
     * @param array $custom_field_filters 커스텀 필드 필터
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_by_custom_fields($custom_field_filters, $additional_options = array()) {
        $advanced_filters = array(
            'custom_fields' => $custom_field_filters
        );
        
        return $this->collect_posts_with_advanced_filters($advanced_filters, $additional_options);
    }
    
    /**
     * 인기 게시물 수집 (댓글 수 기준)
     * 
     * @param int $min_comments 최소 댓글 수
     * @param int $days 날짜 범위 (일)
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_popular_posts($min_comments = 1, $days = 7, $additional_options = array()) {
        $advanced_filters = array(
            'min_comments' => $min_comments
        );
        
        $options = wp_parse_args($additional_options, array(
            'date_range' => $days,
            'order_by' => 'comment_count',
            'order' => 'DESC'
        ));
        
        return $this->collect_posts_with_advanced_filters($advanced_filters, $options);
    }
    
    /**
     * 대표 이미지가 있는 게시물만 수집
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_with_featured_images($additional_options = array()) {
        $advanced_filters = array(
            'has_featured_image' => true
        );
        
        return $this->collect_posts_with_advanced_filters($advanced_filters, $additional_options);
    }
    
    /**
     * 완전한 미디어 정보와 함께 게시물 수집
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_with_full_media($additional_options = array()) {
        $options = wp_parse_args($additional_options, $this->default_options);
        $options['include_all_media'] = true;
        $options['include_attachments'] = true;
        $options['include_featured_image'] = true;
        $options['include_meta_data'] = true;
        
        return $this->collect_posts($options);
    }
    
    /**
     * SEO 메타데이터와 함께 게시물 수집
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_with_seo_data($additional_options = array()) {
        $options = wp_parse_args($additional_options, $this->default_options);
        $options['include_meta_data'] = true;
        
        return $this->collect_posts($options);
    }
    
    /**
     * 특정 메타 키 값으로 게시물 필터링
     * 
     * @param string $meta_key 메타 키
     * @param mixed $meta_value 메타 값
     * @param string $compare 비교 연산자 (=, !=, >, <, LIKE 등)
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_by_meta($meta_key, $meta_value, $compare = '=', $additional_options = array()) {
        $options = wp_parse_args($additional_options, $this->default_options);
        
        // 기본 쿼리에 메타 쿼리 추가
        $options['meta_query'] = array(
            array(
                'key' => sanitize_key($meta_key),
                'value' => $meta_value,
                'compare' => $compare
            )
        );
        
        return $this->collect_posts($options);
    }
    
    /**
     * 읽기 시간 범위로 게시물 필터링
     * 
     * @param int $min_minutes 최소 읽기 시간 (분)
     * @param int $max_minutes 최대 읽기 시간 (분)
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_posts_by_reading_time($min_minutes = 1, $max_minutes = 10, $additional_options = array()) {
        $options = wp_parse_args($additional_options, $this->default_options);
        
        // 읽기 시간을 기준으로 단어 수 계산 (분당 200단어 기준)
        $min_words = $min_minutes * 200;
        $max_words = $max_minutes * 200;
        
        $options['min_content_length'] = $min_words;
        $options['max_content_length'] = $max_words;
        
        return $this->collect_posts($options);
    }
    
    /**
     * 이미지가 많은 게시물 수집 (갤러리, 포토 포스트 등)
     * 
     * @param int $min_images 최소 이미지 개수
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_image_rich_posts($min_images = 3, $additional_options = array()) {
        $posts = $this->collect_posts_with_full_media($additional_options);
        $filtered_posts = array();
        
        foreach ($posts as $post) {
            $image_count = 0;
            
            // 대표 이미지
            if (!empty($post['featured_image'])) {
                $image_count++;
            }
            
            // 첨부 이미지
            if (!empty($post['attachments'])) {
                $image_count += count($post['attachments']);
            }
            
            // 갤러리 이미지
            if (!empty($post['media']['gallery_images'])) {
                $image_count += count($post['media']['gallery_images']);
            }
            
            // 콘텐츠 내 이미지
            if (!empty($post['meta_data']['image_count'])) {
                $image_count += $post['meta_data']['image_count'];
            }
            
            if ($image_count >= $min_images) {
                $filtered_posts[] = $post;
            }
        }
        
        return $filtered_posts;
    }
    
    /**
     * 비디오 콘텐츠가 포함된 게시물 수집
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물
     */
    public function collect_video_posts($additional_options = array()) {
        $posts = $this->collect_posts_with_full_media($additional_options);
        $video_posts = array();
        
        foreach ($posts as $post) {
            $has_video = false;
            
            // 임베디드 비디오 확인
            if (!empty($post['media']['embedded_media'])) {
                foreach ($post['media']['embedded_media'] as $media) {
                    if (in_array($media['type'], array('youtube', 'vimeo'))) {
                        $has_video = true;
                        break;
                    }
                }
            }
            
            // 비디오 메타 필드 확인
            if (!empty($post['meta_data']['featured_video'])) {
                $has_video = true;
            }
            
            if ($has_video) {
                $video_posts[] = $post;
            }
        }
        
        return $video_posts;
    }
    
    /**
     * 게시물의 메타데이터 통계 가져오기
     * 
     * @param array $post_ids 게시물 ID 배열 (비어있으면 모든 게시물)
     * @return array 메타데이터 통계
     */
    public function get_metadata_statistics($post_ids = array()) {
        $stats = array(
            'total_posts' => 0,
            'posts_with_featured_images' => 0,
            'posts_with_galleries' => 0,
            'posts_with_videos' => 0,
            'average_reading_time' => 0,
            'average_word_count' => 0,
            'seo_optimized_posts' => 0,
            'meta_field_usage' => array()
        );
        
        $query_args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        if (!empty($post_ids)) {
            $query_args['post__in'] = $post_ids;
        }
        
        $posts = get_posts($query_args);
        $stats['total_posts'] = count($posts);
        
        $total_reading_time = 0;
        $total_word_count = 0;
        
        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            
            // 대표 이미지 확인
            if (has_post_thumbnail($post_id)) {
                $stats['posts_with_featured_images']++;
            }
            
            // 갤러리 확인
            if (strpos($post->post_content, '[gallery') !== false) {
                $stats['posts_with_galleries']++;
            }
            
            // 비디오 확인
            if (preg_match('/(youtube|vimeo)/', $post->post_content)) {
                $stats['posts_with_videos']++;
            }
            
            // 읽기 시간 및 단어 수
            $word_count = str_word_count(strip_tags($post->post_content));
            $reading_time = $this->calculate_reading_time($post->post_content);
            
            $total_word_count += $word_count;
            $total_reading_time += $reading_time;
            
            // SEO 최적화 확인
            $seo_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
            $seo_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            
            if (!empty($seo_title) || !empty($seo_desc)) {
                $stats['seo_optimized_posts']++;
            }
        }
        
        // 평균 계산
        if ($stats['total_posts'] > 0) {
            $stats['average_reading_time'] = round($total_reading_time / $stats['total_posts'], 1);
            $stats['average_word_count'] = round($total_word_count / $stats['total_posts']);
        }
        
        return $stats;
    }
    
    /**
     * 뉴스레터용 콘텐츠 수집 (정리된 형태)
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물 (정리된 콘텐츠)
     */
    public function collect_posts_for_newsletter($additional_options = array()) {
        $default_newsletter_options = array(
            'max_posts' => 10,
            'include_featured_image' => true,
            'include_meta_data' => true,
            'content_format' => array(
                'remove_shortcodes' => true,
                'strip_html_tags' => false,
                'remove_images' => false,
                'convert_links' => true,
                'convert_headings' => true,
                'clean_whitespace' => true,
                'max_length' => 500
            ),
            'excerpt_format' => array(
                'length' => 30,
                'use_smart_generation' => true,
                'preserve_sentences' => true
            )
        );
        
        $options = wp_parse_args($additional_options, $default_newsletter_options);
        
        return $this->collect_posts($options);
    }
    
    /**
     * 플레인 텍스트용 콘텐츠 수집
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물 (플레인 텍스트)
     */
    public function collect_posts_as_plain_text($additional_options = array()) {
        $plain_text_options = array(
            'content_format' => array(
                'remove_shortcodes' => true,
                'strip_html_tags' => true,
                'remove_images' => true,
                'convert_links' => false,
                'convert_headings' => false,
                'clean_whitespace' => true
            ),
            'excerpt_format' => array(
                'length' => 25,
                'use_smart_generation' => true,
                'preserve_sentences' => true
            )
        );
        
        $options = wp_parse_args($additional_options, $plain_text_options);
        
        return $this->collect_posts($options);
    }
    
    /**
     * 마크다운 형식으로 콘텐츠 수집
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물 (마크다운 형식)
     */
    public function collect_posts_as_markdown($additional_options = array()) {
        $markdown_options = array(
            'content_format' => array(
                'remove_shortcodes' => true,
                'strip_html_tags' => false,
                'remove_images' => false,
                'convert_links' => true,
                'convert_headings' => true,
                'clean_whitespace' => true
            ),
            'excerpt_format' => array(
                'length' => 30,
                'use_smart_generation' => true,
                'preserve_sentences' => true
            )
        );
        
        $options = wp_parse_args($additional_options, $markdown_options);
        
        return $this->collect_posts($options);
    }
    
    /**
     * 요약만 포함한 콘텐츠 수집
     * 
     * @param array $additional_options 추가 옵션
     * @return array 수집된 게시물 (요약만)
     */
    public function collect_posts_summary_only($additional_options = array()) {
        $summary_options = array(
            'content_format' => array(
                'max_length' => 0 // 원본 콘텐츠 제외
            ),
            'excerpt_format' => array(
                'length' => 50,
                'use_smart_generation' => true,
                'preserve_sentences' => true
            ),
            'include_summary_only' => true
        );
        
        $options = wp_parse_args($additional_options, $summary_options);
        
        return $this->collect_posts($options);
    }
    
    /**
     * 콘텐츠 품질 분석
     * 
     * @param string $content 분석할 콘텐츠
     * @return array 품질 분석 결과
     */
    public function analyze_content_quality($content) {
        $analysis = array(
            'word_count' => 0,
            'character_count' => 0,
            'paragraph_count' => 0,
            'sentence_count' => 0,
            'reading_time' => 0,
            'readability_score' => 0,
            'has_images' => false,
            'has_links' => false,
            'has_headings' => false,
            'quality_score' => 0
        );
        
        $clean_content = strip_tags($content);
        
        // 기본 통계
        $analysis['word_count'] = str_word_count($clean_content);
        $analysis['character_count'] = strlen($clean_content);
        $analysis['paragraph_count'] = substr_count($content, '<p>') + substr_count($content, "\n\n");
        $analysis['sentence_count'] = preg_match_all('/[.!?]+/', $clean_content);
        $analysis['reading_time'] = $this->calculate_reading_time($content);
        
        // 콘텐츠 요소 확인
        $analysis['has_images'] = (bool) preg_match('/<img[^>]*>/i', $content);
        $analysis['has_links'] = (bool) preg_match('/<a[^>]*href/i', $content);
        $analysis['has_headings'] = (bool) preg_match('/<h[1-6][^>]*>/i', $content);
        
        // 가독성 점수 계산 (간단한 Flesch Reading Ease 근사치)
        if ($analysis['sentence_count'] > 0 && $analysis['word_count'] > 0) {
            $avg_sentence_length = $analysis['word_count'] / $analysis['sentence_count'];
            $analysis['readability_score'] = max(0, min(100, 206.835 - (1.015 * $avg_sentence_length)));
        }
        
        // 품질 점수 계산 (0-100)
        $quality_factors = array();
        
        // 적절한 길이 (200-2000 단어)
        if ($analysis['word_count'] >= 200 && $analysis['word_count'] <= 2000) {
            $quality_factors[] = 25;
        } elseif ($analysis['word_count'] >= 100) {
            $quality_factors[] = 15;
        }
        
        // 가독성
        if ($analysis['readability_score'] >= 60) {
            $quality_factors[] = 25;
        } elseif ($analysis['readability_score'] >= 30) {
            $quality_factors[] = 15;
        }
        
        // 구조적 요소
        if ($analysis['has_headings']) $quality_factors[] = 15;
        if ($analysis['has_images']) $quality_factors[] = 15;
        if ($analysis['has_links']) $quality_factors[] = 10;
        if ($analysis['paragraph_count'] >= 3) $quality_factors[] = 10;
        
        $analysis['quality_score'] = array_sum($quality_factors);
        
        return $analysis;
    }
    
    /**
     * 콘텐츠 정리 옵션 프리셋 가져오기
     * 
     * @param string $preset_name 프리셋 이름
     * @return array 포맷팅 옵션
     */
    public function get_content_format_preset($preset_name) {
        $presets = array(
            'newsletter' => array(
                'remove_shortcodes' => true,
                'strip_html_tags' => false,
                'preserve_paragraphs' => true,
                'remove_empty_paragraphs' => true,
                'convert_links' => true,
                'max_length' => 500,
                'remove_images' => false,
                'convert_headings' => true,
                'clean_whitespace' => true
            ),
            'plain_text' => array(
                'remove_shortcodes' => true,
                'strip_html_tags' => true,
                'preserve_paragraphs' => false,
                'remove_empty_paragraphs' => true,
                'convert_links' => false,
                'max_length' => 0,
                'remove_images' => true,
                'convert_headings' => false,
                'clean_whitespace' => true
            ),
            'markdown' => array(
                'remove_shortcodes' => true,
                'strip_html_tags' => false,
                'preserve_paragraphs' => true,
                'remove_empty_paragraphs' => true,
                'convert_links' => true,
                'max_length' => 0,
                'remove_images' => false,
                'convert_headings' => true,
                'clean_whitespace' => true
            ),
            'summary' => array(
                'remove_shortcodes' => true,
                'strip_html_tags' => true,
                'preserve_paragraphs' => false,
                'remove_empty_paragraphs' => true,
                'convert_links' => false,
                'max_length' => 200,
                'remove_images' => true,
                'convert_headings' => false,
                'clean_whitespace' => true
            )
        );
        
        return isset($presets[$preset_name]) ? $presets[$preset_name] : $presets['newsletter'];
    }
    
    /**
     * 배치 처리 메서드
     */
    private function process_posts_batch($posts, $options) {
        // 구현 코드 필요
        return array();
    }
    
    /**
     * 메모리 관리 메서드
     */
    private function start_memory_tracking() {
        // 구현 코드 필요
    }
    
    private function cleanup_memory() {
        // 구현 코드 필요
    }
    
    private function cleanup_batch_memory() {
        // 구현 코드 필요
    }
    
    /**
     * 성능 모니터링 메서드
     */
    private function start_performance_monitoring($method_name) {
        // 구현 코드 필요
    }
    
    private function end_performance_monitoring($method_name) {
        // 구현 코드 필요
    }
    
    private function record_query_performance($query_args, $query_time, $found_posts) {
        // 구현 코드 필요
    }
    
    /**
     * 고급 캐시 결과 가져오기
     * 
     * @param array $options 캐시 키 생성용 옵션
     * @return mixed 캐시된 결과 또는 false
     */
    private function get_advanced_cached_result($options) {
        $cache_key = $this->generate_advanced_cache_key($options);
        
        // WordPress transient 캐시 확인
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        
        // 객체 캐시 확인
        $cached = wp_cache_get($cache_key, 'ainl_posts_advanced');
        if ($cached !== false) {
            return $cached;
        }
        
        return false;
    }
    
    /**
     * 고급 캐시 결과 저장
     * 
     * @param array $options 캐시 키 생성용 옵션
     * @param array $result 저장할 결과
     */
    private function cache_advanced_result($options, $result) {
        $cache_key = $this->generate_advanced_cache_key($options);
        $cache_duration = $this->get_cache_duration($options);
        
        // 캐시 크기 관리
        $this->manage_cache_size();
        
        // WordPress transient 캐시 저장
        set_transient($cache_key, $result, $cache_duration);
        
        // 객체 캐시 저장 (더 빠른 접근)
        wp_cache_set($cache_key, $result, 'ainl_posts_advanced', $cache_duration);
        
        // 캐시 메타데이터 저장
        $this->store_cache_metadata($cache_key, $options, $result);
    }
    
    /**
     * 고급 캐시 키 생성
     * 
     * @param array $options 옵션 배열
     * @return string 캐시 키
     */
    private function generate_advanced_cache_key($options) {
        // 캐시에 영향을 주는 주요 옵션만 선별
        $cache_relevant_options = array(
            'post_types' => $options['post_types'],
            'post_status' => $options['post_status'],
            'max_posts' => $options['max_posts'],
            'date_range' => $options['date_range'],
            'include_categories' => $options['include_categories'],
            'exclude_categories' => $options['exclude_categories'],
            'include_tags' => $options['include_tags'],
            'exclude_tags' => $options['exclude_tags'],
            'include_authors' => $options['include_authors'],
            'exclude_authors' => $options['exclude_authors'],
            'order_by' => $options['order_by'],
            'order' => $options['order'],
            'content_format' => isset($options['content_format']) ? $options['content_format'] : array(),
            'include_meta_data' => $options['include_meta_data'],
            'include_featured_image' => $options['include_featured_image']
        );
        
        // 고급 필터가 있다면 포함
        if (!empty($options['advanced_filters'])) {
            $cache_relevant_options['advanced_filters'] = $options['advanced_filters'];
        }
        
        // 옵션을 정렬하여 일관된 키 생성
        ksort($cache_relevant_options);
        
        // 해시 생성
        $options_hash = md5(serialize($cache_relevant_options));
        
        return self::CACHE_PREFIX . 'advanced_' . self::CACHE_VERSION . '_' . $options_hash;
    }
    
    /**
     * 캐시 지속 시간 결정
     * 
     * @param array $options 옵션 배열
     * @return int 캐시 지속 시간 (초)
     */
    private function get_cache_duration($options) {
        // 기본 캐시 시간: 15분
        $default_duration = 15 * MINUTE_IN_SECONDS;
        
        // 날짜 범위에 따른 동적 캐시 시간
        if (isset($options['date_range'])) {
            if ($options['date_range'] <= 1) {
                // 최근 1일: 5분 캐시
                return 5 * MINUTE_IN_SECONDS;
            } elseif ($options['date_range'] <= 7) {
                // 최근 1주일: 15분 캐시
                return 15 * MINUTE_IN_SECONDS;
            } elseif ($options['date_range'] <= 30) {
                // 최근 1개월: 1시간 캐시
                return HOUR_IN_SECONDS;
            } else {
                // 그 이상: 6시간 캐시
                return 6 * HOUR_IN_SECONDS;
            }
        }
        
        return $default_duration;
    }
    
    /**
     * 캐시 크기 관리
     */
    private function manage_cache_size() {
        $cache_list_key = self::CACHE_PREFIX . 'cache_list';
        $cache_list = get_option($cache_list_key, array());
        
        // 최대 캐시 수 초과 시 오래된 캐시 삭제
        if (count($cache_list) >= self::MAX_CACHE_SIZE) {
            // 가장 오래된 캐시 삭제
            $oldest_cache = array_shift($cache_list);
            if ($oldest_cache) {
                delete_transient($oldest_cache['key']);
                wp_cache_delete($oldest_cache['key'], 'ainl_posts_advanced');
            }
        }
        
        update_option($cache_list_key, $cache_list);
    }
    
    /**
     * 캐시 메타데이터 저장
     * 
     * @param string $cache_key 캐시 키
     * @param array $options 옵션
     * @param array $result 결과
     */
    private function store_cache_metadata($cache_key, $options, $result) {
        $cache_list_key = self::CACHE_PREFIX . 'cache_list';
        $cache_list = get_option($cache_list_key, array());
        
        $metadata = array(
            'key' => $cache_key,
            'created' => current_time('timestamp'),
            'post_count' => count($result),
            'options_hash' => md5(serialize($options))
        );
        
        $cache_list[] = $metadata;
        update_option($cache_list_key, $cache_list);
    }
    
    /**
     * 성능 모니터링 시작
     * 
     * @param string $method_name 메서드 이름
     */
    private function start_performance_monitoring($method_name) {
        $this->performance_data[$method_name] = array(
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );
    }
    
    /**
     * 성능 모니터링 종료
     * 
     * @param string $method_name 메서드 이름
     */
    private function end_performance_monitoring($method_name) {
        if (!isset($this->performance_data[$method_name])) {
            return;
        }
        
        $start_data = $this->performance_data[$method_name];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        $this->performance_data[$method_name] = array_merge($start_data, array(
            'end_time' => $end_time,
            'execution_time' => $end_time - $start_data['start_time'],
            'end_memory' => $end_memory,
            'memory_used' => $end_memory - $start_data['start_memory'],
            'peak_memory' => $peak_memory,
            'memory_peak_diff' => $peak_memory - $start_data['peak_memory']
        ));
    }
    
    /**
     * 쿼리 성능 기록
     * 
     * @param array $query_args 쿼리 인수
     * @param float $query_time 쿼리 실행 시간
     * @param int $found_posts 찾은 게시물 수
     */
    private function record_query_performance($query_args, $query_time, $found_posts) {
        $this->query_timer[] = array(
            'query_args' => $query_args,
            'execution_time' => $query_time,
            'found_posts' => $found_posts,
            'timestamp' => current_time('timestamp'),
            'memory_usage' => memory_get_usage(true)
        );
        
        // 성능 로그가 너무 많이 쌓이지 않도록 제한
        if (count($this->query_timer) > 10) {
            array_shift($this->query_timer);
        }
    }
    
    /**
     * 메모리 추적 시작
     */
    private function start_memory_tracking() {
        $this->memory_tracker['start'] = array(
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        );
    }
    
    /**
     * 배치 메모리 정리
     */
    private function cleanup_batch_memory() {
        // WordPress 객체 캐시 일부 정리
        wp_cache_flush_group('posts');
        wp_cache_flush_group('post_meta');
        
        // PHP 가비지 컬렉션 강제 실행
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // 메모리 사용량 기록
        $this->memory_tracker['batch_cleanup'][] = array(
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        );
    }
    
    /**
     * 전체 메모리 정리
     */
    private function cleanup_memory() {
        // 임시 데이터 정리
        unset($this->temp_data);
        
        // 캐시 정리
        wp_cache_flush_group('ainl_posts');
        
        // 메모리 사용량 최종 기록
        $this->memory_tracker['end'] = array(
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => microtime(true)
        );
        
        // PHP 가비지 컬렉션
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * 성능 보고서 생성
     * 
     * @return array 성능 데이터 배열
     */
    public function get_performance_report() {
        return array(
            'performance_data' => $this->performance_data,
            'memory_tracker' => $this->memory_tracker,
            'query_timer' => $this->query_timer,
            'cache_stats' => $this->get_cache_statistics()
        );
    }
    
    /**
     * 캐시 통계 가져오기
     * 
     * @return array 캐시 통계
     */
    private function get_cache_statistics() {
        $cache_list_key = self::CACHE_PREFIX . 'cache_list';
        $cache_list = get_option($cache_list_key, array());
        
        $stats = array(
            'total_cache_entries' => count($cache_list),
            'cache_hit_rate' => 0,
            'average_cache_age' => 0,
            'cache_size_mb' => 0
        );
        
        if (!empty($cache_list)) {
            $current_time = current_time('timestamp');
            $total_age = 0;
            
            foreach ($cache_list as $cache_entry) {
                $age = $current_time - $cache_entry['created'];
                $total_age += $age;
            }
            
            $stats['average_cache_age'] = $total_age / count($cache_list);
        }
        
        return $stats;
    }
    
    /**
     * 캐시 정리 (수동)
     * 
     * @param bool $force_all 모든 캐시 강제 삭제
     */
    public function clear_cache($force_all = false) {
        $cache_list_key = self::CACHE_PREFIX . 'cache_list';
        $cache_list = get_option($cache_list_key, array());
        
        foreach ($cache_list as $cache_entry) {
            delete_transient($cache_entry['key']);
            wp_cache_delete($cache_entry['key'], 'ainl_posts_advanced');
        }
        
        // 캐시 목록 초기화
        delete_option($cache_list_key);
        
        // 객체 캐시 그룹 정리
        wp_cache_flush_group('ainl_posts');
        wp_cache_flush_group('ainl_posts_advanced');
        
        if ($force_all) {
            // 모든 관련 캐시 정리
            wp_cache_flush_group('posts');
            wp_cache_flush_group('post_meta');
            wp_cache_flush_group('terms');
        }
    }
    
    /**
     * 성능 최적화된 대량 게시물 수집
     * 
     * @param array $options 수집 옵션
     * @return array 수집된 게시물 배열
     */
    public function collect_posts_bulk($options = array()) {
        // 대량 처리를 위한 특별한 옵션 설정
        $bulk_options = array_merge($options, array(
            'enable_query_optimization' => true,
            'enable_memory_management' => true,
            'batch_size' => 200, // 더 큰 배치 크기
            'use_object_cache' => true,
            'preload_meta' => false, // 메타데이터 지연 로딩
            'lazy_load_images' => true,
            'cache_results' => true
        ));
        
        return $this->collect_posts($bulk_options);
    }
} 
<?php
/**
 * 데이터베이스 관리 클래스
 * 플러그인의 모든 데이터베이스 테이블 생성 및 관리를 담당합니다.
 */

// 직접 접근 방지
if (!defined('ABSPATH')) {
    exit;
}

class AINL_Database {
    
    /**
     * 데이터베이스 버전
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * 모든 데이터베이스 테이블 생성
     * WordPress dbDelta 함수를 사용하여 안전하게 테이블을 생성합니다.
     */
    public static function create_tables() {
        global $wpdb;
        
        // WordPress dbDelta 함수 로드
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // 각 테이블 생성
        self::create_subscribers_table();
        self::create_categories_table();
        self::create_subscriber_categories_table();
        self::create_templates_table();
        self::create_campaigns_table();
        self::create_statistics_table();
        self::create_email_queue_table();
        self::create_email_logs_table();
        
        // 추가 통계 테이블 생성
        if (class_exists('AINL_Statistics')) {
            AINL_Statistics::create_additional_tables();
        }
        
        // 데이터베이스 버전 저장
        update_option('ainl_db_version', self::DB_VERSION);
        
        // 초기 데이터 삽입
        self::insert_initial_data();
    }
    
    /**
     * 구독자 테이블 생성
     * 이메일 주소, 이름, 상태, 구독 정보 등을 저장합니다.
     */
    private static function create_subscribers_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_subscribers';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT '',
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            status enum('active', 'inactive', 'blocked', 'pending') DEFAULT 'pending',
            source varchar(50) DEFAULT 'manual',
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            double_optin_confirmed tinyint(1) DEFAULT 0,
            confirmation_token varchar(64) DEFAULT '',
            unsubscribe_token varchar(64) DEFAULT '',
            metadata longtext DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            confirmed_at datetime DEFAULT NULL,
            last_activity datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY created_at (created_at),
            KEY source (source),
            KEY confirmation_token (confirmation_token),
            KEY unsubscribe_token (unsubscribe_token)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 카테고리 테이블 생성
     * 구독자를 분류하기 위한 카테고리 정보를 저장합니다.
     */
    private static function create_categories_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_categories';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT '',
            parent_id bigint(20) unsigned DEFAULT 0,
            color varchar(7) DEFAULT '#007cba',
            sort_order int(11) DEFAULT 0,
            is_default tinyint(1) DEFAULT 0,
            subscriber_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY parent_id (parent_id),
            KEY sort_order (sort_order),
            KEY is_default (is_default)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 구독자-카테고리 관계 테이블 생성
     * 구독자와 카테고리 간의 다대다 관계를 저장합니다.
     */
    private static function create_subscriber_categories_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_subscriber_categories';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY subscriber_category (subscriber_id, category_id),
            KEY subscriber_id (subscriber_id),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 템플릿 테이블 생성
     * 이메일 템플릿 정보와 AI 학습 데이터를 저장합니다.
     */
    private static function create_templates_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_templates';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text DEFAULT '',
            content longtext NOT NULL,
            ai_training_data longtext DEFAULT '',
            settings longtext DEFAULT '',
            template_type enum('newsletter', 'welcome', 'confirmation', 'unsubscribe') DEFAULT 'newsletter',
            is_default tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY template_type (template_type),
            KEY is_default (is_default),
            KEY is_active (is_active),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 캠페인 테이블 생성
     * 뉴스레터 캠페인 정보와 발송 결과를 저장합니다.
     */
    private static function create_campaigns_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_campaigns';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            template_id bigint(20) unsigned DEFAULT 0,
            status enum('draft', 'scheduled', 'sending', 'sent', 'paused', 'cancelled') DEFAULT 'draft',
            campaign_type enum('newsletter', 'broadcast', 'autoresponder') DEFAULT 'newsletter',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            total_recipients int(11) DEFAULT 0,
            total_sent int(11) DEFAULT 0,
            total_delivered int(11) DEFAULT 0,
            total_bounced int(11) DEFAULT 0,
            total_opens int(11) DEFAULT 0,
            total_clicks int(11) DEFAULT 0,
            total_unsubscribes int(11) DEFAULT 0,
            unique_opens int(11) DEFAULT 0,
            unique_clicks int(11) DEFAULT 0,
            ai_generation_data longtext DEFAULT '',
            settings longtext DEFAULT '',
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY campaign_type (campaign_type),
            KEY scheduled_at (scheduled_at),
            KEY sent_at (sent_at),
            KEY template_id (template_id),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 통계 테이블 생성
     * 개별 구독자의 이메일 활동을 추적합니다.
     */
    private static function create_statistics_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_statistics';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            subscriber_id bigint(20) unsigned NOT NULL,
            action enum('sent', 'delivered', 'opened', 'clicked', 'bounced', 'unsubscribed', 'complained') NOT NULL,
            action_data longtext DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY action (action),
            KEY timestamp (timestamp),
            KEY campaign_subscriber (campaign_id, subscriber_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 이메일 큐 테이블 생성
     * 이메일 발송을 위한 큐 정보를 저장합니다.
     */
    private static function create_email_queue_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_email_queue';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            to_email varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            message longtext NOT NULL,
            headers longtext DEFAULT '',
            attachments longtext DEFAULT '',
            priority enum('high', 'normal', 'low') DEFAULT 'normal',
            status enum('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_attempt_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            error_message text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY to_email (to_email),
            KEY status (status),
            KEY priority (priority),
            KEY scheduled_at (scheduled_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 이메일 로그 테이블 생성
     * 이메일 발송 로그를 저장합니다.
     */
    private static function create_email_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ainl_email_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            queue_id bigint(20) unsigned DEFAULT 0,
            to_email varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            status enum('success', 'failed', 'bounced', 'opened', 'clicked') NOT NULL,
            attempts int(11) DEFAULT 1,
            error_message text DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY queue_id (queue_id),
            KEY to_email (to_email),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 초기 데이터 삽입
     * 기본 카테고리와 템플릿을 생성합니다.
     */
    private static function insert_initial_data() {
        global $wpdb;
        
        // 기본 카테고리 생성
        $categories_table = $wpdb->prefix . 'ainl_categories';
        
        $default_categories = array(
            array(
                'name' => '일반 구독자',
                'slug' => 'general',
                'description' => '기본 뉴스레터 구독자',
                'is_default' => 1,
                'color' => '#007cba'
            ),
            array(
                'name' => 'VIP 구독자',
                'slug' => 'vip',
                'description' => '프리미엄 콘텐츠 구독자',
                'is_default' => 0,
                'color' => '#d63638'
            )
        );
        
        foreach ($default_categories as $category) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $categories_table WHERE slug = %s",
                $category['slug']
            ));
            
            if (!$existing) {
                $wpdb->insert($categories_table, $category);
            }
        }
        
        // 기본 템플릿 생성
        self::create_default_templates();
    }
    
    /**
     * 기본 템플릿 생성
     * 기본 이메일 템플릿들을 데이터베이스에 삽입합니다.
     */
    private static function create_default_templates() {
        global $wpdb;
        
        $templates_table = $wpdb->prefix . 'ainl_templates';
        
        $default_templates = array(
            array(
                'name' => '기본 뉴스레터 템플릿',
                'slug' => 'basic-newsletter',
                'description' => '심플하고 깔끔한 기본 뉴스레터 템플릿',
                'content' => self::get_basic_newsletter_template(),
                'template_type' => 'newsletter',
                'is_default' => 1,
                'is_active' => 1
            ),
            array(
                'name' => '환영 이메일 템플릿',
                'slug' => 'welcome-email',
                'description' => '새 구독자를 위한 환영 이메일',
                'content' => self::get_welcome_email_template(),
                'template_type' => 'welcome',
                'is_default' => 1,
                'is_active' => 1
            ),
            array(
                'name' => '구독 확인 템플릿',
                'slug' => 'confirmation-email',
                'description' => '이메일 구독 확인을 위한 템플릿',
                'content' => self::get_confirmation_email_template(),
                'template_type' => 'confirmation',
                'is_default' => 1,
                'is_active' => 1
            )
        );
        
        foreach ($default_templates as $template) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $templates_table WHERE slug = %s",
                $template['slug']
            ));
            
            if (!$existing) {
                $wpdb->insert($templates_table, $template);
            }
        }
    }
    
    /**
     * 기본 뉴스레터 템플릿 HTML
     */
    private static function get_basic_newsletter_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{newsletter_title}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007cba; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .post-item { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #ddd; }
        .post-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .post-excerpt { margin-bottom: 15px; }
        .read-more { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{site_name}} 뉴스레터</h1>
            <p>{{newsletter_date}}</p>
        </div>
        <div class="content">
            <h2>{{newsletter_title}}</h2>
            <p>{{newsletter_intro}}</p>
            
            {{#posts}}
            <div class="post-item">
                <div class="post-title">{{post_title}}</div>
                <div class="post-excerpt">{{post_excerpt}}</div>
                <a href="{{post_url}}" class="read-more">더 읽기</a>
            </div>
            {{/posts}}
        </div>
        <div class="footer">
            <p>{{site_name}} | {{unsubscribe_link}}</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * 환영 이메일 템플릿 HTML
     */
    private static function get_welcome_email_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>환영합니다!</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #007cba;">{{site_name}}에 오신 것을 환영합니다!</h1>
        <p>안녕하세요 {{subscriber_name}}님,</p>
        <p>{{site_name}} 뉴스레터 구독을 신청해 주셔서 감사합니다.</p>
        <p>앞으로 유용한 콘텐츠와 최신 소식을 정기적으로 보내드리겠습니다.</p>
        <p>감사합니다.</p>
        <hr>
        <p style="font-size: 12px; color: #666;">
            구독을 취소하시려면 <a href="{{unsubscribe_link}}">여기</a>를 클릭하세요.
        </p>
    </div>
</body>
</html>';
    }
    
    /**
     * 구독 확인 이메일 템플릿 HTML
     */
    private static function get_confirmation_email_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>구독 확인</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #007cba;">구독 확인이 필요합니다</h1>
        <p>안녕하세요,</p>
        <p>{{site_name}} 뉴스레터 구독을 신청해 주셔서 감사합니다.</p>
        <p>구독을 완료하려면 아래 버튼을 클릭해 주세요:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{confirmation_link}}" style="background: #007cba; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">구독 확인하기</a>
        </p>
        <p>만약 위 버튼이 작동하지 않으면, 아래 링크를 복사하여 브라우저에 붙여넣기 하세요:</p>
        <p style="word-break: break-all; background: #f5f5f5; padding: 10px;">{{confirmation_link}}</p>
        <p>감사합니다.</p>
    </div>
</body>
</html>';
    }
    
    /**
     * 데이터베이스 테이블 삭제
     * 플러그인 삭제 시 사용됩니다.
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ainl_email_logs',
            $wpdb->prefix . 'ainl_email_queue',
            $wpdb->prefix . 'ainl_statistics',
            $wpdb->prefix . 'ainl_subscriber_categories',
            $wpdb->prefix . 'ainl_campaigns',
            $wpdb->prefix . 'ainl_templates',
            $wpdb->prefix . 'ainl_categories',
            $wpdb->prefix . 'ainl_subscribers'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // 데이터베이스 버전 옵션 삭제
        delete_option('ainl_db_version');
    }
    
    /**
     * 데이터베이스 업그레이드 체크
     * 플러그인 업데이트 시 데이터베이스 스키마 변경사항을 적용합니다.
     */
    public static function maybe_upgrade() {
        $current_version = get_option('ainl_db_version', '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }
} 
<?php
/**
 * AI Newsletter Generator Pro - 긴급 수정 파일
 * 현재 플러그인 오류를 즉시 해결하기 위한 임시 수정사항
 * 
 * 사용법:
 * 1. 이 파일을 WordPress 루트 디렉토리에 업로드
 * 2. 브라우저에서 yoursite.com/emergency-fix.php 접속
 * 3. 플러그인 비활성화 실행
 */

// WordPress 환경 로드
require_once('wp-config.php');
require_once('wp-load.php');

// 관리자 권한 체크
if (!current_user_can('manage_options')) {
    die('권한이 없습니다. 관리자로 로그인해주세요.');
}

echo "<h1>AI Newsletter Generator Pro - 긴급 수정</h1>";

// 1. 플러그인 강제 비활성화
$plugin_file = 'ai-newsletter-generator-pro/ai-newsletter-generator-pro.php';
$active_plugins = get_option('active_plugins');

if (in_array($plugin_file, $active_plugins)) {
    $active_plugins = array_diff($active_plugins, array($plugin_file));
    update_option('active_plugins', $active_plugins);
    echo "<p style='color: green;'>✅ 플러그인이 강제 비활성화되었습니다.</p>";
} else {
    echo "<p style='color: orange;'>⚠️ 플러그인이 이미 비활성화 상태입니다.</p>";
}

// 2. 플러그인 활성화 플래그 제거
delete_option('ainl_plugin_activated');
echo "<p style='color: green;'>✅ 플러그인 활성화 플래그를 제거했습니다.</p>";

// 3. 오류 발생 가능성이 있는 옵션들 정리
$cleanup_options = array(
    'ainl_plugin_activated',
    'ainl_plugin_version',
    'ainl_settings',
    'ainl_db_version'
);

foreach ($cleanup_options as $option) {
    delete_option($option);
}

echo "<p style='color: green;'>✅ 관련 옵션들을 정리했습니다.</p>";

// 4. 데이터베이스 테이블 존재 확인
global $wpdb;
$tables_to_check = array(
    $wpdb->prefix . 'ainl_subscribers',
    $wpdb->prefix . 'ainl_categories',
    $wpdb->prefix . 'ainl_campaigns',
    $wpdb->prefix . 'ainl_statistics'
);

echo "<h2>데이터베이스 테이블 상태</h2>";
foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        echo "<p style='color: blue;'>📋 $table - 존재함</p>";
    } else {
        echo "<p style='color: gray;'>📋 $table - 존재하지 않음</p>";
    }
}

// 5. 수정 방법 안내
echo "<h2>다음 단계</h2>";
echo "<ol>";
echo "<li><strong>WordPress 관리자 대시보드로 이동</strong> - 이제 정상적으로 접근 가능해야 합니다.</li>";
echo "<li><strong>플러그인 목록 확인</strong> - AI Newsletter Generator Pro가 비활성화되어 있는지 확인</li>";
echo "<li><strong>수정된 플러그인 파일 업로드</strong> - 수정된 버전을 업로드 후 다시 활성화</li>";
echo "<li><strong>이 파일 삭제</strong> - 보안을 위해 emergency-fix.php 파일을 서버에서 삭제</li>";
echo "</ol>";

echo "<h2>WordPress 디버깅 활성화 방법</h2>";
echo "<p>wp-config.php 파일에 다음 코드를 추가하세요 (/* 여기까지 편집을 멈추세요 */ 위에):</p>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
ini_set('memory_limit', '256M');
</pre>";

echo "<p style='color: red;'><strong>중요:</strong> 작업 완료 후 이 파일(emergency-fix.php)을 반드시 삭제하세요!</p>";

// 6. 현재 활성 플러그인 목록 표시
echo "<h2>현재 활성 플러그인</h2>";
$active_plugins = get_option('active_plugins');
if (empty($active_plugins)) {
    echo "<p>활성화된 플러그인이 없습니다.</p>";
} else {
    echo "<ul>";
    foreach ($active_plugins as $plugin) {
        echo "<li>$plugin</li>";
    }
    echo "</ul>";
}
?> 
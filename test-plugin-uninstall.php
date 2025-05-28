<?php
/**
 * AI Newsletter Generator Pro Uninstall Test
 * WordPress 플러그인 삭제 프로세스 테스트 도구
 * 
 * 사용법: WordPress 관리자 페이지에서 이 파일을 실행하여 
 * 플러그인 삭제 시 발생할 수 있는 문제를 미리 확인
 * 
 * @version 1.0.0
 */

// WordPress 환경에서만 실행
if (!defined('ABSPATH')) {
    echo "WordPress 환경에서만 실행할 수 있습니다.";
    exit;
}

// 관리자 권한 체크
if (!current_user_can('activate_plugins')) {
    echo "관리자 권한이 필요합니다.";
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>AI Newsletter Generator Pro - Uninstall Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>AI Newsletter Generator Pro - Uninstall Process Test</h1>
    
    <?php
    echo "<div class='test-section'>";
    echo "<h2>1. WordPress 환경 체크</h2>";
    
    // WordPress 상수 체크
    if (defined('ABSPATH')) {
        echo "<p class='success'>✓ ABSPATH 정의됨</p>";
    } else {
        echo "<p class='error'>✗ ABSPATH 정의되지 않음</p>";
    }
    
    if (defined('WP_UNINSTALL_PLUGIN')) {
        echo "<p class='info'>✓ WP_UNINSTALL_PLUGIN 정의됨 (실제 삭제 프로세스)</p>";
    } else {
        echo "<p class='warning'>⚠ WP_UNINSTALL_PLUGIN 정의되지 않음 (테스트 모드)</p>";
    }
    
    echo "</div>";
    
    echo "<div class='test-section'>";
    echo "<h2>2. WordPress 함수 가용성 체크</h2>";
    
    $required_functions = array(
        'delete_option' => '옵션 삭제',
        'wp_clear_scheduled_hook' => '크론 작업 정리',
        'delete_transient' => '트랜지언트 정리',
        'wp_upload_dir' => '업로드 디렉토리 정보',
        'current_user_can' => '권한 체크',
        'error_log' => '로그 기록'
    );
    
    foreach ($required_functions as $func => $desc) {
        if (function_exists($func)) {
            echo "<p class='success'>✓ $func() - $desc</p>";
        } else {
            echo "<p class='error'>✗ $func() - $desc (함수 없음)</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='test-section'>";
    echo "<h2>3. 플러그인 옵션 체크</h2>";
    
    $plugin_options = array(
        'ainl_plugin_activated',
        'ainl_plugin_version',
        'ainl_settings'
    );
    
    foreach ($plugin_options as $option) {
        $value = get_option($option);
        if ($value !== false) {
            echo "<p class='info'>✓ $option: " . (is_array($value) ? 'Array' : $value) . "</p>";
        } else {
            echo "<p class='warning'>⚠ $option: 설정되지 않음</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='test-section'>";
    echo "<h2>4. 메모리 및 실행 시간 체크</h2>";
    
    $memory_limit = ini_get('memory_limit');
    $max_execution_time = ini_get('max_execution_time');
    
    echo "<p class='info'>현재 메모리 제한: $memory_limit</p>";
    echo "<p class='info'>현재 실행 시간 제한: {$max_execution_time}초</p>";
    
    // 메모리 증가 테스트
    if (function_exists('ini_set')) {
        $old_memory = ini_get('memory_limit');
        @ini_set('memory_limit', '256M');
        $new_memory = ini_get('memory_limit');
        
        if ($new_memory !== $old_memory) {
            echo "<p class='success'>✓ 메모리 제한 증가 가능: $old_memory → $new_memory</p>";
        } else {
            echo "<p class='warning'>⚠ 메모리 제한 증가 불가</p>";
        }
        
        // 원복
        @ini_set('memory_limit', $old_memory);
    }
    
    echo "</div>";
    
    echo "<div class='test-section'>";
    echo "<h2>5. 파일 시스템 권한 체크</h2>";
    
    if (function_exists('wp_upload_dir')) {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/ai-newsletter-files/';
        
        echo "<p class='info'>플러그인 업로드 디렉토리: $plugin_upload_dir</p>";
        
        if (is_dir($plugin_upload_dir)) {
            echo "<p class='info'>✓ 디렉토리 존재함</p>";
            
            if (is_readable($plugin_upload_dir)) {
                echo "<p class='success'>✓ 읽기 권한 있음</p>";
            } else {
                echo "<p class='error'>✗ 읽기 권한 없음</p>";
            }
            
            if (is_writable($plugin_upload_dir)) {
                echo "<p class='success'>✓ 쓰기 권한 있음</p>";
            } else {
                echo "<p class='error'>✗ 쓰기 권한 없음</p>";
            }
        } else {
            echo "<p class='warning'>⚠ 플러그인 업로드 디렉토리가 존재하지 않음</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='test-section'>";
    echo "<h2>6. uninstall.php 파일 체크</h2>";
    
    $plugin_dir = WP_PLUGIN_DIR . '/ai-newsletter-generator-pro/';
    $uninstall_file = $plugin_dir . 'uninstall.php';
    
    if (file_exists($uninstall_file)) {
        echo "<p class='success'>✓ uninstall.php 파일 존재</p>";
        
        if (is_readable($uninstall_file)) {
            echo "<p class='success'>✓ uninstall.php 읽기 가능</p>";
        } else {
            echo "<p class='error'>✗ uninstall.php 읽기 불가</p>";
        }
    } else {
        echo "<p class='error'>✗ uninstall.php 파일 없음</p>";
    }
    
    echo "</div>";
    
    ?>
    
    <div class="test-section">
        <h2>7. 테스트 결과 요약</h2>
        <p><strong>이 테스트는 실제로 플러그인을 삭제하지 않습니다.</strong></p>
        <p>위의 모든 체크가 성공적이면 플러그인 삭제 시 critical error가 발생할 가능성이 낮습니다.</p>
        <p>오류가 있는 항목들은 uninstall.php에서 안전하게 처리되도록 구현되었습니다.</p>
    </div>
    
</body>
</html> 
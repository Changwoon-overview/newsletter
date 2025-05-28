<?php
/**
 * WordPress 디버깅 설정 파일
 * 이 내용을 wp-config.php 파일에 추가하세요.
 */

// WordPress 디버깅 활성화
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// 스크립트 디버깅 활성화
define('SCRIPT_DEBUG', true);

// 로그 파일 위치: /wp-content/debug.log
// FTP나 파일 매니저로 확인 가능

// 메모리 제한 늘리기 (필요 시)
ini_set('memory_limit', '256M');

// 오류 표시 설정
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * 사용법:
 * 1. WordPress 사이트의 wp-config.php 파일을 백업
 * 2. 위 설정들을 wp-config.php에 추가 (/* 여기까지 편집을 멈추세요 */ 위에)
 * 3. 플러그인 활성화 시도
 * 4. /wp-content/debug.log 파일 확인
 * 5. 정확한 오류 메시지 확인 후 수정
 */ 
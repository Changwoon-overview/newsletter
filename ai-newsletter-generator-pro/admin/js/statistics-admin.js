/**
 * AI Newsletter Generator Pro - 통계 관리자 스크립트
 * 관리자 통계 대시보드의 차트 및 인터랙션을 담당합니다.
 *
 * @package AI_Newsletter_Generator_Pro
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // 전역 변수
    var performanceChart, deviceChart;
    
    /**
     * 페이지 로드 시 초기화
     */
    $(document).ready(function() {
        initCharts();
        loadOverviewStats();
        loadCampaignsTable();
        bindEvents();
    });
    
    /**
     * 차트 초기화
     */
    function initCharts() {
        initPerformanceChart();
        initDeviceChart();
    }
    
    /**
     * 성과 추이 차트 초기화
     */
    function initPerformanceChart() {
        var ctx = document.getElementById('performance-chart');
        if (!ctx) return;
        
        performanceChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '이메일 성과 추이 (최근 30일)'
                    },
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: '날짜'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: '이메일 수'
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    intersect: false,
                },
                elements: {
                    line: {
                        tension: 0.4
                    }
                }
            }
        });
        
        // 성과 데이터 로드
        loadPerformanceData();
    }
    
    /**
     * 디바이스별 차트 초기화
     */
    function initDeviceChart() {
        var ctx = document.getElementById('device-chart');
        if (!ctx) return;
        
        deviceChart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '디바이스별 오픈율 분포'
                    },
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // 디바이스 데이터 로드
        loadDeviceData();
    }
    
    /**
     * 개요 통계 로드
     */
    function loadOverviewStats() {
        $.ajax({
            url: ainl_statistics.ajax_url,
            type: 'POST',
            data: {
                action: 'ainl_get_statistics',
                type: 'overview',
                nonce: ainl_statistics.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateOverviewCards(response.data);
                } else {
                    console.error('통계 데이터 로드 실패:', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX 요청 실패:', error);
            }
        });
    }
    
    /**
     * 개요 카드 업데이트
     */
    function updateOverviewCards(data) {
        $('#total-sent').text(formatNumber(data.total_sent));
        $('#open-rate').text(data.open_rate + '%');
        $('#click-rate').text(data.click_rate + '%');
        $('#unsubscribe-rate').text(data.unsubscribe_rate + '%');
        
        // 애니메이션 효과
        $('.ainl-stat-number').addClass('updated');
        setTimeout(function() {
            $('.ainl-stat-number').removeClass('updated');
        }, 500);
    }
    
    /**
     * 성과 데이터 로드
     */
    function loadPerformanceData() {
        $.ajax({
            url: ainl_statistics.ajax_url,
            type: 'POST',
            data: {
                action: 'ainl_get_statistics',
                type: 'performance',
                nonce: ainl_statistics.nonce
            },
            success: function(response) {
                if (response.success && performanceChart) {
                    performanceChart.data = response.data;
                    performanceChart.update();
                }
            },
            error: function(xhr, status, error) {
                console.error('성과 데이터 로드 실패:', error);
            }
        });
    }
    
    /**
     * 디바이스 데이터 로드
     */
    function loadDeviceData() {
        $.ajax({
            url: ainl_statistics.ajax_url,
            type: 'POST',
            data: {
                action: 'ainl_get_statistics',
                type: 'device',
                nonce: ainl_statistics.nonce
            },
            success: function(response) {
                if (response.success && deviceChart) {
                    deviceChart.data = response.data;
                    deviceChart.update();
                }
            },
            error: function(xhr, status, error) {
                console.error('디바이스 데이터 로드 실패:', error);
            }
        });
    }
    
    /**
     * 캠페인 테이블 로드
     */
    function loadCampaignsTable() {
        $.ajax({
            url: ainl_statistics.ajax_url,
            type: 'POST',
            data: {
                action: 'ainl_get_statistics',
                type: 'campaigns',
                nonce: ainl_statistics.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCampaignsTable(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('캠페인 데이터 로드 실패:', error);
            }
        });
    }
    
    /**
     * 캠페인 테이블 렌더링
     */
    function renderCampaignsTable(campaigns) {
        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead>';
        html += '<tr>';
        html += '<th>캠페인 이름</th>';
        html += '<th>제목</th>';
        html += '<th>발송일</th>';
        html += '<th>총 발송</th>';
        html += '<th>오픈율</th>';
        html += '<th>클릭률</th>';
        html += '<th>구독 해지</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (campaigns.length === 0) {
            html += '<tr><td colspan="7" style="text-align: center;">캠페인 데이터가 없습니다.</td></tr>';
        } else {
            campaigns.forEach(function(campaign) {
                html += '<tr>';
                html += '<td><strong>' + escapeHtml(campaign.name) + '</strong></td>';
                html += '<td>' + escapeHtml(campaign.subject) + '</td>';
                html += '<td>' + formatDate(campaign.created_at) + '</td>';
                html += '<td>' + formatNumber(campaign.total_sent) + '</td>';
                html += '<td><span class="open-rate">' + campaign.open_rate + '%</span></td>';
                html += '<td><span class="click-rate">' + campaign.click_rate + '%</span></td>';
                html += '<td>' + formatNumber(campaign.total_unsubscribed) + '</td>';
                html += '</tr>';
            });
        }
        
        html += '</tbody>';
        html += '</table>';
        
        $('#campaigns-table-container').html(html);
    }
    
    /**
     * 이벤트 바인딩
     */
    function bindEvents() {
        // CSV 내보내기
        $('#export-csv').on('click', function(e) {
            e.preventDefault();
            exportData('csv');
        });
        
        // PDF 내보내기
        $('#export-pdf').on('click', function(e) {
            e.preventDefault();
            exportData('pdf');
        });
        
        // 새로고침 버튼 (추가 가능)
        $(document).on('click', '.refresh-stats', function(e) {
            e.preventDefault();
            refreshAllData();
        });
    }
    
    /**
     * 데이터 내보내기
     */
    function exportData(format) {
        var button = format === 'csv' ? $('#export-csv') : $('#export-pdf');
        var originalText = button.text();
        
        button.prop('disabled', true).text('내보내는 중...');
        
        // 새 창에서 내보내기 수행
        var form = $('<form>', {
            method: 'POST',
            action: ainl_statistics.ajax_url,
            target: '_blank'
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'ainl_export_statistics'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'format',
            value: format
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: ainl_statistics.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        // 버튼 상태 복원
        setTimeout(function() {
            button.prop('disabled', false).text(originalText);
        }, 2000);
    }
    
    /**
     * 모든 데이터 새로고침
     */
    function refreshAllData() {
        loadOverviewStats();
        loadPerformanceData();
        loadDeviceData();
        loadCampaignsTable();
        
        // 사용자에게 피드백 제공
        var notice = $('<div class="notice notice-success is-dismissible"><p>통계 데이터가 새로고침되었습니다.</p></div>');
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 3000);
    }
    
    /**
     * 유틸리티 함수들
     */
    
    /**
     * 숫자 포맷팅 (천 단위 구분자)
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    /**
     * 날짜 포맷팅
     */
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.getFullYear() + '-' + 
               String(date.getMonth() + 1).padStart(2, '0') + '-' + 
               String(date.getDate()).padStart(2, '0');
    }
    
    /**
     * HTML 이스케이프
     */
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    /**
     * 로딩 스피너 표시
     */
    function showLoadingSpinner(container) {
        var spinner = '<div class="ainl-loading-spinner">' +
                     '<div class="spinner"></div>' +
                     '<p>데이터를 불러오는 중...</p>' +
                     '</div>';
        $(container).html(spinner);
    }
    
    /**
     * 에러 메시지 표시
     */
    function showErrorMessage(container, message) {
        var error = '<div class="ainl-error-message">' +
                   '<p><strong>오류:</strong> ' + escapeHtml(message) + '</p>' +
                   '<button class="button retry-btn">다시 시도</button>' +
                   '</div>';
        $(container).html(error);
        
        // 재시도 버튼 이벤트
        $(container).find('.retry-btn').on('click', function() {
            refreshAllData();
        });
    }
    
    /**
     * 반응형 차트 크기 조정
     */
    $(window).on('resize', function() {
        if (performanceChart) {
            performanceChart.resize();
        }
        if (deviceChart) {
            deviceChart.resize();
        }
    });
    
    /**
     * 실시간 업데이트 (선택사항)
     */
    function startRealTimeUpdates() {
        setInterval(function() {
            loadOverviewStats();
        }, 300000); // 5분마다 업데이트
    }
    
    // 실시간 업데이트 시작 (옵션)
    // startRealTimeUpdates();

})(jQuery); 
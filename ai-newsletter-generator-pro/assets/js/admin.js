/**
 * AI Newsletter Generator Pro - 관리자 JavaScript
 * WordPress 관리자 페이지의 인터랙션을 담당합니다.
 */

(function($) {
    'use strict';
    
    // 플러그인 초기화
    var AINL_Admin = {
        
        /**
         * 초기화 함수
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        /**
         * 이벤트 바인딩
         */
        bindEvents: function() {
            // 삭제 확인 대화상자
            $(document).on('click', '.ainl-delete-item', this.confirmDelete);
            
            // AJAX 폼 제출
            $(document).on('submit', '.ainl-ajax-form', this.handleAjaxForm);
            
            // 탭 전환
            $(document).on('click', '.ainl-tabs a', this.switchTab);
            
            // 테이블 행 선택
            $(document).on('change', '.ainl-table input[type="checkbox"]', this.handleRowSelection);
            
            // 일괄 작업
            $(document).on('click', '.ainl-bulk-action', this.handleBulkAction);
            
            // 실시간 검색
            $(document).on('keyup', '.ainl-search-input', this.handleSearch);
        },
        
        /**
         * 컴포넌트 초기화
         */
        initComponents: function() {
            // 툴팁 초기화
            this.initTooltips();
            
            // 차트 초기화 (통계 페이지)
            this.initCharts();
            
            // 폼 검증 초기화
            this.initFormValidation();
        },
        
        /**
         * 삭제 확인 대화상자
         */
        confirmDelete: function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var message = $this.data('confirm') || ainl_ajax.strings.confirm_delete;
            
            if (confirm(message)) {
                var url = $this.attr('href');
                if (url) {
                    window.location.href = url;
                } else {
                    // AJAX 삭제 처리
                    AINL_Admin.performAjaxDelete($this);
                }
            }
        },
        
        /**
         * AJAX 삭제 수행
         */
        performAjaxDelete: function($element) {
            var itemId = $element.data('id');
            var itemType = $element.data('type');
            
            $.ajax({
                url: ainl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ainl_delete_item',
                    item_id: itemId,
                    item_type: itemType,
                    nonce: ainl_ajax.nonce
                },
                beforeSend: function() {
                    $element.addClass('ainl-loading');
                },
                success: function(response) {
                    if (response.success) {
                        $element.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                        AINL_Admin.showNotice(response.data.message, 'success');
                    } else {
                        AINL_Admin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    AINL_Admin.showNotice(ainl_ajax.strings.error, 'error');
                },
                complete: function() {
                    $element.removeClass('ainl-loading');
                }
            });
        },
        
        /**
         * AJAX 폼 제출 처리
         */
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = new FormData(this);
            formData.append('nonce', ainl_ajax.nonce);
            
            $.ajax({
                url: ainl_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $form.find('.button-primary').prop('disabled', true).text(ainl_ajax.strings.saving);
                },
                success: function(response) {
                    if (response.success) {
                        AINL_Admin.showNotice(response.data.message, 'success');
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        }
                    } else {
                        AINL_Admin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    AINL_Admin.showNotice(ainl_ajax.strings.error, 'error');
                },
                complete: function() {
                    $form.find('.button-primary').prop('disabled', false).text(ainl_ajax.strings.saved);
                    setTimeout(function() {
                        $form.find('.button-primary').text('저장');
                    }, 2000);
                }
            });
        },
        
        /**
         * 탭 전환 처리
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var targetTab = $this.attr('href');
            
            // 활성 탭 변경
            $this.closest('.ainl-tabs').find('a').removeClass('active');
            $this.addClass('active');
            
            // 탭 콘텐츠 표시/숨김
            $('.ainl-tab-content').hide();
            $(targetTab).show();
        },
        
        /**
         * 테이블 행 선택 처리
         */
        handleRowSelection: function() {
            var $checkbox = $(this);
            var $table = $checkbox.closest('.ainl-table');
            var $row = $checkbox.closest('tr');
            
            if ($checkbox.prop('checked')) {
                $row.addClass('selected');
            } else {
                $row.removeClass('selected');
            }
            
            // 전체 선택 체크박스 상태 업데이트
            var totalRows = $table.find('tbody input[type="checkbox"]').length;
            var selectedRows = $table.find('tbody input[type="checkbox"]:checked').length;
            var $selectAll = $table.find('thead input[type="checkbox"]');
            
            if (selectedRows === 0) {
                $selectAll.prop('indeterminate', false).prop('checked', false);
            } else if (selectedRows === totalRows) {
                $selectAll.prop('indeterminate', false).prop('checked', true);
            } else {
                $selectAll.prop('indeterminate', true);
            }
            
            // 일괄 작업 버튼 활성화/비활성화
            $('.ainl-bulk-action').prop('disabled', selectedRows === 0);
        },
        
        /**
         * 일괄 작업 처리
         */
        handleBulkAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var $table = $button.closest('.ainl-admin-page').find('.ainl-table');
            var selectedIds = [];
            
            $table.find('tbody input[type="checkbox"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                alert('선택된 항목이 없습니다.');
                return;
            }
            
            if (action === 'delete' && !confirm('선택된 항목들을 삭제하시겠습니까?')) {
                return;
            }
            
            $.ajax({
                url: ainl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ainl_bulk_action',
                    bulk_action: action,
                    item_ids: selectedIds,
                    nonce: ainl_ajax.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true).addClass('ainl-loading');
                },
                success: function(response) {
                    if (response.success) {
                        AINL_Admin.showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        AINL_Admin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    AINL_Admin.showNotice(ainl_ajax.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('ainl-loading');
                }
            });
        },
        
        /**
         * 실시간 검색 처리
         */
        handleSearch: function() {
            var $input = $(this);
            var searchTerm = $input.val().toLowerCase();
            var $table = $input.closest('.ainl-admin-page').find('.ainl-table tbody');
            
            $table.find('tr').each(function() {
                var $row = $(this);
                var rowText = $row.text().toLowerCase();
                
                if (rowText.indexOf(searchTerm) === -1) {
                    $row.hide();
                } else {
                    $row.show();
                }
            });
        },
        
        /**
         * 알림 메시지 표시
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="ainl-notice ' + type + '">' + message + '</div>');
            
            // 기존 알림 제거
            $('.ainl-notice').remove();
            
            // 새 알림 추가
            $('.ainl-admin-page').prepend($notice);
            
            // 자동 숨김 (에러가 아닌 경우)
            if (type !== 'error') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        },
        
        /**
         * 툴팁 초기화
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $element = $(this);
                var tooltipText = $element.data('tooltip');
                
                $element.attr('title', tooltipText);
            });
        },
        
        /**
         * 차트 초기화 (통계 페이지용)
         */
        initCharts: function() {
            // Chart.js가 로드된 경우에만 실행
            if (typeof Chart !== 'undefined') {
                this.initDashboardCharts();
                this.initStatisticsCharts();
            }
        },
        
        /**
         * 대시보드 차트 초기화
         */
        initDashboardCharts: function() {
            var $chartContainer = $('#ainl-dashboard-chart');
            if ($chartContainer.length === 0) return;
            
            // 간단한 라인 차트 예제
            var ctx = $chartContainer[0].getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['1월', '2월', '3월', '4월', '5월', '6월'],
                    datasets: [{
                        label: '발송 수',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        /**
         * 통계 페이지 차트 초기화
         */
        initStatisticsCharts: function() {
            // 통계 페이지의 차트들을 초기화
            // 실제 구현은 작업 12에서 진행
        },
        
        /**
         * 폼 검증 초기화
         */
        initFormValidation: function() {
            // 이메일 검증
            $('input[type="email"]').on('blur', function() {
                var email = $(this).val();
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    $(this).addClass('error');
                    AINL_Admin.showFieldError($(this), '올바른 이메일 주소를 입력하세요.');
                } else {
                    $(this).removeClass('error');
                    AINL_Admin.hideFieldError($(this));
                }
            });
            
            // 필수 필드 검증
            $('input[required], textarea[required], select[required]').on('blur', function() {
                if (!$(this).val()) {
                    $(this).addClass('error');
                    AINL_Admin.showFieldError($(this), '이 필드는 필수입니다.');
                } else {
                    $(this).removeClass('error');
                    AINL_Admin.hideFieldError($(this));
                }
            });
        },
        
        /**
         * 필드 에러 표시
         */
        showFieldError: function($field, message) {
            var $error = $field.siblings('.field-error');
            if ($error.length === 0) {
                $error = $('<span class="field-error" style="color: #d63638; font-size: 12px;"></span>');
                $field.after($error);
            }
            $error.text(message);
        },
        
        /**
         * 필드 에러 숨김
         */
        hideFieldError: function($field) {
            $field.siblings('.field-error').remove();
        }
    };
    
    // 문서 준비 완료 시 초기화
    $(document).ready(function() {
        AINL_Admin.init();
    });
    
    // 전역 객체로 노출 (다른 스크립트에서 사용 가능)
    window.AINL_Admin = AINL_Admin;
    
})(jQuery); 
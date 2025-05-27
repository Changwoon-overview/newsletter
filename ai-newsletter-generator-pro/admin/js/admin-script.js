/**
 * AI Newsletter Generator Pro - 관리자 스크립트
 * 구독자 관리 및 기타 관리자 기능을 위한 JavaScript
 */

jQuery(document).ready(function($) {
    
    // 구독자 관리 기능 초기화
    initSubscriberManagement();
    
    // 템플릿 관리 기능 초기화
    initTemplateManagement();
    
    /**
     * 구독자 관리 기능 초기화
     */
    function initSubscriberManagement() {
        
        // 구독자 추가 버튼
        $('#add-subscriber-btn').click(function(e) {
            e.preventDefault();
            openSubscriberModal();
        });
        
        // 구독자 편집 버튼
        $(document).on('click', '.edit-subscriber', function(e) {
            e.preventDefault();
            const subscriberId = $(this).data('id');
            editSubscriber(subscriberId);
        });
        
        // 구독자 삭제 버튼
        $(document).on('click', '.delete-subscriber', function(e) {
            e.preventDefault();
            const subscriberId = $(this).data('id');
            deleteSubscriber(subscriberId);
        });
        
        // 전체 선택 체크박스
        $('#cb-select-all').change(function() {
            $('input[name="subscriber_ids[]"]').prop('checked', this.checked);
        });
        
        // 개별 체크박스 변경 시 전체 선택 상태 업데이트
        $(document).on('change', 'input[name="subscriber_ids[]"]', function() {
            const totalCheckboxes = $('input[name="subscriber_ids[]"]').length;
            const checkedCheckboxes = $('input[name="subscriber_ids[]"]:checked').length;
            $('#cb-select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
        
        // 대량 삭제 버튼
        $('#bulk-delete-btn').click(function() {
            const selectedIds = getSelectedSubscriberIds();
            if (selectedIds.length === 0) {
                alert('삭제할 구독자를 선택해주세요.');
                return;
            }
            
            if (confirm(ainl_ajax.strings.bulk_confirm_delete)) {
                bulkDeleteSubscribers(selectedIds);
            }
        });
        
        // 대량 상태 변경 버튼
        $('#bulk-status-btn').click(function() {
            const selectedIds = getSelectedSubscriberIds();
            if (selectedIds.length === 0) {
                alert('상태를 변경할 구독자를 선택해주세요.');
                return;
            }
            
            const newStatus = prompt('새로운 상태를 입력하세요 (active, inactive, unsubscribed, bounced, blocked):');
            if (newStatus && ['active', 'inactive', 'unsubscribed', 'bounced', 'blocked'].includes(newStatus)) {
                bulkUpdateStatus(selectedIds, newStatus);
            } else if (newStatus) {
                alert('유효하지 않은 상태입니다.');
            }
        });
        
        // CSV 가져오기 버튼
        $('#import-subscribers-btn').click(function() {
            openImportModal();
        });
        
        // CSV 내보내기 버튼
        $('#export-subscribers-btn').click(function() {
            exportSubscribers();
        });
        
        // 구독자 모달 초기화
        if ($('#subscriber-modal').length) {
            $('#subscriber-modal').dialog({
                autoOpen: false,
                modal: true,
                width: 500,
                height: 400,
                resizable: false,
                buttons: {
                    '저장': function() {
                        saveSubscriber();
                    },
                    '취소': function() {
                        $(this).dialog('close');
                    }
                }
            });
        }
        
        // CSV 가져오기 모달 초기화
        if ($('#import-modal').length) {
            $('#import-modal').dialog({
                autoOpen: false,
                modal: true,
                width: 500,
                height: 300,
                resizable: false,
                buttons: {
                    '가져오기': function() {
                        importSubscribers();
                    },
                    '취소': function() {
                        $(this).dialog('close');
                    }
                }
            });
        }
    }
    
    /**
     * 템플릿 관리 기능 초기화
     */
    function initTemplateManagement() {
        
        // 템플릿 테스트 버튼
        $('#test-templates-btn').click(function() {
            const button = $(this);
            const resultsDiv = $('#test-results');
            
            button.prop('disabled', true).text('테스트 실행 중...');
            resultsDiv.html('<p>템플릿 시스템을 테스트하고 있습니다...</p>');
            
            $.post(ainl_ajax.ajax_url, {
                action: 'ainl_test_templates',
                nonce: ainl_ajax.nonce
            }, function(response) {
                button.prop('disabled', false).text('모든 템플릿 테스트 실행');
                
                if (response.success) {
                    let html = '<div class="notice notice-success"><p>모든 테스트가 성공적으로 완료되었습니다!</p></div>';
                    html += '<h3>테스트 결과:</h3><ul>';
                    
                    response.data.results.forEach(function(result) {
                        const status = result.passed ? '✅' : '❌';
                        html += `<li>${status} ${result.test}: ${result.message}</li>`;
                    });
                    
                    html += '</ul>';
                    resultsDiv.html(html);
                } else {
                    resultsDiv.html('<div class="notice notice-error"><p>테스트 실행 중 오류가 발생했습니다: ' + response.data + '</p></div>');
                }
            });
        });
    }
    
    /**
     * 구독자 모달 열기
     */
    function openSubscriberModal(subscriberId = null) {
        const modal = $('#subscriber-modal');
        const form = $('#subscriber-form');
        
        // 폼 초기화
        form[0].reset();
        $('#subscriber-id').val('');
        
        if (subscriberId) {
            // 편집 모드: 구독자 정보 로드
            modal.dialog('option', 'title', '구독자 편집');
            loadSubscriberData(subscriberId);
        } else {
            // 추가 모드
            modal.dialog('option', 'title', '새 구독자 추가');
        }
        
        modal.dialog('open');
    }
    
    /**
     * CSV 가져오기 모달 열기
     */
    function openImportModal() {
        const modal = $('#import-modal');
        const form = $('#import-form');
        
        // 폼 초기화
        form[0].reset();
        modal.dialog('open');
    }
    
    /**
     * 구독자 정보 로드
     */
    function loadSubscriberData(subscriberId) {
        // 테이블에서 구독자 정보 추출
        const row = $(`tr[data-subscriber-id="${subscriberId}"]`);
        if (row.length) {
            const email = row.find('td:nth-child(2) strong').text();
            const name = row.find('td:nth-child(3)').text().trim();
            const status = row.find('.ainl-status').attr('class').match(/ainl-status-(\w+)/)[1];
            const tags = row.find('td:nth-child(5)').text().trim();
            
            // 이름 분리 (간단한 방식)
            const nameParts = name !== '-' ? name.split(' ') : ['', ''];
            const firstName = nameParts[0] || '';
            const lastName = nameParts.slice(1).join(' ') || '';
            
            // 폼에 데이터 설정
            $('#subscriber-id').val(subscriberId);
            $('#subscriber-email').val(email);
            $('#subscriber-first-name').val(firstName);
            $('#subscriber-last-name').val(lastName);
            $('#subscriber-status').val(status);
            $('#subscriber-tags').val(tags !== '-' ? tags : '');
        }
    }
    
    /**
     * 구독자 저장
     */
    function saveSubscriber() {
        const form = $('#subscriber-form');
        const subscriberId = $('#subscriber-id').val();
        const isEdit = subscriberId !== '';
        
        const data = {
            action: isEdit ? 'ainl_update_subscriber' : 'ainl_add_subscriber',
            nonce: ainl_ajax.nonce,
            email: $('#subscriber-email').val(),
            first_name: $('#subscriber-first-name').val(),
            last_name: $('#subscriber-last-name').val(),
            status: $('#subscriber-status').val(),
            tags: $('#subscriber-tags').val()
        };
        
        if (isEdit) {
            data.subscriber_id = subscriberId;
        }
        
        // 이메일 유효성 검사
        if (!data.email || !isValidEmail(data.email)) {
            alert('유효한 이메일 주소를 입력해주세요.');
            return;
        }
        
        $.post(ainl_ajax.ajax_url, data, function(response) {
            if (response.success) {
                alert(response.data.message);
                $('#subscriber-modal').dialog('close');
                location.reload(); // 페이지 새로고침
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 구독자 편집
     */
    function editSubscriber(subscriberId) {
        openSubscriberModal(subscriberId);
    }
    
    /**
     * 구독자 삭제
     */
    function deleteSubscriber(subscriberId) {
        if (!confirm(ainl_ajax.strings.confirm_delete)) {
            return;
        }
        
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_delete_subscriber',
            nonce: ainl_ajax.nonce,
            subscriber_id: subscriberId
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                $(`tr[data-subscriber-id="${subscriberId}"]`).fadeOut(function() {
                    $(this).remove();
                });
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 선택된 구독자 ID 목록 반환
     */
    function getSelectedSubscriberIds() {
        const selectedIds = [];
        $('input[name="subscriber_ids[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        return selectedIds;
    }
    
    /**
     * 대량 구독자 삭제
     */
    function bulkDeleteSubscribers(subscriberIds) {
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_bulk_action_subscribers',
            nonce: ainl_ajax.nonce,
            bulk_action: 'delete',
            subscriber_ids: subscriberIds
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 대량 상태 업데이트
     */
    function bulkUpdateStatus(subscriberIds, newStatus) {
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_bulk_action_subscribers',
            nonce: ainl_ajax.nonce,
            bulk_action: 'status_change',
            subscriber_ids: subscriberIds,
            new_status: newStatus
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * CSV 가져오기
     */
    function importSubscribers() {
        const formData = new FormData();
        const fileInput = $('#csv-file')[0];
        
        if (!fileInput.files[0]) {
            alert('CSV 파일을 선택해주세요.');
            return;
        }
        
        formData.append('action', 'ainl_import_subscribers');
        formData.append('nonce', ainl_ajax.nonce);
        formData.append('csv_file', fileInput.files[0]);
        formData.append('update_existing', $('#update-existing').is(':checked') ? '1' : '0');
        
        $.ajax({
            url: ainl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const result = response.data.result;
                    let message = `가져오기 완료!\n`;
                    message += `총 ${result.total_rows}행 처리\n`;
                    message += `가져온 구독자: ${result.imported}명\n`;
                    message += `업데이트된 구독자: ${result.updated}명\n`;
                    message += `건너뛴 구독자: ${result.skipped}명`;
                    
                    if (result.errors.length > 0) {
                        message += `\n\n오류:\n${result.errors.slice(0, 5).join('\n')}`;
                        if (result.errors.length > 5) {
                            message += `\n... 및 ${result.errors.length - 5}개 추가 오류`;
                        }
                    }
                    
                    alert(message);
                    $('#import-modal').dialog('close');
                    location.reload();
                } else {
                    alert('오류: ' + response.data);
                }
            },
            error: function() {
                alert('파일 업로드 중 오류가 발생했습니다.');
            }
        });
    }
    
    /**
     * CSV 내보내기
     */
    function exportSubscribers() {
        // 현재 필터 조건 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status') || '';
        const search = urlParams.get('s') || '';
        
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_export_subscribers',
            nonce: ainl_ajax.nonce,
            status: status,
            search: search
        }, function(response) {
            if (response.success) {
                // 다운로드 링크 생성
                const link = document.createElement('a');
                link.href = response.data.download_url;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                alert(response.data.message);
            } else {
                alert('오류: ' + response.data);
            }
        });
    }
    
    /**
     * 이메일 유효성 검사
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * 템플릿 미리보기 로드
     */
    window.loadTemplatePreview = function(templateId) {
        const iframe = document.querySelector(`iframe[data-template="${templateId}"]`);
        
        $.post(ainl_ajax.ajax_url, {
            action: 'ainl_get_template_preview',
            template_id: templateId,
            nonce: ainl_ajax.nonce
        }, function(response) {
            if (response.success) {
                iframe.srcdoc = response.data.html;
            } else {
                alert('미리보기를 로드할 수 없습니다: ' + response.data);
            }
        });
    };
    
    /**
     * 템플릿 사용
     */
    window.useTemplate = function(templateId) {
        const baseUrl = window.location.origin + window.location.pathname.replace(/\/wp-admin\/.*/, '/wp-admin/admin.php');
        window.location.href = `${baseUrl}?page=ainl-create-newsletter&template=${templateId}`;
    };
    
}); 
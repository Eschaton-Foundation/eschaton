/**
 * Document Admin UI JavaScript
 * AI Chat & Search Pro
 * Supports PDF, TXT, MD, XML, CSV files
 */

(function($) {
    'use strict';

    const PDFAdmin = {
        init: function() {
            this.bindEvents();
            this.loadPDFList();
        },

        bindEvents: function() {
            // Open PDF modal
            $(document).on('click', '#upload-pdf-btn', function(e) {
                e.preventDefault();
                $('#pdf-upload-modal').fadeIn(200);
                PDFAdmin.loadPDFList();
            });

            // Close modal
            $(document).on('click', '.listeo-ai-modal-close, #pdf-modal-close, .listeo-ai-modal-overlay', function(e) {
                if ($(e.target).hasClass('listeo-ai-modal-overlay') ||
                    $(e.target).closest('.listeo-ai-modal-close').length ||
                    $(e.target).is('#pdf-modal-close')) {
                    $('#pdf-upload-modal').fadeOut(200);
                }
            });

            // Select PDF files
            $(document).on('click', '#pdf-select-btn', function() {
                $('#pdf-file-input').click();
            });

            // Handle file selection
            $(document).on('change', '#pdf-file-input', function() {
                const files = this.files;
                if (files.length > 0) {
                    PDFAdmin.uploadFiles(files);
                }
            });

            // Delete PDF
            $(document).on('click', '.pdf-delete-btn', function(e) {
                e.preventDefault();
                const filename = $(this).data('filename');
                if (confirm(aiChatProPdfConfig.strings.confirm_delete)) {
                    PDFAdmin.deletePDF(filename);
                }
            });

            // Train PDF
            $(document).on('click', '.pdf-train-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const filename = $btn.data('filename');
                PDFAdmin.trainPDF(filename, $btn);
            });
        },

        /**
         * Upload PDF files
         */
        uploadFiles: function(files) {
            const totalFiles = files.length;
            let uploadedCount = 0;

            $('.pdf-upload-progress').show();
            $('.progress-fill').css('width', '0%');
            $('.progress-text').html('<span class="airs-spinner"></span> ' + aiChatProPdfConfig.strings.uploading);

            // Upload files sequentially
            const uploadNext = function(index) {
                if (index >= totalFiles) {
                    // All done
                    setTimeout(function() {
                        $('.pdf-upload-progress').fadeOut(300);
                        $('#pdf-file-input').val(''); // Clear input
                        PDFAdmin.showSuccess(aiChatProPdfConfig.strings.upload_success);
                        PDFAdmin.loadPDFList(); // Refresh list
                    }, 1000);
                    return;
                }

                const file = files[index];
                const formData = new FormData();
                formData.append('action', 'ai_chat_pro_upload_pdf');
                formData.append('nonce', aiChatProPdfConfig.nonce);
                formData.append('pdf_file', file);

                $.ajax({
                    url: aiChatProPdfConfig.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        uploadedCount++;
                        const progress = (uploadedCount / totalFiles) * 100;
                        $('.progress-fill').css('width', progress + '%');
                        $('.progress-text').html(`<span class="airs-spinner"></span> ${uploadedCount} / ${totalFiles} ${aiChatProPdfConfig.strings.uploading}`);

                        if (!response.success) {
                            PDFAdmin.showError(response.data.message || aiChatProPdfConfig.strings.error);
                        }

                        // Upload next file
                        uploadNext(index + 1);
                    },
                    error: function(xhr) {
                        PDFAdmin.showError(aiChatProPdfConfig.strings.error);
                        uploadedCount++;
                        // Continue with next file
                        uploadNext(index + 1);
                    }
                });
            };

            // Start uploading
            uploadNext(0);
        },

        /**
         * Load PDF documents list
         */
        loadPDFList: function() {
            $('#pdf-documents-list').html('<p class="loading-message"><span class="airs-spinner"></span> ' + aiChatProPdfConfig.strings.processing + '</p>');

            $.ajax({
                url: aiChatProPdfConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_chat_pro_get_pdf_list',
                    nonce: aiChatProPdfConfig.nonce
                },
                success: function(response) {
                    if (response.success && response.data.documents) {
                        PDFAdmin.renderPDFList(response.data.documents);
                    } else {
                        $('#pdf-documents-list').html('<p class="pdf-no-documents">No documents uploaded yet.</p>');
                    }
                },
                error: function() {
                    $('#pdf-documents-list').html('<p class="pdf-error">Failed to load document list.</p>');
                }
            });
        },

        /**
         * Render documents list
         */
        renderPDFList: function(documents) {
            if (documents.length === 0) {
                $('#pdf-documents-list').html('<p class="pdf-no-documents">No documents uploaded yet.</p>');
                return;
            }

            let html = '';
            documents.forEach(function(doc) {
                const indexedChunks = doc.indexed_chunks || 0;
                const totalChunks = doc.total_chunks || 0;
                const isFullyIndexed = doc.is_fully_indexed || false;

                // Determine status badge
                let statusBadge = '';
                let showTrainButton = false;

                if (isFullyIndexed) {
                    statusBadge = `<span class="pdf-status-badge status-indexed">✓ Trained (${indexedChunks}/${totalChunks})</span>`;
                } else if (indexedChunks > 0) {
                    statusBadge = `<span class="pdf-status-badge status-training">⏳ Training (${indexedChunks}/${totalChunks})</span>`;
                } else {
                    // No embeddings yet - show pending with Train Now button
                    statusBadge = `<span class="pdf-status-badge status-pending">Pending training</span>`;
                    showTrainButton = true;
                }

                const fileType = (doc.file_type || 'pdf').toUpperCase();
                const fileIcon = PDFAdmin.getFileIcon(doc.file_type || 'pdf');

                html += `
                    <div class="pdf-document-item">
                        <div class="pdf-document-info">
                            <div class="pdf-document-name">
                                <span class="dashicons ${fileIcon}"></span>
                                ${PDFAdmin.escapeHtml(doc.filename)}
                                <span class="file-type-badge file-type-${(doc.file_type || 'pdf').toLowerCase()}">${fileType}</span>
                            </div>
                            <div class="pdf-document-meta">
                                ${statusBadge}
                                <span><strong>Chunks:</strong> ${totalChunks}</span>
                                <span><strong>Uploaded:</strong> ${PDFAdmin.formatDate(doc.upload_date)}</span>
                            </div>
                        </div>
                        <div class="pdf-document-actions">
                            ${showTrainButton ? `
                                <a href="#" class="pdf-action-btn pdf-train-btn" data-filename="${PDFAdmin.escapeHtml(doc.filename)}">
                                    <span class="dashicons dashicons-controls-play"></span> Train Now
                                </a>
                            ` : ''}
                            <a href="#" class="pdf-action-btn pdf-delete-btn" data-filename="${PDFAdmin.escapeHtml(doc.filename)}">
                                <span class="dashicons dashicons-trash"></span> Delete
                            </a>
                        </div>
                    </div>
                `;
            });

            $('#pdf-documents-list').html(html);
        },

        /**
         * Train document (queue embeddings)
         */
        trainPDF: function(filename, $btn) {
            const originalHtml = $btn.html();
            $btn.prop('disabled', true);
            $btn.html('<span class="airs-spinner"></span> Training...');

            $.ajax({
                url: aiChatProPdfConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_chat_pro_train_pdf',
                    nonce: aiChatProPdfConfig.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        PDFAdmin.showSuccess(response.data.message || 'Document queued for training');
                        // Reload list after a delay to show updated status
                        setTimeout(function() {
                            PDFAdmin.loadPDFList();
                        }, 2000);
                    } else {
                        PDFAdmin.showError(response.data.message || aiChatProPdfConfig.strings.error);
                        $btn.prop('disabled', false);
                        $btn.html(originalHtml);
                    }
                },
                error: function() {
                    PDFAdmin.showError(aiChatProPdfConfig.strings.error);
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                }
            });
        },

        /**
         * Delete document
         */
        deletePDF: function(filename) {
            $.ajax({
                url: aiChatProPdfConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_chat_pro_delete_pdf',
                    nonce: aiChatProPdfConfig.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        PDFAdmin.showSuccess(aiChatProPdfConfig.strings.delete_success);
                        PDFAdmin.loadPDFList();
                    } else {
                        PDFAdmin.showError(response.data.message || aiChatProPdfConfig.strings.error);
                    }
                },
                error: function() {
                    PDFAdmin.showError(aiChatProPdfConfig.strings.error);
                }
            });
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            const html = `<div class="pdf-success">${message}</div>`;
            $('.pdf-upload-section').prepend(html);
            setTimeout(function() {
                $('.pdf-success').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const html = `<div class="pdf-error">${message}</div>`;
            $('.pdf-upload-section').prepend(html);
            setTimeout(function() {
                $('.pdf-error').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Format date
         */
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },

        /**
         * Get dashicon class for file type
         */
        getFileIcon: function(fileType) {
            const icons = {
                'pdf': 'dashicons-pdf',
                'txt': 'dashicons-text-page',
                'md': 'dashicons-editor-code',
                'xml': 'dashicons-media-code',
                'csv': 'dashicons-media-spreadsheet'
            };
            return icons[fileType.toLowerCase()] || 'dashicons-media-document';
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PDFAdmin.init();
    });

})(jQuery);

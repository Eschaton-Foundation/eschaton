jQuery(document).ready(function ($) {
    function checkEditorHeader() {
        const editorHeader = $('.editor-header__settings');

        if (editorHeader.length > 0) {
            clearInterval(editorHeaderInterval);

            const importExportButton = $(
                `<button class="import-export-btn" title="Import/Export" style="display: flex; align-items: center; justify-content: center; gap: 6px; background-color: #e1f0ff; border: 1px solid #007cba; color: #007cba; padding: 5px 18px 4px 10px; cursor: pointer; border-radius: 2px; font-size: 12px;">
                    <div style="rotate:270deg; font-size: 20px; color: #007cba;">&#8651;</div>
                    <div style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
                        <span class="button-text" style="font-size: 8px; font-weight: bold; text-align: center; color: #007cba;">Import</span>
                        <hr class="separator" style="width: 100%; border: 0; border-top: 1px solid #007cba; margin: 2px 0;">
                        <span class="button-text" style="font-size: 8px; font-weight: bold; text-align: center; color: #007cba;">Export</span>
                    </div>
                </button>`
            );

            const slider = $(
                `<div class="right-slider" style="position: absolute; right: 0; width: 280px; height: calc(100vh - 65px); background: #fff; box-shadow: -2px 6px 10px rgba(0, 0, 0, 0.2); overflow: auto; transition: transform 0.3s ease; transform: translateX(100%); z-index: 10000;">
                    <div class="slider-header" style="position: sticky; top: 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; margin-top: 5px;">
                        <div class="tabs" style="display: flex; gap: 10px;">
                            <button class="tab" style="background: none; border: none; cursor: pointer; padding:1rem; border-bottom: 2px solid #007cba; font-weight: 500;">Import</button>
                            <button class="tab" style="background: none; border: none; cursor: pointer; padding:1rem 10px; border-bottom: 2px solid transparent; font-weight: 500;">Export</button>
                        </div>
                        <span class="close-slider" style="cursor: pointer; font-size: 25px; margin-right: 10px;">&times;</span>
                    </div>
                    <div class="slider-content" style="padding: 15px; color: #333; height: calc(100% - 91px); position: relative;">
                        <div class="tab-content import-content">
                            <p>Import options go here. You can import your CSV file using the options provided.</p>
                            <input type="file" id="import-file" name="import-file" accept=".csv" style="display: inline-block; margin-top: 1rem; width: 100%; padding: 1rem 8px; background-color: #eee; border-radius: 4px; box-shadow: 0 0 2px #888;">
                            <div class="loading-bar" style="display: none; margin-top: 10px; width: 100%; background-color: #EDEDED; border-radius: 10px; height: 5px;">
                                <div style="width: 0%; height: 100%; background-color: #007cba; border-radius: 10px;" id="loading-progress"></div>
                            </div>
                            <small id="import-status" style="margin-top: 10px; display: none; color: #017C01; text-align: right;"></small>
                            <button id="upload-import-btn" style="display: none; margin: 20px auto 0; background-color: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Upload Import</button>
                             <button id="clear-btn" style="display: none; margin: 20px auto 0; background-color: #007cba; margin-left:83px; color: white; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer;">clear</button>
                             <small id="smack-imp-message" style=" display: none; color:#FF0000; text-align: right;  "></small>
                            <p id="smack-message" style="margin-top: 25px; display: none; inline-block; background-color:#eee; padding: 1rem 8px; border: 2px solid #DCDCDC; border-radius: 8px; color: #333; height:50px; width: 230px;"></p>
                        </div>
                    
                        <div class="tab-content export-content" style="display: none;">
                            <p>Export options go here. You can export your CSV file using the options provided.</p>
                            <button id="export-btn" style="margin: 20px auto 0; display: block; background-color: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;margin-bottom:16px">Export CSV</button>
                             <small id="smack-exp-message" style=" display: none; color:#FF0000; text-align: right;  "></small>
                        </div>
                    </div>
                </div>`
            );

            const sliders = $('.interface-navigable-region.interface-interface-skeleton__sidebar');
            sliders.append(slider);

            let totalChildren = editorHeader.children().length;
            editorHeader.children().eq(totalChildren - 3).after(importExportButton);

            $('.import-export-btn').on('click', function () {
                $('.right-slider').css({
                    'transform': 'translateX(0)',
                    'z-index': '10000',
                });

                $('.interface-navigable-region.interface-interface-skeleton__sidebar').css({
                    'width': '280px'
                });
            });

            $('.right-slider').on('click', '.close-slider', function () {
                $('.right-slider').css({
                    'transform': 'translateX(100%)',
                    'z-index': '10',
                });

                $('.interface-navigable-region.interface-interface-skeleton__sidebar').css({
                    'width': ''
                });
            });

            $(document).on('click', function (event) {
                if (!$(event.target).closest('.right-slider, .import-export-btn').length) {
                    if ($('.right-slider').css('transform') !== 'translateX(0px)') {
                        $('.right-slider').css({
                            'transform': 'translateX(100%)',
                            'z-index': '10',
                        });

                        $('.interface-navigable-region.interface-interface-skeleton__sidebar').css({
                            'width': ''
                        });
                    }
                }
            });

            $('.tab').on('click', function () {
                $('.tab').css('border-bottom', '2px solid transparent');
                $(this).css('border-bottom', '2px solid #007cba');
                const index = $(this).index();
                $('.tab-content').hide();
                $('.tab-content').eq(index).show();
            });

            $('#import-file').on('change', function () {
                const fileName = $(this).val().split('\\').pop();
                if (!fileName) {
                    $('#upload-import-btn').hide();
                    return;
                }

                $('.loading-bar').show();
                $('#import-status').hide();
                $('#upload-import-btn').hide().prop('disabled', true);

                let truncatedFileName = fileName;
                if (fileName.length > 10) {
                    truncatedFileName = fileName.substring(0, 10) + '...csv';
                }

                $('#import-status').show().text(`Importing ${truncatedFileName}`).css('color', '#017C01');

                let progress = 0;
                const interval = setInterval(function () {
                    progress += 10;
                    $('#loading-progress').css('width', progress + '%');
                    if (progress >= 100) {
                        clearInterval(interval);
                        $('#import-status').text(`Success!`).css('color', '#017C01');
                        $('#upload-import-btn')
                            .prop('disabled', false)
                            .css({
                                'margin-top': '20px',
                                'display': 'block',
                            })
                            .show();
                    }
                }, 200);
            });

            $('#upload-import-btn').on('click', function () {
                const fileInput = $('#import-file')[0];
                const file = fileInput.files[0];

                if (!file) {
                    console.error("No file selected.");
                    $('#import-status').text("No file selected. Please choose a file.").css('color', '#FF0000');
                    return;
                }

                const postId = wp.data.select('core/editor').getCurrentPostId();
                const formData = new FormData();
                formData.append('action', 'handle_import_csv');
                formData.append('file', file);
                formData.append('post_id', postId);
                formData.append('securekey' , smack_nonce_object.nonce),
                $('#upload-import-btn').prop('disabled', true).text('Uploading...').css({
                    'background-color': '#ccc',
                    'cursor': 'not-allowed',
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        try {
                            const data = JSON.parse(response) || response;
                            if (data.success || response.success) {
                                $('#smack-message').show().text(data.message).css('color', '#FFFFF');
                                $('#import-file').prop('disabled', true);
                                $('#import-file').next().hide();
                                $('#upload-import-btn').hide();
                                $('#clear-btn').show();
                                $('#import-status').hide();
                                $('#smack-message').append(`
                                    <p>
                                        <a href="${data.redirect_link}" target="_blank" style="color: #007cba; text-decoration: none; font-weight: bold;">
                                            Click here
                                        </a> for Redirect link
                                    </p>
                                 `);
                            } else if (!data.success || !response.success) {
                                $('#smack-imp-message').show().text(response.data.message || data.message).css('color', '#FFFFF');

                            } else {

                                $('#smack-imp-message').show().text("Something Went to wrong" + response.data.message || data.message).css('color', '#FFFFF');

                            }
                        } catch (e) {
                            console.error("Error parsing JSON response:", e);
                            $('#import-status').text("Upload Failed. Invalid response format.").css('color', '#FF0000');
                        } finally {
                            $('#upload-import-btn').prop('disabled', false).text('Upload Import').css({
                                'background-color': '#007cba',
                                'cursor': 'pointer',
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", error);
                        $('#import-status').text("Upload Failed. Please try again.").css('color', '#FF0000');
                        $('#upload-import-btn').prop('disabled', false).text('Upload Import').css({
                            'background-color': '#007cba',
                            'cursor': 'pointer',
                        });
                    }
                });
            });

            $('#export-btn').on('click', function () {
                const postId = wp.data.select('core/editor').getCurrentPostId();
                const postTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'handle_export_csv',
                        post_id: postId,
                        post_title: postTitle,
                        securekey : smack_nonce_object.nonce,

                    },
                    success: function (response) {
                        console.log('response', 'color: #0088cc', typeof (response));
                        try {
                            // Check if response is a string and parse it if needed
                            const data = (typeof response === 'string') ? JSON.parse(response) : response;

                            if (data.success) {
                                const filePath = data.file_path;
                                const fileName = filePath.split('/').pop();
                                const downloadLink = document.createElement('a');
                                downloadLink.href = filePath;
                                downloadLink.download = fileName;
                                document.body.appendChild(downloadLink);
                                downloadLink.click();
                                document.body.removeChild(downloadLink);
                            } else if (!data.success) {
                                $('#smack-exp-message').show().text(response.data.message || data.message).css('color', '#FFFFF');
                            } else {
                                $('#smack-exp-message').show().text(data.message || "Something went wrong").css('color', '#FFFFF');
                            }
                        } catch (e) {
                            console.error("Error parsing JSON response:", e);
                            $('#smack-exp-message').show().text("Error parsing response").css('color', '#FFFFF');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", error);
                    }
                });
            });


            $('#clear-btn').on('click', function () {
                $('#import-file').val('');
                $('#smack-message').text('')
                $('#import-status').text('');
                $('#smack-message').hide();
                $('#clear-btn').hide();
                $('#upload-import-btn').show();
                $('#import-file').prop('disabled', false);
            });

            const customStyles = `
                .import-export-btn:hover svg {
                    fill: #006799;
                }
                .tab:hover {
                    color: #007cba;
                    border-bottom: 2px solid #007cba;
                }
                .slider-header .close-slider:hover {
                    color: #007cba;
                }
            `;
            $('head').append(`<style>${customStyles}</style>`);
        }
    }

    const editorHeaderInterval = setInterval(checkEditorHeader, 500);
});

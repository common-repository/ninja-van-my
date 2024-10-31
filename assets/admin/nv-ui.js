jQuery(document).ready(function($){
    // On upload button click
    $('body').on('click', '#upload_image_button', function(e){
        e.preventDefault();

        var button = $(this);
        var imageId = $('#awb_seller_logo').val();

        var customUploader = wp.media({
            title: 'Upload Image',
            library : {
                type : 'image'
            },
            button: {
                text: 'Use this image'
            },
            multiple: false
        }).on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            var fileType = attachment.url.substring(attachment.url.lastIndexOf('.') + 1).toLowerCase();

            // Check if the file type is valid
            if (fileType == "png" || fileType == "jpg" || fileType == "jpeg") {
                $('#image_preview').attr('src', attachment.url).show();
                $('#image_container').show();
                button.hide();
                $('#awb_seller_logo').val(attachment.url);
                $('.text-warning.awb_seller_logo_notice').hide();
            } else {
                $('.text-warning.awb_seller_logo_notice').show();
            }
        });

        // already selected images
        customUploader.on('open', function() {
            if(imageId) {
                var selection = customUploader.state().get('selection');
                var attachment = wp.media.attachment(imageId);
                attachment.fetch();
                selection.add(attachment ? [attachment] : []);
            }
        });

        customUploader.open();
    });

    // On remove button click
    $('body').on('click', '#remove_image_button', function(e){
        e.preventDefault();
        $('#awb_seller_logo').val('');
        $('#image_container').hide();
        $('#upload_image_button').show();
        $('.text-warning.awb_seller_logo_notice').hide();
    });

    // Initialize state
    initialize_input();

    // Remove query string from URL
    if (window.location.search.indexOf('&save=true') > -1) {
        var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search.replace('&save=true', '') + window.location.hash;
        window.history.replaceState({}, document.title, newUrl);
    }

    // Get #orders-history-paged Tbody Tr count
    const tableHLen = $('#orders-history-paged tbody tr').length;

    var tableH = null;
    if (tableHLen > 1) {
        tableH = new DataTable('#orders-history-paged', {
            dom: 'iptip',
            pagingType: 'full',
            fixedHeader: true,
            scrollX: true,
            columnDefs: [
                { orderable: false, targets: [1,4,5,6,7] }
            ]
        });
    }

    $('.nav-tab-wrapper a').click(function(event) {
        if (!$(this).parent().hasClass('nv-setting-tabs') && !$(this).parent().hasClass('nv-setting-child-tabs')) {
            return;
        }
        event.preventDefault();
        var tab = $(this).attr('data-tab');

        $('.nav-tab-wrapper.nv-setting-tabs a').removeClass('nav-tab-active');
        if ($(this).parent().hasClass('nv-setting-tabs')) {
            $('.ninja_van_form').hide();
            $('#form-' + tab + '').show();
            if (tableH && tab == 'history') {
                tableH.draw();
            }
        } else if ($(this).parent().hasClass('nv-setting-child-tabs')) {
            $('.pickup_address_form').hide();
            $('#table-' + tab + '').show();
        }

        $(this).addClass('nav-tab-active');
    });

    $('#push_to_ninja').change(function(event) {
        if ($(this).is(':checked')) {
            $('.push_to_ninja_required').show();
        } else {
            $('.push_to_ninja_required').hide();
        }
    });

    $('#cash_on_delivery').change(function(event) {
        if ($(this).is(':checked')) {
            $('.cod_required').show();
        } else {
            $('.cod_required').hide();
        }
    });

    $('#international_shipment').change(function(event) {
        if ($(this).is(':checked')) {
            $('.international_shipment_required').show();
        } else {
            $('.international_shipment_required').hide();
        }
    });

    $('#pickup_required').change(function(event) {
        if ($(this).is(':checked')) {
            $('.pickup_required').show();
        } else {
            $('.pickup_required').hide();
        }
    });

    $('.nv-authorize').click(function(event) {
        event.preventDefault();
        const url = $('#client_auth').val();
        if (!url) {
            console.error('Ah boy, cheating ah?');
            return false;
        }

        // Redirect user to url specifed
        window.location.href = url;
        
        return true;
    });

    // On form submit, check if auth_mode is changed compare to the static value
    $('#ninja_van_form_submit').submit(function(e) {
        var auth_mode = $('#auth_mode').is(':checked'); // boolean
        var auth_mode_static = $('#auth_mode_static').val(); // string

        if (auth_mode) {
            auth_mode = 'PLUGIN';
        } else {
            auth_mode = 'DIRECT';
        }

        if (auth_mode_static && auth_mode != auth_mode_static) {
            var r = confirm("You have changed the authentication mode. This will reset your Ninja Van API credentials. Are you sure you want to continue?");
            if (r == false) {
                e.preventDefault();
            }
        }
    });

    $('#nv-my-webhook-sync').click(function(e) {
        e.preventDefault();
        if ($(this).attr('disabled')) {
            return;
        }
        if (!confirm('This will create and sync the webhook in your Ninja Van account. Are you sure you want to continue?')) {
            return;
        }
        console.log('ninja_van_my | Syncing webhook...');
        $.ajax({
            url: nv_my_admin_js.ajax_url,
            type: 'POST',
            data: {
                action: 'nv_my_sync_webhooks',
                nonce: nv_my_admin_js.nonce
            },
            success: function(response) {
                console.log(response);
                if (response.success) {
                    $('#nv-my-webhook-sync').text('Successfully Synced!');
                } else {
                    let text = 'Failed to Sync!';
                    if (response.data == 'Webhook already synced') {
                        text = 'Webhook already synced!';
                    }
                    $('#nv-my-webhook-sync').text(text);
                }
                
                setTimeout(function() {
                    $('#nv-my-webhook-sync').text('Sync Webhook');
                }, 5000);
            },
            error: function(response) {
                console.error(response);
                $('#nv-my-webhook-sync').text('Failed to Sync!');
                setTimeout(function() {
                    $('#nv-my-webhook-sync').text('Sync Webhook');
                }, 5000);
            }
        });
    });

    function initialize_input() {
        if ($('#push_to_ninja').is(':checked')) {
            $('.push_to_ninja_required').show();
        } else {
            $('.push_to_ninja_required').hide();
        }

        if ($('#cash_on_delivery').is(':checked')) {
            $('.cod_required').show();
        } else {
            $('.cod_required').hide();
        }

        if ($('#pickup_required').is(':checked')) {
            $('.pickup_required').show();
        } else {
            $('.pickup_required').hide();
        }

        if ($('#international_shipment').is(':checked')) {
            $('.international_shipment_required').show();
        } else {
            $('.international_shipment_required').hide();
        }
    }
});
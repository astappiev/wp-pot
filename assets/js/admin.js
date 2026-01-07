(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle module toggle
        $('.wp-pot-toggle input[type="checkbox"]').on('change', function() {
            const $checkbox = $(this);
            const $toggle = $checkbox.closest('.wp-pot-toggle');
            const slug = $checkbox.data('slug');

            if ($checkbox.is(':disabled')) {
                return;
            }

            // Add loading state
            $toggle.addClass('loading');

            // Send AJAX request
            $.ajax({
                url: wpPotAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_pot_toggle_module',
                    nonce: wpPotAdmin.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        // Update checkbox state
                        $checkbox.prop('checked', response.data.enabled);

                        // Show success message
                        showNotice(response.data.message, 'success');
                    } else {
                        // Revert checkbox state
                        $checkbox.prop('checked', !$checkbox.is(':checked'));

                        // Show error message
                        showNotice(response.data.message || 'An error occurred', 'error');
                    }
                },
                error: function() {
                    // Revert checkbox state
                    $checkbox.prop('checked', !$checkbox.is(':checked'));

                    // Show error message
                    showNotice('Failed to update module status', 'error');
                },
                complete: function() {
                    // Remove loading state
                    $toggle.removeClass('loading');
                }
            });
        });

        // Show admin notice
        function showNotice(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wp-pot-admin h1').after($notice);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);

            // Make dismissible
            $(document).trigger('wp-updates-notice-added');
        }
    });

})(jQuery);


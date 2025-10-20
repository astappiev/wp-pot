(function ($) {
	'use strict';

	$(document).ready(function () {
		let mediaUploader;

		// Upload avatar button
		$('#pot_local_avatars_upload_btn').on('click', function (e) {
			e.preventDefault();

			// If the uploader object has already been created, reopen the dialog
			if (mediaUploader) {
				mediaUploader.open();
				return;
			}

			// Create the media uploader
			mediaUploader = wp.media({
				title: 'Choose Avatar',
				button: {
					text: 'Use this image'
				},
				library: {
					type: 'image'
				},
				multiple: false
			});

			// When an image is selected, run a callback
			mediaUploader.on('select', function () {
				const attachment = mediaUploader.state().get('selection').first().toJSON();

				// Set the attachment ID
				$('#pot_local_avatar').val(attachment.id);

				// Update preview
				const imgUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				$('#pot_local_avatars_preview').html(
					'<img src="' + imgUrl + '" style="max-width: 150px; height: auto; border-radius: 50%;" />'
				);

				// Update button text and show remove button
				$('#pot_local_avatars_upload_btn').text('Change Avatar');
				if (!$('#pot_local_avatars_remove_btn').length) {
					$('#pot_local_avatars_upload_btn').after('<button type="button" class="button" id="pot_local_avatars_remove_btn">Remove Avatar</button>');
				}
			});

			// Open the uploader dialog
			mediaUploader.open();
		});

		// Remove avatar button (using event delegation since it might not exist on load)
		$(document).on('click', '#pot_local_avatars_remove_btn', function (e) {
			e.preventDefault();

			// Clear the field and preview
			$('#pot_local_avatar').val('');
			$('#pot_local_avatars_preview').html('');

			// Update button text and remove the remove button
			$('#pot_local_avatars_upload_btn').text('Upload Avatar');
			$(this).remove();
		});
	});
})(jQuery);

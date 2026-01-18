(function ($) {
	'use strict';

	$(document).ready(function () {
		let mediaUploader;

		$('#pot_local_avatars_upload_btn').on('click', function (e) {
			e.preventDefault();

			if (mediaUploader) {
				mediaUploader.open();
				return;
			}

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

			mediaUploader.on('select', function () {
				const attachment = mediaUploader.state().get('selection').first().toJSON();

				$('#pot_local_avatar').val(attachment.id);

				const imgUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				$('#pot_local_avatars_preview').html(
					'<img src="' + imgUrl + '" style="max-width: 150px; height: auto; border-radius: 50%;" />'
				);

				$(this).text('Change Avatar');
				if (!$('#pot_local_avatars_remove_btn').length) {
					$(this).after('<button type="button" class="button" id="pot_local_avatars_remove_btn">Remove Avatar</button>');
				}
			});

			mediaUploader.open();
		});

		// Remove avatar button (using event delegation since it might not exist on load)
		$(document).on('click', '#pot_local_avatars_remove_btn', function (e) {
			e.preventDefault();

			$('#pot_local_avatar').val('');
			$('#pot_local_avatars_preview').html('');
			$('#pot_local_avatars_upload_btn').text('Upload Avatar');
			$(this).remove();
		});
	});
})(jQuery);

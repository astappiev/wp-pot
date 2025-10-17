/**
 * Author: WP Zone
 * License: GNU General Public License version 3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

function pot_media_replace() {

	frame = wp.media({
		title: "Choose Replacement Image",
		button: {
			text: "Replace Image"
		},
		multiple: false
	});

	frame.on("select", function () {
		jQuery("#pot_media_replace_with_fld").val(frame.state().get("selection").first().toJSON().id);
		if (jQuery("#pot_media_replace_with_fld").closest('.media-modal').length) {
			jQuery("#pot_media_replace_with_fld").change();
			var saveStatusInterval = setInterval(function () {
				if (jQuery("#pot_media_replace_with_fld").closest('.attachment-details.save-ready').length) {
					clearInterval(saveStatusInterval);
					location.reload();
				}
			}, 250);
		} else {
			jQuery("#pot_media_replace_with_fld").closest("form").submit();
		}

	});

	var frameEl = jQuery(frame.open().el);
	frameEl.find('.media-router > a:first-child').click();

}

function pot_media_replace() {

	const frame = wp.media({
		title: "Choose Replacement Image",
		button: {
			text: "Replace Image"
		},
		multiple: false
	});

	frame.on("select", function () {
		const replaceWithEl = jQuery("#pot_media_replace_with_fld");
		replaceWithEl.val(frame.state().get("selection").first().toJSON().id);
		if (replaceWithEl.closest('.media-modal').length) {
			replaceWithEl.change();
			const saveStatusInterval = setInterval(function () {
				if (replaceWithEl.closest('.attachment-details.save-ready').length) {
					clearInterval(saveStatusInterval);
					location.reload();
				}
			}, 250);
		} else {
			replaceWithEl.closest("form").submit();
		}
	});

	const frameEl = jQuery(frame.open().el);
	frameEl.find('.media-router > a:first-child').click();

}

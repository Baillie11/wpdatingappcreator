/**
 * Dating Site Builder - Admin JavaScript
 */

(function($) {
	'use strict';

	const DSB_Admin = {
		init: function() {
			this.wizard();
			this.adminActions();
			this.logoUploader();
		},

		/**
		 * Site Logo media-library picker for the setup wizard.
		 */
		logoUploader: function() {
			let frame;

			$(document).on('click', '#dsb-logo-upload-btn', function(e) {
				e.preventDefault();

				if (typeof wp === 'undefined' || !wp.media) {
					return;
				}

				if (frame) {
					frame.open();
					return;
				}

				const opts = (typeof dsbAdmin !== 'undefined') ? dsbAdmin : {};

				frame = wp.media({
					title: opts.logo_modal_title || 'Choose Site Logo',
					library: { type: 'image' },
					button: { text: opts.logo_modal_btn || 'Use this logo' },
					multiple: false
				});

				frame.on('select', function() {
					const attachment = frame.state().get('selection').first().toJSON();
					const $input = $('#dsb_site_logo');
					const $preview = $('#dsb-logo-preview');
					const $remove = $('#dsb-logo-remove-btn');
					const $uploadBtn = $('#dsb-logo-upload-btn');

					let src = attachment.url;
					if (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) {
						src = attachment.sizes.medium.url;
					}

					$input.val(attachment.id);
					$preview.html(
						'<img src="' + src + '" alt="" style="max-width:200px;max-height:200px;display:block;margin-bottom:8px;">' 
					).show();
					$remove.show();
					$uploadBtn.text('Change Logo');
				});

				frame.open();
			});

			$(document).on('click', '#dsb-logo-remove-btn', function(e) {
				e.preventDefault();
				$('#dsb_site_logo').val('0');
				$('#dsb-logo-preview').empty().hide();
				$(this).hide();
				$('#dsb-logo-upload-btn').text('Choose / Upload Logo');
			});
		},

		wizard: function() {
			let clickedButtonValue = 'next';
			
			// Track which submit button was clicked
			$(document).on('click', '#dsb-wizard-form button[type="submit"]', function() {
				clickedButtonValue = $(this).val() || 'next';
			});
			
			// Intercept wizard form submission
			$(document).on('submit', '#dsb-wizard-form', function(e) {
				e.preventDefault();
				
				const $form = $(this);
				
				// Disable submit buttons and show loading state
				$form.find('button[type="submit"]').prop('disabled', true).css('opacity', '0.7');
				
				// Build form data with action type
				const formData = $form.serialize() + '&action=dsb_save_wizard_step&action_type=' + clickedButtonValue;
				
				$.post(ajaxurl, formData, function(response) {
					if (response.success && response.data && response.data.redirect) {
						window.location.href = response.data.redirect;
					} else {
						$form.find('button[type="submit"]').prop('disabled', false).css('opacity', '1');
						const errorMsg = (response.data && response.data.message) ? response.data.message : 'Unknown error occurred';
						alert('Error: ' + errorMsg);
					}
				}).fail(function(xhr, status, error) {
					$form.find('button[type="submit"]').prop('disabled', false).css('opacity', '1');
					console.error('AJAX Error:', xhr.responseText);
					alert('AJAX Error: ' + error + '. Check console for details.');
				});
			});
		},

		adminActions: function() {
			// Approve profile
			$('.dsb-approve-profile').on('click', function(e) {
				e.preventDefault();
				const userId = $(this).data('user-id');
				
				$.post(ajaxurl, {
					action: 'dsb_approve_profile',
					user_id: userId
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + (response.data.message || 'Unknown error'));
					}
				});
			});

			// Ban user
			$('.dsb-ban-user').on('click', function(e) {
				e.preventDefault();
				
				if (!confirm('Are you sure you want to ban this user?')) {
					return;
				}
				
				const userId = $(this).data('user-id');
				
				$.post(ajaxurl, {
					action: 'dsb_ban_user',
					user_id: userId
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + (response.data.message || 'Unknown error'));
					}
				});
			});

			// Resolve report
			$('.dsb-resolve-report').on('click', function(e) {
				e.preventDefault();
				const reportId = $(this).data('report-id');
				
				$.post(ajaxurl, {
					action: 'dsb_resolve_report',
					report_id: reportId
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + (response.data.message || 'Unknown error'));
					}
				});
			});
		}
	};

	$(document).ready(function() {
		DSB_Admin.init();
	});

})(jQuery);

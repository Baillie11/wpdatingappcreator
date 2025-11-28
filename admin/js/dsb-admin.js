/**
 * Dating Site Builder - Admin JavaScript
 */

(function($) {
	'use strict';

	const DSB_Admin = {
		init: function() {
			this.wizard();
			this.adminActions();
		},

		wizard: function() {
			// Wizard navigation
			$('.dsb-wizard-next').on('click', function(e) {
				e.preventDefault();
				const currentStep = $('.dsb-wizard-step.active').data('step');
				DSB_Admin.saveWizardStep(currentStep, function() {
					DSB_Admin.goToStep(currentStep + 1);
				});
			});

			$('.dsb-wizard-prev').on('click', function(e) {
				e.preventDefault();
				const currentStep = $('.dsb-wizard-step.active').data('step');
				DSB_Admin.goToStep(currentStep - 1);
			});

			$('.dsb-wizard-finish').on('click', function(e) {
				e.preventDefault();
				const currentStep = $('.dsb-wizard-step.active').data('step');
				DSB_Admin.saveWizardStep(currentStep, function() {
					DSB_Admin.completeWizard();
				});
			});
		},

		saveWizardStep: function(step, callback) {
			const $form = $('#dsb-wizard-form-' + step);
			if ($form.length === 0) {
				if (callback) callback();
				return;
			}

			const formData = $form.serialize() + '&action=dsb_save_wizard_step&step=' + step;
			
			$.post(ajaxurl, formData, function(response) {
				if (response.success) {
					if (callback) callback();
				} else {
					alert('Error saving step: ' + (response.data.message || 'Unknown error'));
				}
			});
		},

		goToStep: function(step) {
			// Hide all step contents
			$('.dsb-wizard-step-content').hide();
			$('#dsb-wizard-step-' + step).show();

			// Update progress indicators
			$('.dsb-wizard-step').each(function() {
				const $step = $(this);
				const stepNum = $step.data('step');
				
				$step.removeClass('active completed');
				if (stepNum < step) {
					$step.addClass('completed');
				} else if (stepNum === step) {
					$step.addClass('active');
				}
			});

			// Update button visibility
			if (step === 1) {
				$('.dsb-wizard-prev').hide();
			} else {
				$('.dsb-wizard-prev').show();
			}

			const totalSteps = $('.dsb-wizard-step').length;
			if (step === totalSteps) {
				$('.dsb-wizard-next').hide();
				$('.dsb-wizard-finish').show();
			} else {
				$('.dsb-wizard-next').show();
				$('.dsb-wizard-finish').hide();
			}
		},

		completeWizard: function() {
			$.post(ajaxurl, {
				action: 'dsb_complete_wizard'
			}, function(response) {
				if (response.success) {
					window.location.reload();
				}
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

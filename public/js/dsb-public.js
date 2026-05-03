/**
 * Dating Site Builder - Frontend JavaScript
 * Handles AJAX for forms, messaging, likes, photo uploads
 */

(function($) {
	'use strict';

	const DSB = {
		/**
		 * Initialize all functionality
		 */
		init: function() {
			this.registerForm();
			this.loginForm();
			this.forgotPasswordForm();
			this.profileEditForm();
			this.photoUpload();
			this.likes();
			this.messaging();
			this.blockUser();
			this.reportUser();
			this.filters();
			this.groupChat();
			this.logoSpin();
		},

		/**
		 * Spin the header logo when clicked, then follow the link.
		 *
		 * Works for both an uploaded image (.dsb-app-logo-img) and the
		 * fallback emoji icon (.dsb-app-logo-icon). The link still
		 * navigates to the home / browse page; we just defer the
		 * navigation until the spin animation finishes so the user
		 * actually sees it.
		 */
		logoSpin: function() {
			$(document).on('click', '.dsb-app-logo', function(e) {
				var $link = $(this);
				var $target = $link.find('.dsb-app-logo-img, .dsb-app-logo-icon').first();
				if (!$target.length) {
					return; // No spinable element - let the link behave normally.
				}
				// Honour modifier keys / non-primary mouse buttons so users can
				// still middle-click / Cmd-click the logo to open in a new tab.
				if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || (e.which && e.which !== 1)) {
					return;
				}
				if ($target.hasClass('is-spinning')) {
					return; // Already spinning, let the previous click finish.
				}
				var href = $link.attr('href');
				if (!href || href === '#') {
					return;
				}
				e.preventDefault();
				$target.addClass('is-spinning');
				var navigated = false;
				var go = function() {
					if (navigated) {
						return;
					}
					navigated = true;
					window.location.href = href;
				};
				$target.one('animationend webkitAnimationEnd', go);
				// Fallback in case the animationend event doesn't fire
				// (e.g. element removed, prefers-reduced-motion).
				setTimeout(go, 700);
			});
		},

		/**
		 * Show message to user
		 */
		showMessage: function($container, message, type) {
			$container.removeClass('success error').addClass(type).html(message).slideDown();
			// Auto-hide success messages, keep errors visible longer
			const hideDelay = type === 'error' ? 10000 : 5000;
			setTimeout(function() {
				$container.slideUp();
			}, hideDelay);
		},

		/**
		 * Registration form AJAX
		 */
		registerForm: function() {
			$('#dsb-register-form').on('submit', function(e) {
				e.preventDefault();
				
				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $message = $form.find('.dsb-form-message');
				
				// Validate password match
				const password = $('#reg_password').val();
				const passwordConfirm = $('#reg_password_confirm').val();
				
				if (password !== passwordConfirm) {
					DSB.showMessage($message, 'Passwords do not match', 'error');
					return;
				}
				
				// Disable submit button
				$submit.prop('disabled', true).html('<span class="dsb-loading"></span> Creating account...');
				
				// Prepare data
				const formData = {
					action: 'dsb_register_user',
					nonce: $form.find('[name="dsb_register_nonce"]').val(),
					username: $('#reg_username').val(),
					email: $('#reg_email').val(),
					password: password,
					display_name: $('#reg_display_name').val()
				};
				
				// Submit via AJAX
				$.post(dsbPublic.ajaxurl, formData, function(response) {
					if (response.success) {
						DSB.showMessage($message, response.data.message, 'success');
						setTimeout(function() {
							const target = response.data && response.data.redirect_url;
							if (typeof target === 'string' && target.length > 0) {
								window.location.href = target;
							} else {
								window.location.href = '/';
							}
						}, 1000);
					} else {
						DSB.showMessage($message, response.data.message, 'error');
						$submit.prop('disabled', false).text('Create Account');
					}
				}).fail(function() {
					DSB.showMessage($message, 'An error occurred. Please try again.', 'error');
					$submit.prop('disabled', false).text('Create Account');
				});
			});
		},

		/**
		 * Login form AJAX
		 */
		loginForm: function() {
			$('#dsb-login-form').on('submit', function(e) {
				e.preventDefault();
				
				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $message = $form.find('.dsb-form-message');
				
				// Disable submit button
				$submit.prop('disabled', true).html('<span class="dsb-loading"></span> Logging in...');
				
				// Prepare data
				const formData = {
					action: 'dsb_login_user',
					nonce: $form.find('[name="dsb_login_nonce"]').val(),
					username: $('#login_username').val(),
					password: $('#login_password').val(),
					remember: $('[name="remember"]').is(':checked') ? 1 : 0
				};
				
				// Submit via AJAX
				$.post(dsbPublic.ajaxurl, formData, function(response) {
					if (response.success) {
						DSB.showMessage($message, response.data.message, 'success');
						setTimeout(function() {
							const target = response.data && response.data.redirect_url;
							if (typeof target === 'string' && target.length > 0) {
								window.location.href = target;
							} else {
								window.location.href = '/';
							}
						}, 1000);
					} else {
						DSB.showMessage($message, response.data.message, 'error');
						$submit.prop('disabled', false).text('Login');
					}
				}).fail(function() {
					DSB.showMessage($message, 'An error occurred. Please try again.', 'error');
					$submit.prop('disabled', false).text('Login');
				});
			});
		},

		/**
		 * Forgot password form AJAX
		 */
		forgotPasswordForm: function() {
			$('#dsb-forgot-password-form').on('submit', function(e) {
				e.preventDefault();
				
				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $message = $form.find('.dsb-form-message');
				
				// Disable submit button
				$submit.prop('disabled', true).html('<span class="dsb-loading"></span> Sending...');
				
				// Prepare data
				const formData = {
					action: 'dsb_forgot_password',
					nonce: $form.find('[name="dsb_forgot_password_nonce"]').val(),
					email: $('#forgot_email').val()
				};
				
				// Submit via AJAX
				$.post(dsbPublic.ajaxurl, formData, function(response) {
					if (response.success) {
						DSB.showMessage($message, response.data.message, 'success');
						$submit.prop('disabled', false).text('Send Reset Link');
						$('#forgot_email').val(''); // Clear field
					} else {
						DSB.showMessage($message, response.data.message, 'error');
						$submit.prop('disabled', false).text('Send Reset Link');
					}
				}).fail(function() {
					DSB.showMessage($message, 'An error occurred. Please try again.', 'error');
					$submit.prop('disabled', false).text('Send Reset Link');
				});
			});
		},

		/**
		 * Profile edit form AJAX
		 */
		profileEditForm: function() {
			$('#dsb-profile-edit-form').on('submit', function(e) {
				e.preventDefault();
				
				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $message = $form.find('.dsb-form-message');
				
				// Hide any existing message
				$message.slideUp();
				
				// Disable submit button
				$submit.prop('disabled', true).html('<span class="dsb-loading"></span> Saving...');
				
				// Prepare data - serialize form and add action
				const formData = $form.serialize() + '&action=dsb_update_profile';
				
				// Submit via AJAX
				$.post(dsbPublic.ajaxurl, formData, function(response) {
					if (response.success) {
						DSB.showMessage($message, response.data.message, 'success');
						// Scroll to top of form to show success message
						$('html, body').animate({
							scrollTop: $form.offset().top - 100
						}, 300);
					} else {
						const errorMsg = response.data && response.data.message 
							? response.data.message 
							: 'An error occurred while saving your profile.';
						DSB.showMessage($message, errorMsg, 'error');
						// Scroll to error message
						$('html, body').animate({
							scrollTop: $message.offset().top - 100
						}, 300);
					}
					$submit.prop('disabled', false).text('Save Profile');
				}).fail(function(xhr, status, error) {
					let errorMsg = 'An error occurred. Please try again.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}
					DSB.showMessage($message, errorMsg, 'error');
					$submit.prop('disabled', false).text('Save Profile');
				});
			});
		},

		/**
		 * Photo upload and management
		 */
		photoUpload: function() {
			// Photo upload
			$('#dsb-photo-upload').on('change', function() {
				const files = this.files;
				if (files.length === 0) return;
				
				const $grid = $('#dsb-photo-grid');
				const formData = new FormData();
				
				formData.append('action', 'dsb_upload_photo');
				formData.append('nonce', dsbPublic.nonce);
				
				// Upload each file
				for (let i = 0; i < files.length; i++) {
					if (i >= 10) break; // Max 10 photos
					
					const fileFormData = new FormData();
					fileFormData.append('action', 'dsb_upload_photo');
					fileFormData.append('nonce', dsbPublic.nonce);
					fileFormData.append('photo', files[i]);
					
					$.ajax({
						url: dsbPublic.ajaxurl,
						type: 'POST',
						data: fileFormData,
						processData: false,
						contentType: false,
						success: function(response) {
							if (response.success) {
								location.reload(); // Reload to show new photo
							} else {
								alert(response.data.message || 'Upload failed');
							}
						}
					});
				}
				
				// Clear file input
				$(this).val('');
			});
			
			// Delete photo
			$(document).on('click', '.dsb-delete-photo', function(e) {
				e.preventDefault();
				
				if (!confirm(dsbPublic.strings.confirm_delete)) {
					return;
				}
				
				const $button = $(this);
				const index = $button.data('index');
				
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_delete_photo',
					nonce: dsbPublic.nonce,
					index: index
				}, function(response) {
					if (response.success) {
						location.reload();
					}
				});
			});
			
			// Set main photo
			$(document).on('click', '.dsb-set-main-photo', function(e) {
				e.preventDefault();
				
				const $button = $(this);
				const index = $button.data('index');
				
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_set_main_photo',
					nonce: dsbPublic.nonce,
					index: index
				}, function(response) {
					if (response.success) {
						location.reload();
					}
				});
			});
		},

		/**
		 * Like/Unlike functionality
		 */
		likes: function() {
			$(document).on('click', '.dsb-like-btn', function(e) {
				e.preventDefault();

				const $button = $(this);
				const userId = $button.data('user-id');
				const likedHint = 'You\'ve liked this member. Click to remove your like.';
				const notLikedHint = 'Like this member to let them know you\'re interested. If they like you back it\'s a match!';

				function setLikedState($btn, liked) {
					const $label = $btn.find('.dsb-btn-label');
					if (liked) {
						$btn.addClass('liked');
						if ($label.length) {
							$label.text('Liked');
						} else {
							$btn.find('span').last().text('Liked');
						}
						$btn.attr('title', likedHint).attr('aria-label', likedHint);
					} else {
						$btn.removeClass('liked');
						if ($label.length) {
							$label.text('Like');
						} else {
							$btn.find('span').last().text('Like');
						}
						$btn.attr('title', notLikedHint).attr('aria-label', notLikedHint);
					}
				}

				// Optimistic UI update
				const optimisticLiked = !$button.hasClass('liked');
				setLikedState($button, optimisticLiked);

				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_toggle_like',
					nonce: dsbPublic.nonce,
					user_id: userId
				}, function(response) {
					if (response.success) {
						setLikedState($button, !!response.data.liked);

						// Show mutual match notification
						if (response.data.mutual_match) {
							alert('It\'s a match! You both liked each other!');
						}
					} else {
						// Revert UI update on error
						setLikedState($button, !optimisticLiked);
					}
				});
			});
		},

		/**
		 * Messaging functionality
		 */
		messaging: function() {
			const $wrapper = $('.dsb-conversation-wrapper');
			if ($wrapper.length === 0) return;
			
			const otherUserId = $wrapper.data('user-id');
			let lastMessageId = 0;
			
			// Get last message ID
			const $lastMessage = $('.dsb-message').last();
			if ($lastMessage.length) {
				lastMessageId = $lastMessage.data('message-id') || 0;
			}
			
			// Send message
			$('#dsb-send-message-form').on('submit', function(e) {
				e.preventDefault();
				
				const $form = $(this);
				const $textarea = $('#dsb-message-input');
				const $button = $form.find('button[type="submit"]');
				const message = $textarea.val().trim();
				
				if (message === '') return;
				
				// Disable send button
				$button.prop('disabled', true);
				
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_send_message',
					nonce: dsbPublic.messaging_nonce,
					receiver_id: otherUserId,
					message: message
				}, function(response) {
					if (response.success) {
						// Clear textarea
						$textarea.val('');
						
						// Add message to conversation
						const messageHtml = `
							<div class="dsb-message sent" data-message-id="${response.data.message_id}">
								<div class="dsb-message-content">${escapeHtml(message)}</div>
								<div class="dsb-message-time">Just now</div>
							</div>
						`;
						$('#dsb-conversation-messages').append(messageHtml);
						
						// Scroll to bottom
						DSB.scrollToBottom();
						
						// Update last message ID
						lastMessageId = response.data.message_id;
					} else {
						alert(response.data.message || 'Failed to send message');
					}
					
					$button.prop('disabled', false);
				});
			});
			
			// Poll for new messages every 5 seconds
			if (otherUserId) {
				setInterval(function() {
					$.post(dsbPublic.ajaxurl, {
						action: 'dsb_get_messages',
						nonce: dsbPublic.messaging_nonce,
						other_user_id: otherUserId,
						last_message_id: lastMessageId
					}, function(response) {
						if (response.success && response.data.messages.length > 0) {
							response.data.messages.forEach(function(msg) {
								const isSent = msg.sender_id == dsbPublic.current_user_id;
								const messageClass = isSent ? 'sent' : 'received';
								const timeAgo = msg.time_ago || 'Just now';
								
								const messageHtml = `
									<div class="dsb-message ${messageClass}" data-message-id="${msg.id}">
										<div class="dsb-message-content">${escapeHtml(msg.message_text)}</div>
										<div class="dsb-message-time">${timeAgo}</div>
									</div>
								`;
								$('#dsb-conversation-messages').append(messageHtml);
								
								// Update last message ID
								lastMessageId = msg.id;
							});
							
							// Scroll to bottom
							DSB.scrollToBottom();
							
							// Mark as read
							$.post(dsbPublic.ajaxurl, {
								action: 'dsb_mark_read',
								nonce: dsbPublic.messaging_nonce,
								other_user_id: otherUserId
							});
						}
					});
				}, 5000);
			}
		},

		/**
		 * Scroll conversation to bottom
		 */
		scrollToBottom: function() {
			const $messages = $('#dsb-conversation-messages');
			$messages.scrollTop($messages[0].scrollHeight);
		},

		/**
		 * Block user
		 */
		blockUser: function() {
			$(document).on('click', '.dsb-block-user-btn', function(e) {
				e.preventDefault();
				
				if (!confirm(dsbPublic.strings.confirm_block)) {
					return;
				}
				
				const userId = $(this).data('user-id');
				
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_block_user',
					nonce: dsbPublic.messaging_nonce,
					blocked_user_id: userId
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
						// Redirect away from profile/conversation
						window.location.href = window.location.origin;
					} else {
						alert(response.data.message || 'Failed to block user');
					}
				});
			});
		},

		/**
		 * Report user
		 */
		reportUser: function() {
			$(document).on('click', '.dsb-report-btn', function(e) {
				e.preventDefault();
				
				const userId = $(this).data('user-id');
				const reason = prompt('Please provide a reason for reporting this user:');
				
				if (!reason || reason.trim() === '') {
					return;
				}
				
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_report_user',
					nonce: dsbPublic.messaging_nonce,
					reported_user_id: userId,
					reason: reason,
					type: 'user'
				}, function(response) {
					if (response.success) {
						alert(response.data.message);
					} else {
						alert(response.data.message || 'Failed to submit report');
					}
				});
			});
		},

		/**
		 * Member directory filters
		 */
		filters: function() {
			$('#dsb-apply-filters').on('click', function(e) {
				e.preventDefault();
				
				// Get filter values
				const gender = $('#dsb-filter-gender').val();
				const ageMin = $('#dsb-filter-age-min').val();
				const ageMax = $('#dsb-filter-age-max').val();
				const location = $('#dsb-filter-location').val();
				
				// Build query string
				let url = window.location.pathname;
				const params = [];
				
				if (gender) params.push('gender=' + encodeURIComponent(gender));
				if (ageMin) params.push('age_min=' + encodeURIComponent(ageMin));
				if (ageMax) params.push('age_max=' + encodeURIComponent(ageMax));
				if (location) params.push('location=' + encodeURIComponent(location));
				
				if (params.length > 0) {
					url += '?' + params.join('&');
				}
				
				// Redirect with filters
				window.location.href = url;
			});
			
			// Clear filters on Enter key in filter inputs
			$('.dsb-filter').on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					$('#dsb-apply-filters').click();
				}
			});
		},

		/**
		 * Group chat functionality
		 */
		groupChat: function() {
			const $chatContainer = $('#dsb-chat-messages');
			if ($chatContainer.length === 0) return;
			
			let lastMessageId = 0;
			let isPolling = true;
			let pollInterval = null;
			let onlineInterval = null;
			
			// Load initial messages
			loadMessages();
			
			// Load online users
			loadOnlineUsers();
			
			// Start polling for new messages
			pollInterval = setInterval(function() {
				if (isPolling) {
					loadMessages();
				}
			}, 3000); // Poll every 3 seconds
			
			// Update online users every 30 seconds
			onlineInterval = setInterval(loadOnlineUsers, 30000);
			
			// Send message form
			$('#dsb-group-chat-form').on('submit', function(e) {
				e.preventDefault();
				
				const $input = $('#dsb-chat-input');
				const $button = $('#dsb-chat-send');
				const message = $input.val().trim();
				
				if (message === '') return;
				
				$button.prop('disabled', true);
				
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_group_chat_send',
					nonce: dsbPublic.group_chat_nonce,
					message: message
				}, function(response) {
					if (response.success) {
						$input.val('');
						
						// Add message immediately for instant feedback
						appendMessage({
							id: response.data.message_id,
							user_id: response.data.user_id,
							username: response.data.username,
							avatar: response.data.avatar,
							message: response.data.message,
							time: response.data.time,
							is_own: true
						});
						
						lastMessageId = response.data.message_id;
						scrollToBottom();
					} else {
						alert(response.data.message || 'Failed to send message');
					}
					$button.prop('disabled', false);
				}).fail(function() {
					alert('Failed to send message. Please try again.');
					$button.prop('disabled', false);
				});
			});
			
			// Allow Enter key to send (Shift+Enter for new line in future)
			$('#dsb-chat-input').on('keypress', function(e) {
				if (e.which === 13 && !e.shiftKey) {
					e.preventDefault();
					$('#dsb-group-chat-form').submit();
				}
			});
			
			/**
			 * Load messages from server
			 */
			function loadMessages() {
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_group_chat_get',
					nonce: dsbPublic.group_chat_nonce,
					last_id: lastMessageId,
					limit: lastMessageId === 0 ? 50 : 20
				}, function(response) {
					if (response.success) {
						const messages = response.data.messages;
						
						// Remove loading indicator on first load
						if (lastMessageId === 0) {
							$chatContainer.find('.dsb-chat-loading').remove();
							
							if (messages.length === 0) {
								$chatContainer.html('<div class="dsb-no-messages" style="text-align:center;padding:40px;color:#6b7280;">No messages yet. Be the first to say hello! 👋</div>');
							}
						}
						
						// Append new messages
						messages.forEach(function(msg) {
							// Skip if message already exists
							if ($chatContainer.find('[data-message-id="' + msg.id + '"]').length === 0) {
								// Remove "no messages" placeholder
								$chatContainer.find('.dsb-no-messages').remove();
								appendMessage(msg);
							}
						});
						
						// Update last message ID
						if (response.data.last_id > lastMessageId) {
							lastMessageId = response.data.last_id;
						}
						
						// Scroll to bottom on initial load
						if (messages.length > 0 && lastMessageId === response.data.last_id) {
							scrollToBottom();
						}
					}
				});
			}
			
			/**
			 * Append a message to the chat
			 */
			function appendMessage(msg) {
				const ownClass = msg.is_own ? ' own' : '';
				const profileUrl = msg.profile_url || '#';
				
				const html = `
					<div class="dsb-chat-message${ownClass}" data-message-id="${msg.id}">
						<div class="dsb-chat-message-avatar">
							<a href="${escapeHtml(profileUrl)}">
								<img src="${escapeHtml(msg.avatar)}" alt="${escapeHtml(msg.username)}">
							</a>
						</div>
						<div class="dsb-chat-message-content">
							<div class="dsb-chat-message-header">
								<span class="dsb-chat-message-username">
									<a href="${escapeHtml(profileUrl)}">${escapeHtml(msg.username)}</a>
								</span>
								<span class="dsb-chat-message-time">${escapeHtml(msg.time)}</span>
							</div>
							<div class="dsb-chat-message-bubble">${escapeHtml(msg.message)}</div>
						</div>
					</div>
				`;
				
				$chatContainer.append(html);
			}
			
			/**
			 * Load online users
			 */
			function loadOnlineUsers() {
				$.post(dsbPublic.ajaxurl, {
					action: 'dsb_group_chat_online',
					nonce: dsbPublic.group_chat_nonce
				}, function(response) {
					if (response.success) {
						const users = response.data.users;
						const count = response.data.count;
						
						// Update online count
						$('#dsb-online-count').text(count);
						
						// Update online users list
						const $onlineList = $('#dsb-online-users');
						$onlineList.empty();
						
						if (users.length === 0) {
							$onlineList.html('<div class="dsb-no-online-users">No members online</div>');
						} else {
							users.forEach(function(user) {
								$onlineList.append(`
									<a href="#" class="dsb-online-user">
										<img src="${escapeHtml(user.avatar)}" alt="${escapeHtml(user.username)}" class="dsb-online-user-avatar">
										<span class="dsb-online-user-name">${escapeHtml(user.username)}</span>
									</a>
								`);
							});
						}
					}
				});
			}
			
			/**
			 * Scroll chat to bottom
			 */
			function scrollToBottom() {
				$chatContainer.scrollTop($chatContainer[0].scrollHeight);
			}
		}
	};

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		DSB.init();
		
		// Scroll to bottom of messages on load
		if ($('#dsb-conversation-messages').length) {
			DSB.scrollToBottom();
		}
	});

})(jQuery);

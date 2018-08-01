/**
 * Reviews Admin JS
 */
if (typeof (jQuery) != 'undefined') {
	(function( $ ) {
		"use strict";

		$(function() {
			var row = $('#replyrow');

			$('a[data-edd-reviews-action="reply"]').on('click', function(e) {
				e.preventDefault();

				var tableRow = $(this).parents('tr');
				var review_id = $(tableRow).data('review-id');
				var editRow = $('#replyrow');
				var status = tableRow.data('review-status');

				$('#review_ID', editRow).val(review_id);
				$('#status', editRow).val(status)

				$('#edd-review-' + review_id).after(editRow);
				$('#replyrow').fadeIn(300, function(){
					$(this).show();
				});
			});

			$('a.cancel', row).on('click', function(e) {
				$('#replyrow').fadeOut('fast', function(){
					row.hide();
					$('#com-reply').append(row);
					$('#replycontent').css('height', '').val('');
					$('.error', row).empty().hide();
					$('.spinner', row).removeClass('is-active');
				});
				e.preventDefault();
			});

			$('a.save', row).on('click', function(e) {
				e.preventDefault();

				$('#replysubmit .error').hide();
				$('#replysubmit .spinner').addClass('is-active');

				var data = {
					content: $('#replycontent').val(),
					_nonce: edd_reviews_admin_params.security
				};

				$('#replyrow input').not(':button').each(function() {
					var t = $(this);
					data[ t.attr('name') ] = t.val();
				});

				data.action = 'edd_reviews_reply_to_review';

				if ($(this).parents('table').hasClass('edd-reviews-box')) {
					data.metabox = true;
				}

				$.ajax({
					type : 'POST',
					url : ajaxurl,
					data : data,
					success : function (response) {
						var res = wpAjax.parseAjaxResponse(response, 'result');

						if (res) {
							var reply = res.responses[0];

							var r = $.trim(reply.data);
							$(r).hide();
							$('#replyrow').after(r);

							$('#replyrow').fadeOut('fast', function() {
								row.hide();
								$('#com-reply').append(row);
								$('#replycontent').css('height', '').val('');
								$('.error', row).empty().hide();
								$('.spinner', row).removeClass('is-active');
							})
						}
					},
					error : function (error) {
						console.log(erorr);
					}
				});

				return false;
			});

			$('#edd-reviews .row-actions a[data-edd-reviews-action!="reply"], .edd-reviews-box .row-actions a[data-edd-reviews-action!="reply"]').on('click', function(e) {
				var row = $(this).parents('tr');
				var status = $(this).data('edd-reviews-action');

				var review_id = $(row).data('review-id');

				var data = {
					action: 'edd_reviews_change_status',
					status: status,
					_nonce: edd_reviews_admin_params.security,
					review_id: review_id
				};

				$.ajax({
					type: "POST",
					data: data,
					url: ajaxurl,
					success: function (response) {
						var new_status = response.new_status;

						if (new_status == 1) {
							row.children('td').animate( { 'backgroundColor': '#FFFFE0' }, 150, function() {
								$(this).animate( { 'backgroundColor':'#CCEEBB' }, 150, function() {
									$(this).animate( { 'backgroundColor':'none' }, 150 );
								} )
							});
							row.removeClass('edd-review-unapproved').addClass('edd-review-approved');
						}

						if (new_status == 0) {
							row.children('td').animate( { 'backgroundColor': '#FFFFE0' }, 150, function() {
								$(this).animate( { 'backgroundColor':'#FeF7F1' }, 150 );
							});
							row.removeClass('edd-review-approved').addClass('edd-review-unapproved');
						}
					},
					error: function(error) {
						console.log(error);
					}
				});
				e.preventDefault();
			})
		});
	}(jQuery));
}
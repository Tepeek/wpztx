/**
 * Reviews JS
 */
if (typeof (jQuery) != 'undefined') {

	(function( $ ) {
		"use strict";

		$(function() {

			var EDD_Reviews = {
				init: function () {
					this.show();
					this.remove();
					this.ratings();
					this.votes();
					this.reply();
				},

				show: function() {
					$('.edd-review-vote').show();
				},

				remove: function () {
					$('.edd_show_if_no_js').remove();
				},

				ratings: function () {
					$('.edd-reviews-stars-empty .edd-reviews-star-rating').on('hover', function() {
						$(this).toggleClass('dashicons-star-empty').toggleClass('dashicons-star-filled');
						$(this).prevAll().toggleClass('dashicons-star-empty').toggleClass('dashicons-star-filled');
						return false;
					});

					$('.edd-reviews-star-rating').on('click', function() {
						$('.edd-reviews-stars-filled').width( $(this).attr('data-rating') * 25 )
						$('input#edd-reviews-star-rating').val($(this).attr('data-rating'));
					});
				},

				votes: function() {
					$('span.edd-reviews-voting-buttons a').on('click', function() {
						var $this = $(this),
							vote = $this.data('edd-reviews-vote'),
							data = {
								action: 'edd_reviews_process_vote',
								security: edd_reviews_params.edd_voting_nonce,
								review_vote: vote,
								comment_id: $this.data('edd-reviews-comment-id'),
								edd_reviews_ajax: true
							};

						$this.parent().after('<img src="' + edd_reviews_params.ajax_loader + '" class="edd-reviews-vote-ajax" />');

						$.post( edd_reviews_params.ajax_url, data, function (response) {
							if (response == 'success') {
								$this.parent().parent().parent().addClass('edd-yellowfade').html('<p style="margin:0;padding:0;">' + edd_reviews_params.thank_you_msg + '</p>');
								$('.edd-reviews-vote-ajax').remove();
							}
						});

						return false;
					});
				},

				reply: function () {
					$('.edd_review .comment-reply-link').on('click', function() {
						$('#edd-reviews-respond').hide();
						$('#edd-reviews-reply').show();
						return false;
					})

					$('.edd-reviews-form #cancel-comment-reply-link').on('click', function() {
						$('#edd-reviews-respond').show();
						$('#edd-reviews-reply').hide();
						return false;
					})
				}
			}

			EDD_Reviews.init();

		});

	}(jQuery));

}

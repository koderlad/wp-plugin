/* Travel Pack — Frontend JS
 *
 * Handles:
 *  - Opening the booking modal when a "Join Now" button is clicked, populating it
 *    with the chosen departure's date, price and remaining seats.
 *  - Closing on backdrop click, close button, or Escape key — resetting the form.
 *  - Submitting the booking via AJAX and updating the corresponding departure
 *    card in place so the next viewer sees accurate availability without a reload.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var modal = document.getElementById('travel-pack-modal');
		if (!modal) { return; }

		var form            = modal.querySelector('.travel-pack-booking__form');
		var status          = modal.querySelector('.travel-pack-booking__status');
		var submit          = modal.querySelector('.travel-pack-booking__submit');
		var seatsInput      = modal.querySelector('input[data-tp-input="seats"]');
		var departureInput  = modal.querySelector('input[data-tp-input="departure_id"]');
		var summaryDate     = modal.querySelector('[data-tp-summary="date"]');
		var summaryPrice    = modal.querySelector('[data-tp-summary="price"]');
		var currentCard     = null;

		function openModalFor(button) {
			currentCard = button.closest('.travel-pack-departure-card');

			var remaining = parseInt(button.dataset.remaining, 10);
			if (isNaN(remaining) || remaining < 0) { remaining = 0; }

			departureInput.value = button.dataset.departureId || '';
			summaryDate.textContent = button.dataset.date || '—';
			summaryPrice.textContent = button.dataset.price ? button.dataset.price : 'Contact us';

			// Cap the travelers input at the number remaining.
			if (remaining > 0) {
				seatsInput.max = remaining;
			} else {
				seatsInput.removeAttribute('max');
			}
			seatsInput.value = 1;

			modal.hidden = false;
			modal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('travel-pack-modal-open');

			var focusTarget = form.querySelector('#tp_name');
			if (focusTarget) {
				window.setTimeout(function () { focusTarget.focus(); }, 30);
			}
		}

		function closeModal() {
			modal.hidden = true;
			modal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('travel-pack-modal-open');
			// Reset every field back to defaults so the next opening starts fresh.
			form.reset();
			status.className = 'travel-pack-booking__status';
			status.textContent = '';
			submit.disabled = false;
			if (submit.dataset.originalText) {
				submit.textContent = submit.dataset.originalText;
			}
			currentCard = null;
		}

		// Wire every Join Now button on the page.
		document.querySelectorAll('[data-tp-open]').forEach(function (btn) {
			if (btn.disabled) { return; }
			btn.addEventListener('click', function () { openModalFor(btn); });
		});

		// Close via backdrop or × button (any element with data-tp-close).
		modal.querySelectorAll('[data-tp-close]').forEach(function (el) {
			el.addEventListener('click', closeModal);
		});

		// Close on Escape.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !modal.hidden) {
				closeModal();
			}
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			status.className = 'travel-pack-booking__status';
			status.textContent = '';

			var data = new FormData(form);
			data.append('action', 'travel_pack_book');
			data.append('nonce', data.get('travel_pack_booking_nonce'));

			submit.disabled = true;
			submit.dataset.originalText = submit.dataset.originalText || submit.textContent;
			submit.textContent = 'Booking…';

			fetch(TravelPackFrontend.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			})
			.then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
			.then(function (res) {
				submit.disabled = false;
				submit.textContent = submit.dataset.originalText;

				if (res.body && res.body.success) {
					status.className = 'travel-pack-booking__status is-success';
					status.textContent = res.body.data.message;
					updateCard(currentCard, res.body.data.remaining, res.body.data.is_full);
				} else {
					var msg = (res.body && res.body.data && res.body.data.message)
						|| 'Something went wrong. Please try again.';
					status.className = 'travel-pack-booking__status is-error';
					status.textContent = msg;
				}
			})
			.catch(function () {
				submit.disabled = false;
				submit.textContent = submit.dataset.originalText;
				status.className = 'travel-pack-booking__status is-error';
				status.textContent = 'Network error. Please try again.';
			});
		});

		/**
		 * Updates the departure card the user just booked so the availability badge,
		 * Join Now button, and any relevant state reflects the new capacity without
		 * a page reload.
		 */
		function updateCard(card, remaining, isFull) {
			if (!card) { return; }

			var cta   = card.querySelector('.travel-pack-departure-card__cta');
			var badge = card.querySelector('.travel-pack-avail');

			card.classList.remove('travel-pack-departure-card--ok', 'travel-pack-departure-card--low', 'travel-pack-departure-card--full');
			if (badge) {
				badge.classList.remove('travel-pack-avail--ok', 'travel-pack-avail--low', 'travel-pack-avail--full');
			}

			if (isFull) {
				card.classList.add('travel-pack-departure-card--full');
				if (badge) {
					badge.classList.add('travel-pack-avail--full');
					badge.textContent = 'Full';
				}
				if (cta) {
					cta.disabled = true;
					cta.textContent = 'Sold Out';
				}
			} else {
				var state = remaining <= 3 ? 'low' : 'ok';
				card.classList.add('travel-pack-departure-card--' + state);
				if (badge) {
					badge.classList.add('travel-pack-avail--' + state);
					badge.textContent = remaining + ' Left';
				}
				if (cta) {
					cta.dataset.remaining = remaining;
				}
			}
		}
	});
})();

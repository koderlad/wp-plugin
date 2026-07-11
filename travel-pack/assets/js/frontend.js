/* Travel Pack — Frontend JS
 * Submits the booking form via AJAX and updates the seat count in the dropdown.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('.travel-pack-booking__form');
		if (!form) { return; }

		var status = form.querySelector('.travel-pack-booking__status');
		var submit = form.querySelector('.travel-pack-booking__submit');
		var seatsInput = form.querySelector('input[name="seats"]');
		var departureSelect = form.querySelector('select[name="departure_id"]');

		// Cap the seats input to what's available for the selected departure.
		function syncSeatsMax() {
			var opt = departureSelect.options[departureSelect.selectedIndex];
			if (!opt || !opt.value) {
				seatsInput.removeAttribute('max');
				return;
			}
			var remaining = parseInt(opt.dataset.remaining, 10);
			if (!isNaN(remaining) && remaining > 0) {
				seatsInput.max = remaining;
				if (parseInt(seatsInput.value, 10) > remaining) {
					seatsInput.value = remaining;
				}
			}
		}
		departureSelect.addEventListener('change', syncSeatsMax);
		syncSeatsMax();

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
					// Reduce the remaining seats shown in the option label and dataset.
					var opt = departureSelect.options[departureSelect.selectedIndex];
					if (opt) {
						opt.dataset.remaining = res.body.data.remaining;
						updateOptionLabel(opt, res.body.data.remaining, res.body.data.is_full);
					}
					form.reset();
					syncSeatsMax();
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

		function updateOptionLabel(opt, remaining, isFull) {
			var datePart = opt.textContent.split(' — ')[0];
			if (isFull) {
				opt.textContent = datePart + ' — Fully booked';
				opt.disabled = true;
			} else {
				var word = remaining === 1 ? 'seat left' : 'seats left';
				opt.textContent = datePart + ' — ' + remaining + ' ' + word;
			}
		}
	});
})();

/* Travel Pack — Admin JS
 *
 * Vanilla JS. Handles:
 *  - Settings screen: add/remove rows for dropdown value lists.
 *  - Package edit screen: add/remove rows in Included / Not Included / Important Notes,
 *    Itinerary counter, Departures.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		bindSettingsEditors();
		bindRepeaters();
		bindItineraryCounters();
	});

	/* --- Dropdown settings --- */
	function bindSettingsEditors() {
		document.querySelectorAll('.travel-pack-list-editor').forEach(function (editor) {
			var field = editor.dataset.field;
			editor.addEventListener('click', function (e) {
				var addBtn = e.target.closest('.travel-pack-add-row');
				if (addBtn && addBtn.dataset.field === field) {
					e.preventDefault();
					var list = editor.querySelector('.travel-pack-list-editor__items');
					list.appendChild(buildSettingRow(field, addBtn.dataset.placeholder || ''));
					list.lastElementChild.querySelector('input').focus();
					return;
				}
				var rmBtn = e.target.closest('.travel-pack-remove-row');
				if (rmBtn) {
					e.preventDefault();
					var li = rmBtn.closest('.travel-pack-list-editor__item');
					if (li) { li.parentNode.removeChild(li); }
				}
			});
		});
	}

	function buildSettingRow(field, placeholder) {
		var li = document.createElement('li');
		li.className = 'travel-pack-list-editor__item';
		li.innerHTML =
			'<span class="travel-pack-drag" aria-hidden="true">☰</span>' +
			'<input type="text" name="' + field + '[]" value="" />' +
			'<button type="button" class="button-link-delete travel-pack-remove-row" aria-label="Remove">×</button>';
		li.querySelector('input').placeholder = placeholder;
		return li;
	}

	/* --- Repeaters (notes, itinerary, departures) --- */
	function bindRepeaters() {
		document.querySelectorAll('.travel-pack-repeater').forEach(function (rep) {
			rep.addEventListener('click', function (e) {
				var add = e.target.closest('.travel-pack-repeater__add');
				if (add && add.dataset.key === rep.dataset.key) {
					e.preventDefault();
					var list = rep.querySelector('.travel-pack-repeater__items');
					var node = buildRepeaterRow(rep.dataset.key, add.dataset.placeholder || '');
					list.appendChild(node);
					if (rep.dataset.key === 'itinerary') {
						bindCounter(node.querySelector('.travel-pack-counter__control'));
					}
					var firstField = node.querySelector('textarea, input[type="date"], input[type="text"]');
					if (firstField) { firstField.focus(); }
					return;
				}
				var rm = e.target.closest('.travel-pack-repeater__remove');
				if (rm) {
					e.preventDefault();
					var item = rm.closest('.travel-pack-repeater__item');
					if (item) { item.parentNode.removeChild(item); }
				}
			});
		});
	}

	function buildRepeaterRow(key, placeholder) {
		var div = document.createElement('div');
		div.className = 'travel-pack-repeater__item';

		if (key === 'itinerary') {
			div.classList.add('travel-pack-itinerary__item');
			div.innerHTML =
				'<button type="button" class="travel-pack-repeater__remove" aria-label="Remove itinerary item">×</button>' +
				'<div class="travel-pack-itinerary__row">' +
					'<div class="travel-pack-counter">' +
						'<label>Days</label>' +
						'<div class="travel-pack-counter__control">' +
							'<button type="button" class="travel-pack-counter__btn" data-action="decrement">−</button>' +
							'<input type="number" name="travel_pack_itinerary_days[]" min="1" step="1" value="1" />' +
							'<button type="button" class="travel-pack-counter__btn" data-action="increment">+</button>' +
						'</div>' +
					'</div>' +
					'<div class="travel-pack-itinerary__text">' +
						'<label>Description</label>' +
						'<textarea name="travel_pack_itinerary_text[]" rows="3" placeholder="What happens on these days…"></textarea>' +
					'</div>' +
				'</div>';
			return div;
		}

		if (key === 'departures') {
			div.classList.add('travel-pack-departure');
			div.innerHTML =
				'<button type="button" class="travel-pack-repeater__remove" aria-label="Remove departure">×</button>' +
				'<input type="hidden" name="travel_pack_departure_id[]" value="" />' +
				'<div class="travel-pack-departure__row">' +
					'<div class="travel-pack-departure__field">' +
						'<label>Departure Date</label>' +
						'<input type="date" name="travel_pack_departure_date[]" required />' +
					'</div>' +
					'<div class="travel-pack-departure__field">' +
						'<label>Total Seats</label>' +
						'<input type="number" min="0" step="1" name="travel_pack_departure_seats[]" value="10" />' +
					'</div>' +
					'<div class="travel-pack-departure__field">' +
						'<label>Price / Person</label>' +
						'<input type="text" name="travel_pack_departure_price[]" placeholder="e.g. $1,499 (optional)" />' +
					'</div>' +
					'<div class="travel-pack-departure__field travel-pack-departure__status">' +
						'<label>Status</label>' +
						'<span class="travel-pack-badge travel-pack-badge--ok">New — save to activate</span>' +
					'</div>' +
				'</div>';
			return div;
		}

		// Notes-style repeater.
		var textarea = document.createElement('textarea');
		textarea.name = 'travel_pack_' + key + '[]';
		textarea.rows = 2;
		textarea.placeholder = placeholder;

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'travel-pack-repeater__remove';
		remove.setAttribute('aria-label', 'Remove note');
		remove.textContent = '×';

		div.appendChild(remove);
		div.appendChild(textarea);
		return div;
	}

	/* --- Itinerary counter --- */
	function bindItineraryCounters() {
		document.querySelectorAll('.travel-pack-counter__control').forEach(bindCounter);
	}

	function bindCounter(control) {
		if (!control || control.dataset.tpBound === '1') { return; }
		control.dataset.tpBound = '1';
		var input = control.querySelector('input[type="number"]');
		control.addEventListener('click', function (e) {
			var btn = e.target.closest('.travel-pack-counter__btn');
			if (!btn || !input) { return; }
			e.preventDefault();
			var current = parseInt(input.value, 10);
			if (isNaN(current) || current < 1) { current = 1; }
			if (btn.dataset.action === 'increment') {
				input.value = current + 1;
			} else if (btn.dataset.action === 'decrement') {
				input.value = Math.max(1, current - 1);
			}
		});
		input.addEventListener('input', function () {
			var v = parseInt(input.value, 10);
			if (isNaN(v) || v < 1) { input.value = 1; }
		});
	}
})();

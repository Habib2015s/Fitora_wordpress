jQuery(function ($) {
	var D = window.PMF_PROD;
	var $ui = $('#pmf_ui');
	if (!D || !$ui.length) { return; }

	var items = (D.items || []).slice();
	var $form = $('#pmf_form_sel');
	var $lv = $('#pmf_levels');
	var $tbody = $('#pmf_table tbody');
	var $json = $('#pmf_compat_json');
	var $empty = $ui.find('.pmf-empty-row');
	var forms = D.forms || {};

	var TYPES = { verified: 'سازگار و تأیید شده', conditional: 'سازگار با شرایط مشخص', alt: 'جایگزین تأیید شده' };

	function esc(s) { return $('<div>').text(s).html(); }

	function sync() {
		$json.val(JSON.stringify(items.map(function (i) {
			return { device_id: i.device_id, ctype: i.ctype, note: i.note };
		})));
		$empty.toggle(!items.length);
	}

	function render() {
		$tbody.empty();
		items.forEach(function (it, idx) {
			var $tr = $('<tr>');
			$tr.append($('<td>').text((forms[it.form_id] ? forms[it.form_id].title + ' — ' : '') + it.label));
			var $sel = $('<select>');
			$.each(TYPES, function (k, v) { $sel.append($('<option>').val(k).text(v)); });
			$sel.val(it.ctype).on('change', function () { it.ctype = $(this).val(); sync(); });
			$tr.append($('<td>').append($sel));
			$tr.append($('<td>').append(
				$('<input type="text" maxlength="250" placeholder="مثلاً: فقط مدل‌های دارای ترمز ABS">').val(it.note).on('input', function () { it.note = $(this).val(); sync(); })
			));
			$tr.append($('<td>').append($('<a href="#" class="pmf-rm" title="حذف">&times;</a>').on('click', function (e) {
				e.preventDefault();
				items.splice(idx, 1);
				render();
			})));
			$tbody.append($tr);
		});
		sync();
	}

	function values(upto) {
		var out = [];
		$lv.find('select').each(function (i) {
			if (upto !== undefined && i >= upto) { return false; }
			var v = $(this).val();
			if (!v) { return false; }
			out.push(v);
		});
		return out;
	}

	function loadOptions(level) {
		var fid = $form.val();
		$.post(D.ajax, { action: 'pmf_options', form: fid, values: values(level) }).done(function (r) {
			var $s = $lv.find('select').eq(level);
			if (!r || !r.success || !$s.length) { return; }
			var name = forms[fid].fields[level];
			$s.empty().append($('<option>').val('').text(name + ' (همه)'));
			$.each(r.data.options, function (_, v) { $s.append($('<option>').val(v).text(v)); });
			$s.prop('disabled', false);
		});
	}

	function build() {
		var fid = $form.val();
		$lv.empty();
		if (!forms[fid]) { return; }
		$.each(forms[fid].fields, function (i, name) {
			var $s = $('<select>').append($('<option>').val('').text(name + ' (همه)')).prop('disabled', i > 0);
			$s.on('change', function () {
				for (var j = i + 1; j < forms[fid].fields.length; j++) {
					$lv.find('select').eq(j).empty().append($('<option>').val('').text(forms[fid].fields[j] + ' (همه)')).prop('disabled', true);
				}
				if ($s.val() && i + 1 < forms[fid].fields.length) { loadOptions(i + 1); }
			});
			$lv.append($s);
		});
		loadOptions(0);
	}

	$form.on('change', build);

	$('#pmf_add').on('click', function (e) {
		e.preventDefault();
		var v = values();
		if (!v.length) { window.alert('حداقل اولین فیلد را انتخاب کنید.'); return; }
		var $b = $(this).prop('disabled', true);
		$.post(D.ajax, { action: 'pmf_admin_devices', nonce: D.nonce, form: $form.val(), values: v }).done(function (r) {
			if (!r || !r.success) { return; }
			var have = {};
			items.forEach(function (i) { have[i.device_id] = 1; });
			var added = 0;
			r.data.devices.forEach(function (d) {
				if (!have[d.id]) {
					items.push({ device_id: d.id, form_id: d.form_id, label: d.label, ctype: 'verified', note: '' });
					added++;
				}
			});
			render();
			if (!added) { window.alert('این دستگاه(ها) قبلاً در لیست هست.'); }
		}).always(function () { $b.prop('disabled', false); });
	});

	build();
	render();
});

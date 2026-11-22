(function () {
	'use strict';
	var C = window.PMF_LINK;
	var formSel = document.getElementById('pmf_ql_form');
	var box = document.getElementById('pmf_ql_levels');
	if (!C || !formSel || !box) { return; }

	function post(data) {
		var fd = new FormData();
		Object.keys(data).forEach(function (k) {
			if (Array.isArray(data[k])) { data[k].forEach(function (x) { fd.append(k + '[]', x); }); } else { fd.append(k, data[k]); }
		});
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function sel(label, i) {
		var s = document.createElement('select');
		s.name = 'values[]';
		s.setAttribute('data-i', i);
		s.innerHTML = '<option value="">' + (i === 0 ? 'انتخاب ' + label : 'همه (' + label + ')') + '</option>';
		return s;
	}
	function load(form, level, vals) {
		var f = C.forms[form];
		if (!f || level >= f.fields.length) { return; }
		post({ action: 'pmf_options', form: form, values: vals }).then(function (r) {
			var opts = (r && r.success && r.data.options) || [];
			var s = box.querySelector('select[data-i="' + level + '"]');
			if (!s) { return; }
			opts.forEach(function (o) { var e = document.createElement('option'); e.value = o; e.textContent = o; s.appendChild(e); });
		});
	}
	function build() {
		box.innerHTML = '';
		var form = formSel.value, f = C.forms[form];
		if (!f) { return; }
		f.fields.forEach(function (label, i) {
			var s = sel(label, i);
			box.appendChild(s);
			s.addEventListener('change', function () {
				var vals = [];
				[].forEach.call(box.querySelectorAll('select'), function (x) {
					var n = parseInt(x.getAttribute('data-i'), 10);
					if (n > i) { x.innerHTML = '<option value="">همه (' + f.fields[n] + ')</option>'; }
				});
				for (var k = 0; k <= i; k++) { var v = box.querySelector('select[data-i="' + k + '"]').value; if (!v) { break; } vals.push(v); }
				if (vals.length === i + 1) { load(form, i + 1, vals); }
			});
		});
		load(form, 0, []);
	}
	formSel.addEventListener('change', build);
	build();
})();

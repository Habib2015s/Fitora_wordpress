(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || !t.closest) { return; }
		var cp = t.closest('.pmf-copy');
		if (cp) {
			var txt = cp.getAttribute('data-copy') || '';
			var done = function () { var o = cp.textContent; cp.textContent = 'کپی شد ✓'; setTimeout(function () { cp.textContent = o; }, 1500); };
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(done, function () {}); }
			else { var ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (x) {} document.body.removeChild(ta); }
			return;
		}
		var sw = t.closest('.pmf-swatch');
		if (sw) { var inp = document.getElementById('pmf_primary'); if (inp) { inp.value = sw.getAttribute('data-c'); } }
	});
	var prov = document.getElementById('pmf_sms_provider');
	function toggle() {
		if (!prov) { return; }
		[].forEach.call(document.querySelectorAll('tr[data-prov]'), function (tr) {
			tr.style.display = (' ' + tr.getAttribute('data-prov') + ' ').indexOf(' ' + prov.value + ' ') > -1 ? '' : 'none';
		});
	}
	if (prov) { prov.addEventListener('change', toggle); toggle(); }
})();

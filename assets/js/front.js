(function () {
	'use strict';
	var C = window.PMF || { ajax: '', t: {} };
	var T = C.t || {};
	var GK = 'pmf_garage';

	function post(action, data) {
		var fd = new FormData();
		fd.append('action', action);
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			if (Array.isArray(v)) {
				v.forEach(function (x) { fd.append(k + '[]', x); });
			} else {
				fd.append(k, v);
			}
		});
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}

	function getGarage() {
		try { return JSON.parse(localStorage.getItem(GK) || 'null'); } catch (e) { return null; }
	}
	function setGarage(g) {
		try { if (g) { localStorage.setItem(GK, JSON.stringify(g)); } else { localStorage.removeItem(GK); } } catch (e) {}
	}
	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	/* ---------------- ویجت جستجو ---------------- */
	function initWidget(root) {
		var form = root.getAttribute('data-form');
		var sels = [].slice.call(root.querySelectorAll('.pmf-sel'));
		var go = root.querySelector('.pmf-go');
		var results = root.querySelector('.pmf-results');
		var garageBox = root.querySelector('.pmf-garage');
		var req = root.querySelector('.pmf-req');
		var lastQuery = '';
		var lastKind = 'search';
		var lastVals = [];

		function values() {
			var v = [];
			for (var i = 0; i < sels.length; i++) {
				if (!sels[i].value) { break; }
				v.push(sels[i].value);
			}
			return v;
		}

		function fill(sel, opts) {
			sel.innerHTML = '';
			var o = document.createElement('option');
			o.value = '';
			o.textContent = T.choose;
			sel.appendChild(o);
			opts.forEach(function (x) {
				var e = document.createElement('option');
				e.value = x;
				e.textContent = x;
				sel.appendChild(e);
			});
		}

		function resetFrom(i) {
			for (var j = i; j < sels.length; j++) {
				var label = sels[j].parentNode.querySelector('span').textContent;
				sels[j].innerHTML = '<option value="">' + esc(T.choose + ' ' + label) + '</option>';
				sels[j].disabled = true;
			}
		}

		sels.forEach(function (sel, i) {
			sel.addEventListener('change', function () {
				resetFrom(i + 1);
				if (!sel.value) { if (go) { go.disabled = true; } return; }
				if (i === sels.length - 1) { go.disabled = false; return; }
				go.disabled = true;
				post('pmf_options', { form: form, values: values() }).then(function (r) {
					var opts = (r && r.success && r.data.options) || [];
					if (opts.length) {
						fill(sels[i + 1], opts);
						sels[i + 1].disabled = false;
					} else {
						go.disabled = false; // انتهای مسیر
					}
				}).catch(function () {});
			});
		});

		function show(html, count, query, kind, vals) {
			results.innerHTML = html;
			lastQuery = query || '';
			lastKind = kind || 'search';
			lastVals = vals || [];
			if (req) { req.hidden = true; }
			if (results.scrollIntoView && count) { results.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
		}

		function run(vals, label) {
			results.innerHTML = '<div class="pmf-loading">' + esc(T.loading) + '</div>';
			post('pmf_search', { form: form, values: vals }).then(function (r) {
				if (r && r.success) {
					show(r.data.html, r.data.count, r.data.query, 'search', vals);
					setGarage({ form: form, values: vals, label: label || vals.join(' › ') });
					renderGarage();
				} else {
					results.innerHTML = '<div class="pmf-empty">' + esc((r && r.data && r.data.message) || T.error) + '</div>';
				}
			}).catch(function () { results.innerHTML = '<div class="pmf-empty">' + esc(T.error) + '</div>'; });
		}

		if (go) {
			go.addEventListener('click', function () {
				var v = values();
				if (v.length) { run(v); }
			});
		}

		function renderGarage() {
			if (!garageBox) { return; }
			var g = getGarage();
			if (!g || String(g.form) !== String(form)) { garageBox.hidden = true; return; }
			garageBox.hidden = false;
			garageBox.innerHTML = '<span>' + esc(T.mydev) + ' <strong>' + esc(g.label) + '</strong></span> ' +
				'<button type="button" class="pmf-link pmf-g-show">' + esc(T.showmine) + '</button> ' +
				'<button type="button" class="pmf-link pmf-g-del">' + esc(T.remove) + '</button>';
			garageBox.querySelector('.pmf-g-show').onclick = function () { run(g.values, g.label); };
			garageBox.querySelector('.pmf-g-del').onclick = function () { setGarage(null); renderGarage(); };
		}
		renderGarage();

		/* جستجو با کد */
		var codeInput = root.querySelector('.pmf-code');
		var codeGo = root.querySelector('.pmf-code-go');
		function runCode() {
			var c = codeInput.value.trim();
			if (!c) { results.innerHTML = '<div class="pmf-empty">' + esc(T.needcode) + '</div>'; return; }
			results.innerHTML = '<div class="pmf-loading">' + esc(T.loading) + '</div>';
			post('pmf_code', { form: form, code: c }).then(function (r) {
				if (r && r.success) { show(r.data.html, r.data.count, r.data.query, 'code', []); }
				else { results.innerHTML = '<div class="pmf-empty">' + esc((r && r.data && r.data.message) || T.error) + '</div>'; }
			}).catch(function () { results.innerHTML = '<div class="pmf-empty">' + esc(T.error) + '</div>'; });
		}
		if (codeGo) {
			codeGo.addEventListener('click', runCode);
			codeInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); runCode(); } });
		}

		/* تب‌ها */
		[].forEach.call(root.querySelectorAll('.pmf-tab'), function (tab) {
			tab.addEventListener('click', function () {
				[].forEach.call(root.querySelectorAll('.pmf-tab'), function (x) { x.classList.toggle('is-active', x === tab); });
				[].forEach.call(root.querySelectorAll('.pmf-pane'), function (p) {
					p.hidden = p.getAttribute('data-pane') !== tab.getAttribute('data-tab');
				});
				results.innerHTML = '';
			});
		});

		/* فرم درخواست محصول */
		root.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('.pmf-open-req') : null;
			if (!a || !req) { return; }
			e.preventDefault();
			req.hidden = false;
			req.querySelector('[name=query]').value = lastQuery;
			req.querySelector('[name=kind]').value = lastKind;
			var d = req.querySelector('[name=details]');
			if (!d.value && lastQuery) { d.value = lastQuery; }
			req.scrollIntoView({ behavior: 'smooth', block: 'center' });
		});
		if (req) {
			req.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = req.querySelector('.pmf-req-msg');
				var btn = req.querySelector('button[type=submit]');
				var data = { form: form, values: lastVals };
				[].forEach.call(req.querySelectorAll('input,textarea'), function (el) { if (el.name) { data[el.name] = el.value; } });
				if (!data.email && !data.phone) { msg.className = 'pmf-req-msg is-err'; msg.textContent = T.needphone; return; }
				btn.disabled = true;
				post('pmf_request', data).then(function (r) {
					btn.disabled = false;
					if (r && r.success) {
						msg.className = 'pmf-req-msg is-ok';
						msg.textContent = T.sent;
						req.querySelector('[name=details]').value = '';
					} else {
						msg.className = 'pmf-req-msg is-err';
						msg.textContent = (r && r.data && r.data.message) || T.error;
					}
				}).catch(function () { btn.disabled = false; msg.className = 'pmf-req-msg is-err'; msg.textContent = T.error; });
			});
		}
	}

	/* ---------------- بررسی سازگاری در صفحهٔ محصول ---------------- */
	function initCheck(box) {
		var g = getGarage();
		if (!g) { return; }
		post('pmf_check', { form: g.form, values: g.values, product: box.getAttribute('data-product') }).then(function (r) {
			if (!r || !r.success) { return; }
			var s = r.data.status;
			var msg = s === 'verified' ? T.ok : s === 'conditional' ? T.cond : s === 'alt' ? T.alt : T.no;
			var cls = s === 'none' ? 'is-no' : s === 'verified' ? 'is-ok' : 'is-warn';
			box.className = 'pmf-gcheck ' + cls;
			box.innerHTML = esc(msg) + ' <strong>' + esc(g.label) + '</strong>' + (r.data.note ? '<div class="pmf-gnote">' + esc(r.data.note) + '</div>' : '');
			box.hidden = false;
		}).catch(function () {});
	}

	/* ---------------- «خبرم کن» در صفحهٔ محصول ---------------- */
	function initNotify(box) {
		var go = box.querySelector('.pmf-n-go');
		var msg = box.querySelector('.pmf-n-msg');
		go.addEventListener('click', function () {
			var em = box.querySelector('.pmf-n-email').value.trim();
			var phEl = box.querySelector('.pmf-n-phone');
			var phone = phEl ? phEl.value.trim() : '';
			if (!em && !phone) { msg.className = 'pmf-n-msg is-err'; msg.textContent = T.needphone; return; }
			go.disabled = true;
			post('pmf_request', {
				kind: 'product', product: box.getAttribute('data-product'), phone: phone, email: em,
				name: box.querySelector('.pmf-n-name').value, website: box.querySelector('.pmf-n-web').value,
				loaded: box.getAttribute('data-loaded')
			}).then(function (r) {
				go.disabled = false;
				if (r && r.success) { msg.className = 'pmf-n-msg is-ok'; msg.textContent = T.sent; }
				else { msg.className = 'pmf-n-msg is-err'; msg.textContent = (r && r.data && r.data.message) || T.error; }
			}).catch(function () { go.disabled = false; msg.className = 'pmf-n-msg is-err'; msg.textContent = T.error; });
		});
	}

	function boot() {
		[].forEach.call(document.querySelectorAll('.pmf-notify[data-product]'), initNotify);
		[].forEach.call(document.querySelectorAll('.pmf[data-form]'), initWidget);
		[].forEach.call(document.querySelectorAll('.pmf-gcheck[data-product]'), initCheck);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();

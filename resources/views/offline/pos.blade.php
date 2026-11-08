<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Offline POS</title>
@verbatim
<style>
*{box-sizing:border-box}
body{margin:0;font:15px/1.4 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f4f6;color:#111827}
header.bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 14px;background:#1e3a8a;color:#fff}
header.bar h1{font-size:17px;margin:0;margin-right:auto}
header.bar a{color:#bfdbfe;font-size:13px}
.pill{padding:2px 10px;border-radius:99px;font-weight:600;font-size:13px}
.pill.on{background:#16a34a}.pill.off{background:#dc2626}
button{font:inherit;cursor:pointer;border:1px solid #cbd5e1;background:#fff;border-radius:6px;padding:6px 10px}
header.bar button{background:#1d4ed8;color:#fff;border-color:#3b82f6}
button.primary{background:#16a34a;color:#fff;border-color:#15803d;font-weight:700;font-size:17px;padding:12px;width:100%}
.banner{padding:8px 14px;font-size:14px}
#authBanner{background:#fef3c7;color:#92400e}#errBanner{background:#fee2e2;color:#991b1b}
main.grid{display:grid;grid-template-columns:1.4fr 1fr;gap:12px;padding:12px;max-width:1200px;margin:auto}
@media(max-width:820px){main.grid{grid-template-columns:1fr}}
.card{background:#fff;border-radius:10px;padding:12px;box-shadow:0 1px 2px rgba(0,0,0,.08);margin-bottom:12px}
input[type=text],input[type=number],select{width:100%;padding:9px;border:1px solid #cbd5e1;border-radius:6px;font:inherit}
#results{display:grid;gap:6px;margin-top:8px;max-height:230px;overflow:auto}
.item{display:flex;justify-content:space-between;gap:8px;text-align:left}.item span{color:#64748b;font-size:13px}
table{width:100%;border-collapse:collapse}td,th{padding:6px 4px;border-bottom:1px solid #e5e7eb;vertical-align:top}
.r{text-align:right}.muted{color:#6b7280}
input.qty{width:80px}button.x{border:0;font-size:22px;color:#dc2626;background:none}
.tot{display:flex;justify-content:space-between;font-size:15px;margin:6px 0}.tot.big{font-size:22px;font-weight:700}
.pm label{display:block;padding:6px 0}
.q{display:flex;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid #e5e7eb;font-size:14px}
.q.failed{background:#fef2f2}.q.done{color:#6b7280}.q .acts button{margin-left:4px;font-size:12px;padding:3px 7px}
#toast{position:fixed;left:50%;bottom:20px;transform:translateX(-50%);background:#065f46;color:#fff;padding:10px 16px;border-radius:8px;opacity:0;pointer-events:none;transition:opacity .2s;max-width:90vw;z-index:9}
#toast.show{opacity:1}#toast.bad{background:#991b1b}
#receipt{display:none}
@media print{
  body *{visibility:hidden}
  #receipt,#receipt *{visibility:visible}
  #receipt{display:block;position:absolute;left:0;top:0;width:72mm;font:12px/1.35 monospace;color:#000}
  #receipt .c{text-align:center}#receipt hr{border:0;border-top:1px dashed #000}
  #receipt .row{display:flex;justify-content:space-between}
}
</style>
@endverbatim
</head>
<body>
<header class="bar">
  <h1>Offline POS <small id="loc"></small></h1>
  <span id="pill" class="pill">...</span>
  <span id="counts"></span>
  <button type="button" id="syncBtn">Sync now</button>
  <button type="button" id="catalogBtn">Update catalog</button>
  <a href="{{ url('/pos/create') }}">Normal POS</a>
  <a href="{{ url('/home') }}">Home</a>
</header>
<div id="authBanner" class="banner" style="display:none">Your session has expired. Log in again in another tab, then press <b>Sync now</b>. Your sales are safe on this device.</div>
<div id="errBanner" class="banner" style="display:none"></div>
@verbatim
<main class="grid">
  <section>
    <div class="card">
      <input type="text" id="search" placeholder="Search by name or SKU. Scan a barcode and press Enter" autocomplete="off" autofocus>
      <div id="results"></div>
    </div>
    <div class="card">
      <table>
        <thead><tr><th>Item</th><th>Qty</th><th class="r">Amount</th><th></th></tr></thead>
        <tbody id="cartBody"></tbody>
      </table>
    </div>
    <div class="card">
      <b>Offline sales on this device</b>
      <div id="queueList"></div>
      <p class="muted" id="catalogInfo"></p>
      <button type="button" id="clearBtn">Clear offline data</button>
    </div>
  </section>
  <aside>
    <div class="card">
      <label>Customer</label>
      <input type="text" id="custq" placeholder="Search customer by name or phone">
      <select id="cust" size="1" style="margin-top:6px"></select>
    </div>
    <div class="card">
      <div class="tot"><span>Subtotal</span><span id="subtotal"></span></div>
      <div class="tot"><span>Discount (KSh)</span><span style="width:110px"><input type="number" id="discount" min="0" step="any" placeholder="0"></span></div>
      <div class="tot big"><span>TOTAL</span><span id="total"></span></div>
    </div>
    <div class="card pm">
      <b>Payment</b>
      <label><input type="radio" name="pm" value="cash" checked> Cash</label>
      <div id="cashBox"><input type="number" id="tendered" min="0" step="any" placeholder="Cash received"><div class="tot"><span>Change</span><span id="change"></span></div></div>
      <label id="tillRow"><input type="radio" name="pm" value="till"> <span id="tillLabel">M-Pesa Till</span></label>
      <div id="tillBox" style="display:none"><input type="text" id="tillcode" placeholder="M-Pesa code, e.g. SJK4X9ABCD" autocomplete="off"></div>
      <label><input type="radio" name="pm" value="credit"> On credit (named customer)</label>
      <p class="muted" id="creditNote"></p>
    </div>
    <button type="button" class="primary" id="completeBtn">Complete sale and print receipt</button>
  </aside>
</main>
<div id="receipt"></div>
<div id="toast"></div>
@endverbatim
<script>
window.OFFLINE_CFG = @json($cfg);
document.getElementById('loc').textContent = '· ' + window.OFFLINE_CFG.locationName;
if (!window.OFFLINE_CFG.tillEnabled) { document.getElementById('tillRow').style.display = 'none'; }
else { document.getElementById('tillLabel').textContent = window.OFFLINE_CFG.tillLabel + ' (customer paid to our till)'; }
</script>
<script>
@verbatim
(function () {
  'use strict';

  var CFG = window.OFFLINE_CFG;
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var round2 = function (n) { return Math.round((n + Number.EPSILON) * 100) / 100; };
  var money = function (n) { return CFG.currency + ' ' + Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var qtyText = function (n) { return String(Math.round(n * 10000) / 10000); };

  // ---------- IndexedDB ----------
  var dbp = new Promise(function (resolve, reject) {
    var r = indexedDB.open('dantepos-offline', 1);
    r.onupgradeneeded = function () {
      var d = r.result;
      d.createObjectStore('products', { keyPath: 'variation_id' });
      d.createObjectStore('customers', { keyPath: 'id' });
      d.createObjectStore('queue', { keyPath: 'uuid' });
      d.createObjectStore('meta', { keyPath: 'key' });
    };
    r.onsuccess = function () { resolve(r.result); };
    r.onerror = function () { reject(r.error); };
  });

  var idb = {
    run: function (store, mode, fn) {
      return dbp.then(function (db) {
        return new Promise(function (resolve, reject) {
          var t = db.transaction(store, mode), result;
          try { result = fn(t.objectStore(store)); } catch (e) { reject(e); return; }
          t.oncomplete = function () { resolve(result && 'result' in result ? result.result : undefined); };
          t.onerror = function () { reject(t.error); };
          t.onabort = function () { reject(t.error); };
        });
      });
    },
    getAll: function (s) { return idb.run(s, 'readonly', function (st) { return st.getAll(); }); },
    get: function (s, k) { return idb.run(s, 'readonly', function (st) { return st.get(k); }); },
    put: function (s, o) { return idb.run(s, 'readwrite', function (st) { st.put(o); }); },
    putMany: function (s, a) { return idb.run(s, 'readwrite', function (st) { a.forEach(function (o) { st.put(o); }); }); },
    del: function (s, k) { return idb.run(s, 'readwrite', function (st) { st.delete(k); }); },
    clear: function (s) { return idb.run(s, 'readwrite', function (st) { st.clear(); }); }
  };

  // ---------- state ----------
  var products = [], customers = [], queue = [], meta = {}, cart = [];
  var syncing = false, authExpired = false, lastSyncError = '', lastFetch = 0;

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var b = new Uint8Array(16); crypto.getRandomValues(b);
    b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
    var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }

  function toast(msg, bad) {
    var t = $('toast');
    t.textContent = msg; t.className = 'show' + (bad ? ' bad' : '');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(function () { t.className = ''; }, 3500);
  }

  // ---------- loading ----------
  function loadLocal() {
    return Promise.all([idb.getAll('products'), idb.getAll('customers'), idb.getAll('queue'), idb.get('meta', 'catalog')]).then(function (r) {
      products = r[0] || []; customers = r[1] || []; queue = (r[2] || []).sort(function (a, b) { return a.created - b.created; });
      meta = r[3] || {};
    });
  }

  function purgeOldDone() {
    var cutoff = Date.now() - 7 * 86400000;
    var old = queue.filter(function (q) { return q.status === 'done' && (q.synced || q.created) < cutoff; });
    return Promise.all(old.map(function (q) { return idb.del('queue', q.uuid); }));
  }

  // Stock the server reported does not include sales that are still on this device.
  function applyPendingStock() {
    var byVar = {}; products.forEach(function (p) { byVar[p.variation_id] = p; });
    var touched = {};
    queue.filter(function (q) { return q.status !== 'done'; }).forEach(function (q) {
      q.sale.lines.forEach(function (l) {
        var p = byVar[l.variation_id];
        if (p && p.enable_stock && p.stock != null) { p.stock = Math.round((p.stock - l.qty) * 10000) / 10000; touched[p.variation_id] = p; }
      });
    });
    var arr = Object.keys(touched).map(function (k) { return touched[k]; });
    return arr.length ? idb.putMany('products', arr) : Promise.resolve();
  }

  function refreshCatalog(silent) {
    if (!navigator.onLine) { if (!silent) toast('You are offline. Using the saved catalog.', true); return Promise.resolve(false); }
    return fetch(CFG.catalogUrl + '?location_id=' + CFG.locationId, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (resp) {
        if (resp.status === 401 || resp.status === 419 || resp.redirected) { authExpired = true; render(); throw new Error('login'); }
        if (!resp.ok) throw new Error('Server error ' + resp.status);
        return resp.json();
      })
      .then(function (data) {
        authExpired = false; lastFetch = Date.now();
        return idb.clear('products').then(function () { return idb.putMany('products', data.products); })
          .then(function () { return idb.clear('customers'); })
          .then(function () { return idb.putMany('customers', data.customers); })
          .then(function () { return idb.put('meta', { key: 'catalog', updated: Date.now(), business: data.business, currency: data.currency }); });
      })
      .then(loadLocal).then(applyPendingStock).then(loadLocal)
      .then(function () { render(); if (!silent) toast('Catalog updated: ' + products.length + ' items'); return true; })
      .catch(function (e) { if (e.message !== 'login' && !silent) toast('Could not update the catalog: ' + e.message, true); return false; });
  }

  // ---------- search and cart ----------
  function findBySku(code) {
    code = code.trim().toLowerCase();
    return products.find(function (p) { return p.sku && p.sku.toLowerCase() === code; });
  }

  function search(q) {
    q = q.trim().toLowerCase();
    if (!q) return [];
    var parts = q.split(/\s+/);
    return products.filter(function (p) {
      var hay = (p.name + ' ' + p.sku).toLowerCase();
      return parts.every(function (w) { return hay.indexOf(w) !== -1; });
    }).slice(0, 30);
  }

  function cartQty(variationId) {
    var l = cart.find(function (x) { return x.variation_id === variationId; });
    return l ? l.qty : 0;
  }

  function addToCart(p) {
    if (p.enable_stock && p.stock != null && p.stock - cartQty(p.variation_id) < 1) {
      if (!confirm('Only ' + qtyText(Math.max(p.stock, 0)) + ' of "' + p.name + '" in stock. Add anyway?')) return;
    }
    var l = cart.find(function (x) { return x.variation_id === p.variation_id; });
    if (l) l.qty = Math.round((l.qty + 1) * 10000) / 10000;
    else cart.push({ variation_id: p.variation_id, product_id: p.product_id, name: p.name, sku: p.sku, product_type: p.product_type, enable_stock: p.enable_stock, unit_id: p.unit_id, tax_id: p.tax_id, unit_price: p.unit_price, unit_price_inc_tax: p.unit_price_inc_tax, qty: 1 });
    renderCart();
  }

  function totals() {
    var subtotal = round2(cart.reduce(function (s, l) { return s + l.qty * l.unit_price_inc_tax; }, 0));
    var discount = Math.min(Math.max(parseFloat($('discount').value) || 0, 0), subtotal);
    return { subtotal: subtotal, discount: round2(discount), total: round2(subtotal - discount) };
  }

  function currentCustomer() {
    var id = parseInt($('cust').value, 10);
    return customers.find(function (c) { return c.id === id; }) || customers.find(function (c) { return c.is_default; }) || null;
  }

  // ---------- rendering ----------
  function renderResults() {
    var list = search($('search').value);
    $('results').innerHTML = list.map(function (p) {
      var stock = p.stock == null ? '' : ' · stock ' + qtyText(p.stock);
      return '<button type="button" class="item" data-v="' + p.variation_id + '"><b>' + esc(p.name) + '</b><span>' + esc(p.sku) + ' · ' + money(p.unit_price_inc_tax) + stock + '</span></button>';
    }).join('');
  }

  function renderCart() {
    var rows = cart.map(function (l, i) {
      return '<tr><td>' + esc(l.name) + '<br><small>' + esc(l.sku) + ' · ' + money(l.unit_price_inc_tax) + '</small></td>' +
        '<td><input type="number" min="0.001" step="any" value="' + qtyText(l.qty) + '" data-i="' + i + '" class="qty"></td>' +
        '<td class="r">' + money(l.qty * l.unit_price_inc_tax) + '</td>' +
        '<td><button type="button" class="x" data-i="' + i + '">&times;</button></td></tr>';
    }).join('');
    $('cartBody').innerHTML = rows || '<tr><td colspan="4" class="muted">Cart is empty. Search or scan an item.</td></tr>';
    renderTotals();
  }

  function renderTotals() {
    var t = totals();
    $('subtotal').textContent = money(t.subtotal);
    $('total').textContent = money(t.total);
    var method = (document.querySelector('input[name=pm]:checked') || {}).value;
    $('cashBox').style.display = method === 'cash' ? '' : 'none';
    $('tillBox').style.display = method === 'till' ? '' : 'none';
    var tendered = parseFloat($('tendered').value);
    $('change').textContent = method === 'cash' && !isNaN(tendered) ? money(Math.max(tendered - t.total, 0)) : '';
    var cust = currentCustomer();
    $('creditNote').textContent = method === 'credit' ? (cust && !cust.is_default ? 'Goes on ' + cust.name + "'s account. The server checks their credit limit when it syncs." : 'Pick a named customer for credit sales.') : '';
  }

  function renderCustomers() {
    var q = $('custq').value.trim().toLowerCase();
    var cur = $('cust').value;
    var list = customers.filter(function (c) { return c.is_default || !q || (c.name + ' ' + c.mobile).toLowerCase().indexOf(q) !== -1; }).slice(0, 100);
    $('cust').innerHTML = list.map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + (c.mobile ? ' · ' + esc(c.mobile) : '') + '</option>'; }).join('');
    if (cur && list.some(function (c) { return String(c.id) === cur; })) $('cust').value = cur;
  }

  function renderQueue() {
    var items = queue.slice().reverse();
    $('queueList').innerHTML = items.length ? items.map(function (q) {
      var label = q.status === 'done' ? 'Synced ' + esc(q.invoice_no || '') : q.status === 'failed' ? 'FAILED' : 'Waiting to sync';
      var actions = '<button type="button" data-a="print" data-u="' + q.uuid + '">Reprint</button>' +
        (q.status === 'failed' ? '<button type="button" data-a="retry" data-u="' + q.uuid + '">Retry</button><button type="button" data-a="discard" data-u="' + q.uuid + '">Discard</button>' : '');
      return '<div class="q ' + q.status + '"><div><b>' + esc(q.sale.ref) + '</b> · ' + money(q.sale.total) + ' · ' + new Date(q.sale.created_at).toLocaleString('en-KE') + '<br><small>' + label + (q.error ? ' · ' + esc(q.error) : '') + '</small></div><div class="acts">' + actions + '</div></div>';
    }).join('') : '<p class="muted">No offline sales yet.</p>';
  }

  function render() {
    var pending = queue.filter(function (q) { return q.status === 'pending'; }).length;
    var failed = queue.filter(function (q) { return q.status === 'failed'; }).length;
    var pill = $('pill');
    pill.textContent = navigator.onLine ? 'Online' : 'Offline';
    pill.className = 'pill ' + (navigator.onLine ? 'on' : 'off');
    $('counts').textContent = 'Waiting: ' + pending + (failed ? ' · Failed: ' + failed : '') + (syncing ? ' · syncing...' : '');
    $('catalogInfo').textContent = meta.updated ? 'Catalog: ' + products.length + ' items, saved ' + new Date(meta.updated).toLocaleString('en-KE') : 'No catalog saved yet';
    $('authBanner').style.display = authExpired ? '' : 'none';
    $('errBanner').style.display = lastSyncError ? '' : 'none';
    $('errBanner').textContent = lastSyncError;
    renderQueue();
  }

  // ---------- receipt ----------
  function printReceipt(sale) {
    var b = meta.business || {};
    var p = sale.payment || {};
    var lines = sale.lines.map(function (l) {
      return '<tr><td colspan="3">' + esc(l.name) + '</td></tr><tr><td>' + qtyText(l.qty) + ' x ' + Number(l.unit_price_inc_tax).toFixed(2) + '</td><td></td><td class="r">' + (l.qty * l.unit_price_inc_tax).toFixed(2) + '</td></tr>';
    }).join('');
    var pay = p.method === 'cash' ? '<div>Cash: ' + money(p.tendered) + '</div><div>Change: ' + money(p.change || 0) + '</div>' :
      p.method === 'till' ? '<div>' + esc(CFG.tillLabel) + ': ' + esc(p.code) + '</div>' :
      '<div><b>ON CREDIT - amount due ' + money(sale.total) + '</b></div>';
    $('receipt').innerHTML =
      '<div class="c"><b>' + esc(b.name || CFG.locationName) + '</b><br>' + esc(b.location || '') + (b.address ? '<br>' + esc(b.address) : '') + (b.mobile ? '<br>Tel: ' + esc(b.mobile) : '') + (b.tax_number ? '<br>' + esc(b.tax_label || 'PIN') + ': ' + esc(b.tax_number) : '') + '</div><hr>' +
      '<div class="c"><b>RECEIPT</b><br><small>Offline sale - not yet posted to the system</small></div>' +
      '<div>Ref: ' + esc(sale.ref) + '</div><div>Date: ' + new Date(sale.created_at).toLocaleString('en-KE') + '</div><div>Served by: ' + esc(sale.served_by) + '</div><div>Customer: ' + esc(sale.customer_name) + '</div><hr>' +
      '<table>' + lines + '</table><hr>' +
      '<div class="row"><span>Subtotal</span><span>' + money(sale.subtotal) + '</span></div>' +
      (sale.discount ? '<div class="row"><span>Discount</span><span>-' + money(sale.discount) + '</span></div>' : '') +
      '<div class="row"><b>TOTAL</b><b>' + money(sale.total) + '</b></div><hr>' + pay + '<hr>' +
      '<div class="c">' + esc(b.footer || 'Thank you for shopping with us!') + '</div>';
    setTimeout(function () { window.print(); }, 50);
  }

  // ---------- sale ----------
  function completeSale() {
    if (!cart.length) return toast('The cart is empty.', true);
    var t = totals();
    var method = document.querySelector('input[name=pm]:checked').value;
    var cust = currentCustomer();
    var payment = { method: method };

    if (t.total <= 0 && method !== 'credit') return toast('The total is zero.', true);
    if (method === 'cash') {
      var tendered = parseFloat($('tendered').value);
      payment.tendered = isNaN(tendered) ? t.total : round2(tendered);
      if (payment.tendered < t.total - 0.009) return toast('Cash received is less than the total.', true);
      payment.change = round2(payment.tendered - t.total);
    } else if (method === 'till') {
      var code = $('tillcode').value.trim().toUpperCase();
      if (code.length < 6) return toast('Enter the M-Pesa transaction code from the customer.', true);
      payment.code = code;
    } else if (method === 'credit') {
      if (!cust || cust.is_default) return toast('Pick a named customer for a credit sale.', true);
    }

    var id = uuid();
    var sale = {
      uuid: id, ref: 'OFF-' + id.slice(0, 8).toUpperCase(), created_at: new Date().toISOString(),
      location_id: CFG.locationId, contact_id: cust ? cust.id : null, customer_name: cust ? cust.name : 'Walk-In Customer',
      lines: cart.map(function (l) { return { variation_id: l.variation_id, product_id: l.product_id, name: l.name, sku: l.sku, product_type: l.product_type, enable_stock: l.enable_stock, unit_id: l.unit_id, tax_id: l.tax_id, unit_price: l.unit_price, unit_price_inc_tax: l.unit_price_inc_tax, qty: l.qty }; }),
      subtotal: t.subtotal, discount: t.discount, total: t.total, payment: payment, served_by: CFG.userName
    };

    var byVar = {}; products.forEach(function (p) { byVar[p.variation_id] = p; });
    var touched = [];
    sale.lines.forEach(function (l) { var p = byVar[l.variation_id]; if (p && p.enable_stock && p.stock != null) { p.stock = Math.round((p.stock - l.qty) * 10000) / 10000; touched.push(p); } });

    idb.put('queue', { uuid: id, status: 'pending', error: '', sale: sale, created: Date.now() })
      .then(function () { return touched.length ? idb.putMany('products', touched) : null; })
      .then(loadLocal)
      .then(function () {
        cart = []; $('discount').value = ''; $('tendered').value = ''; $('tillcode').value = '';
        renderCart(); renderResults(); render();
        printReceipt(sale);
        toast('Sale saved on this device (' + sale.ref + ')');
        if (navigator.onLine) syncNow();
      })
      .catch(function (e) { toast('Could not save the sale: ' + e, true); });
  }

  // ---------- sync ----------
  function freshToken() {
    return fetch(CFG.tokenUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) {
      var json = (r.headers.get('content-type') || '').indexOf('json') !== -1;
      if (r.status === 401 || r.status === 419 || r.redirected || !json) { var e = new Error('login'); e.auth = true; throw e; }
      if (!r.ok) throw new Error('Server error ' + r.status);
      return r.json();
    }).then(function (d) { return d.token; });
  }

  function syncNow() {
    if (syncing || !navigator.onLine) return Promise.resolve();
    var batch = queue.filter(function (q) { return q.status === 'pending'; }).slice(0, 10);
    if (!batch.length) return Promise.resolve();
    syncing = true; lastSyncError = ''; render();
    var progressed = false;

    return freshToken().then(function (token) {
      return fetch(CFG.syncUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ sales: batch.map(function (b) { return b.sale; }) })
      });
    }).then(function (resp) {
      if (resp.status === 401 || resp.status === 419) { var e = new Error('login'); e.auth = true; throw e; }
      if (!resp.ok) throw new Error('Server error ' + resp.status);
      return resp.json();
    }).then(function (data) {
      authExpired = false;
      var saves = (data.results || []).map(function (r) {
        var item = queue.find(function (q) { return q.uuid === r.uuid; });
        if (!item) return null;
        if (r.ok) { item.status = 'done'; item.invoice_no = r.invoice_no || ''; item.error = ''; item.synced = Date.now(); progressed = true; }
        else { item.status = 'failed'; item.error = r.message || 'Rejected by the server'; }
        return idb.put('queue', item);
      });
      return Promise.all(saves);
    }).catch(function (e) {
      if (e.auth) authExpired = true; else lastSyncError = 'Sync problem: ' + e.message + '. Will retry.';
    }).then(function () {
      syncing = false; render();
      var more = queue.some(function (q) { return q.status === 'pending'; });
      if (more && progressed) return syncNow();
      if (!more && progressed) return refreshCatalog(true);
    });
  }

  // ---------- wiring ----------
  document.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.classList.contains('item')) { var p = products.find(function (x) { return x.variation_id === parseInt(t.dataset.v, 10); }); if (p) { addToCart(p); $('search').focus(); } }
    else if (t.classList.contains('x')) { cart.splice(parseInt(t.dataset.i, 10), 1); renderCart(); }
    else if (t.dataset.a) {
      var item = queue.find(function (q) { return q.uuid === t.dataset.u; }); if (!item) return;
      if (t.dataset.a === 'print') printReceipt(item.sale);
      if (t.dataset.a === 'retry') { item.status = 'pending'; item.error = ''; idb.put('queue', item).then(function () { render(); syncNow(); }); }
      if (t.dataset.a === 'discard') {
        if (confirm('Discard ' + item.sale.ref + '? Only do this if the sale is already in the system or was cancelled. This cannot be undone.')) {
          idb.del('queue', item.uuid).then(loadLocal).then(applyPendingStock).then(loadLocal).then(render);
        }
      }
    }
  });

  document.addEventListener('input', function (e) {
    if (e.target.classList.contains('qty')) { var l = cart[parseInt(e.target.dataset.i, 10)]; var v = parseFloat(e.target.value); if (l && v > 0) { l.qty = v; renderTotals(); var cells = e.target.closest('tr').children; cells[2].textContent = money(l.qty * l.unit_price_inc_tax); } }
  });

  $('search').addEventListener('input', renderResults);
  $('search').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var v = $('search').value.trim(); if (!v) return;
    var exact = findBySku(v), list = search(v);
    var p = exact || (list.length === 1 ? list[0] : null);
    if (p) { addToCart(p); $('search').value = ''; renderResults(); } else toast(list.length ? 'Several items match. Tap the one you want.' : 'No item found: ' + v, true);
  });
  $('custq').addEventListener('input', function () { renderCustomers(); renderTotals(); });
  $('cust').addEventListener('change', renderTotals);
  $('discount').addEventListener('input', renderTotals);
  $('tendered').addEventListener('input', renderTotals);
  Array.prototype.forEach.call(document.querySelectorAll('input[name=pm]'), function (r) { r.addEventListener('change', renderTotals); });
  $('completeBtn').addEventListener('click', completeSale);
  $('syncBtn').addEventListener('click', function () { if (!navigator.onLine) return toast('You are offline.', true); syncNow(); });
  $('catalogBtn').addEventListener('click', function () { refreshCatalog(false); });
  $('clearBtn').addEventListener('click', function () {
    if (queue.some(function (q) { return q.status !== 'done'; })) return toast('Sync or discard the waiting sales first.', true);
    if (!confirm('Remove the saved catalog, customers and sale history from this device?')) return;
    Promise.all(['products', 'customers', 'queue', 'meta'].map(function (s) { return idb.clear(s); })).then(loadLocal).then(function () { renderCustomers(); renderResults(); render(); toast('Offline data cleared.'); });
  });
  window.addEventListener('online', function () { render(); syncNow().then(function () { return refreshCatalog(true); }); });
  window.addEventListener('offline', render);
  setInterval(function () { if (navigator.onLine) syncNow(); }, 60000);

  if ('serviceWorker' in navigator) navigator.serviceWorker.register(CFG.base + '/offline-sw.js').catch(function () {});
  if (navigator.storage && navigator.storage.persist) navigator.storage.persist();

  loadLocal().then(purgeOldDone).then(loadLocal).then(function () {
    renderCustomers(); renderCart(); renderResults(); render();
    if (!products.length && !navigator.onLine) toast('Open this page online once to download the catalog.', true);
    if (navigator.onLine) return syncNow().then(function () { return products.length ? null : refreshCatalog(true); });
  }).then(function () { if (navigator.onLine && products.length && Date.now() - lastFetch > 10000 && !queue.some(function (q) { return q.status === 'pending'; })) return refreshCatalog(true); });
})();
@endverbatim
</script>
</body>
</html>

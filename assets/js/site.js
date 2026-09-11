(function () {
  'use strict';
  var fmt = function (n) { return Math.max(0, Math.round(n)).toLocaleString('vi-VN') + 'đ'; };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Hóa đơn công ty ở trang thanh toán */
  var inv = $('#invToggle');
  if (inv) inv.addEventListener('change', function () { $('#inv').hidden = !inv.checked; });

  /* Nút sao chép ở trang chuyển khoản */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var txt = b.getAttribute('data-copy'), old = b.textContent;
      var done = function () { b.textContent = 'Đã chép'; setTimeout(function () { b.textContent = old; }, 1500); };
      if (navigator.clipboard) navigator.clipboard.writeText(txt).then(done, function () { window.prompt('Sao chép:', txt); });
      else window.prompt('Sao chép:', txt);
    });
  });

  var D = window.PDP;
  if (!D) return;

  var byId = {};
  D.variants.forEach(function (v) { byId[v.id] = v; });
  var selected = D.selected;
  var main = $('#mainImg');

  /* Gallery */
  function showImage(src, alt) {
    main.innerHTML = '';
    var img = document.createElement('img');
    img.src = src; img.alt = alt || '';
    main.appendChild(img);
  }
  $$('.thumb[data-img]').forEach(function (t, i) {
    if (i === 0) t.classList.add('on');
    t.addEventListener('click', function () {
      $$('.thumb').forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      showImage(t.dataset.img, t.dataset.alt);
    });
  });
  $$('.thumb[data-yt]').forEach(function (t) {
    t.addEventListener('click', function () {
      $$('.thumb').forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      main.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent(t.dataset.yt) +
        '?autoplay=1" title="Video sản phẩm" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
    });
  });

  var form = $('#addform');
  if (!form) return;

  /* Số lượng (sản phẩm không combo) */
  var qtyInput = $('input[name="qty"]', form);
  $$('[data-step]', form).forEach(function (b) {
    b.addEventListener('click', function () {
      var v = Math.min(99, Math.max(1, (parseInt(qtyInput.value, 10) || 1) + parseInt(b.dataset.step, 10)));
      qtyInput.value = v; update();
    });
  });
  if (qtyInput) qtyInput.addEventListener('input', update);

  /* Chọn loại (biến thể) */
  function selectVariant(id) {
    selected = id;
    var v = byId[id];
    $$('.chip[data-vid]').forEach(function (c) {
      var on = parseInt(c.dataset.vid, 10) === id;
      c.classList.toggle('on', on);
      c.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    var set = function (sel, txt) { var el = $(sel); if (el) el.textContent = txt; };
    set('#vdesc', v.desc); set('#snName', v.name); set('#snDesc', v.desc);
    var sw = $('#sw'), chip = $('.chip[data-vid="' + id + '"] .dot');
    if (sw && chip) sw.style.background = chip.style.background;
    set('#unitPrice', fmt(v.price));
    if (v.image) showImage(v.image, v.name);
    var hid = $('#variantId');
    if (hid) hid.value = id;
    var first = $('#slots select');
    if (first) first.value = id;
    try { var u = new URL(location.href); u.searchParams.set('v', id); history.replaceState(null, '', u); } catch (e) {}
    update();
  }
  $$('.chip[data-vid]').forEach(function (c) {
    c.addEventListener('click', function (e) { e.preventDefault(); selectVariant(parseInt(c.dataset.vid, 10)); });
  });

  /* Combo: số ô chọn loại = số lượng của combo */
  function currentTier() {
    var r = $('input[name="tier"]:checked', form);
    return r ? parseInt(r.value, 10) : 1;
  }
  function buildSlots() {
    var wrap = $('#slots');
    if (!wrap) return;
    var q = currentTier();
    var existing = $$('select', wrap).map(function (s) { return parseInt(s.value, 10); });
    var vals = existing.slice(0, q);
    var used = {}; vals.forEach(function (v) { used[v] = true; });
    D.variants.forEach(function (v) { if (vals.length < q && !used[v.id]) { vals.push(v.id); used[v.id] = true; } });
    while (vals.length < q) vals.push(selected);
    wrap.innerHTML = '';
    var unit = D.unitWord.charAt(0).toUpperCase() + D.unitWord.slice(1);
    vals.forEach(function (val, i) {
      var label = document.createElement('label');
      if (q === 1) label.className = 'sr';
      label.appendChild(document.createTextNode(unit + ' ' + (i + 1)));
      var sel = document.createElement('select');
      sel.name = 'variant_ids[]';
      D.variants.forEach(function (v) {
        var o = document.createElement('option');
        o.value = v.id; o.textContent = v.name;
        if (v.id === val) o.selected = true;
        sel.appendChild(o);
      });
      sel.addEventListener('change', function () { if (i === 0) selectVariant(parseInt(sel.value, 10)); else update(); });
      label.appendChild(sel);
      wrap.appendChild(label);
    });
    var lab = $('#slotsLabel');
    if (lab) lab.hidden = q < 2;
    update();
  }
  $$('input[name="tier"]', form).forEach(function (r) { r.addEventListener('change', buildSlots); });

  /* Tính tiền hiển thị (giá chính thức luôn tính lại ở máy chủ) */
  function tierFor(q) {
    var t = null;
    D.tiers.forEach(function (x) { if (x.q <= q) t = x; });
    return t;
  }
  function update() {
    var unitPrice = byId[selected].price;
    $$('[data-tier-price]').forEach(function (el) {
      var q = parseInt(el.dataset.tierPrice, 10), t = tierFor(q);
      el.textContent = fmt(q * unitPrice - (t ? t.d : 0));
    });
    var total, qty, free = false;
    if (D.combo) {
      var ids = $$('#slots select').map(function (s) { return parseInt(s.value, 10); });
      qty = ids.length;
      var sum = ids.reduce(function (a, id) { return a + (byId[id] ? byId[id].price : 0); }, 0);
      var t = tierFor(qty);
      total = sum - (t ? t.d : 0);
      free = t && t.f;
    } else {
      qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
      total = unitPrice * qty;
    }
    if (!free && !(D.freeMin > 0 && total >= D.freeMin)) total += D.ship;
    var bt = $('#buyTotal');
    if (bt) bt.textContent = qty + ' ' + (D.combo ? D.unitWord : 'sản phẩm') + ', ' + fmt(total);
  }
  update();
})();

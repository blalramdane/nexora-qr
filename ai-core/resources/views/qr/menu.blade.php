<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f766e">
  <title>المنيو الإلكتروني</title>
  <style>
    :root{color-scheme:light;--ink:#142523;--muted:#6a7c79;--brand:#0f766e;--brand-dark:#115e59;--line:#e2e9e6;--paper:#f5f8f6;--white:#fff;--accent:#e8a34a}
    *{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:Tahoma,Arial,sans-serif}button,input,textarea{font:inherit}button{cursor:pointer}
    .hero{background:linear-gradient(130deg,#123e3a,#0f766e);color:#fff;padding:28px 18px 36px;border-radius:0 0 28px 28px}.wrap{max-width:1000px;margin:auto}.eyebrow{font-size:12px;letter-spacing:1.5px;color:#bde4d9;font-weight:800}.hero h1{font-size:clamp(25px,5vw,38px);margin:10px 0 8px}.hero p{margin:0;color:#d7eeea;font-size:14px;line-height:1.8}.content{padding:20px 14px 120px}.status{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:12px;margin:0 0 18px}.dot{width:8px;height:8px;border-radius:50%;background:#16a34a}
    .section-title{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:22px 0 12px}.section-title h2{font-size:18px;margin:0}.count{font-size:12px;color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,270px),1fr));gap:12px}
    .item{background:var(--white);border:1px solid var(--line);border-radius:18px;padding:16px;display:flex;flex-direction:column;min-height:160px;box-shadow:0 4px 16px #123e3a08}.category{font-size:11px;color:var(--brand);font-weight:800}.item h3{font-size:16px;margin:8px 0}.desc{font-size:12px;color:var(--muted);line-height:1.7;min-height:20px}.item-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:auto;padding-top:15px}.price{font-weight:900;font-size:17px}.btn{border:0;border-radius:12px;padding:11px 15px;background:var(--brand);color:white;font-weight:800}.btn:hover{background:var(--brand-dark)}.btn:disabled{opacity:.5;cursor:not-allowed}.btn-light{background:#e5f3ef;color:var(--brand-dark)}.btn-light:hover{background:#d1ebe4}
    .cart{position:fixed;z-index:5;bottom:0;right:0;left:0;background:#ffffffed;backdrop-filter:blur(12px);border-top:1px solid var(--line);padding:12px 14px calc(12px + env(safe-area-inset-bottom));box-shadow:0 -8px 30px #123e3a0b}.cart-inner{max-width:1000px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:12px}.cart-label{font-size:12px;color:var(--muted)}.cart-total{font-size:20px;font-weight:900;margin-top:3px}.cart-button{min-width:145px}.panel{margin-top:18px;background:white;border:1px solid var(--line);border-radius:20px;padding:18px}.panel h2{margin:0 0 14px;font-size:19px}.cart-line{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid var(--line)}.qty{display:flex;align-items:center;gap:8px}.qty button{border:1px solid var(--line);background:white;border-radius:8px;width:32px;height:32px;font-weight:900}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.field{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:800;color:#3b514d}.field input,.field textarea{border:1px solid var(--line);border-radius:11px;padding:12px;background:#fbfdfc;outline:none}.field input:focus,.field textarea:focus{border-color:var(--brand)}.notice{padding:12px 14px;border-radius:12px;margin:14px 0;font-size:13px;line-height:1.8}.error{background:#fff1f0;color:#a42e25}.success{background:#e8f7ee;color:#17633c}.empty{padding:24px;text-align:center;color:var(--muted);background:white;border:1px dashed var(--line);border-radius:16px}
    @media(min-width:700px){.hero{padding:38px 26px 46px}.content{padding:28px 20px 120px}.panel{padding:24px}}@media(prefers-reduced-motion:no-preference){.item{transition:transform .18s,box-shadow .18s}.item:hover{transform:translateY(-2px);box-shadow:0 12px 24px #123e3a0d}}
  </style>
</head>
<body>
  <header class="hero"><div class="wrap">
    <div class="eyebrow">DIGITAL MENU · NEXORA</div>
    <h1 id="branch-name">المنيو الإلكتروني</h1>
    <p>اختار طلبك بسهولة. الأسعار المعروضة من منيو الفرع، والطلب بيتراجع قبل تأكيده من الكاشير.</p>
  </div></header>
  <main class="wrap content">
    <div class="status"><span class="dot"></span><span id="menu-status">جاري تحميل المنيو...</span><span id="menu-version"></span></div>
    <div id="notice" aria-live="polite"></div>
    <div id="menu"><div class="empty">جاري تحميل الأصناف...</div></div>
    <section id="checkout" class="panel" hidden>
      <h2>مراجعة الطلب</h2>
      <div id="cart-lines"></div>
      <div class="section-title"><h2>بيانات الطلب</h2><span class="count">الطلب سفري</span></div>
      <form id="order-form">
        <div class="form-grid">
          <label class="field">الاسم (اختياري)<input name="customer_name" maxlength="120" autocomplete="name" placeholder="اسمك"></label>
          <label class="field">رقم الهاتف (اختياري)<input name="customer_phone" maxlength="40" inputmode="tel" autocomplete="tel" placeholder="01xxxxxxxxx"></label>
        </div>
        <label class="field" style="margin-top:12px">ملاحظات للطلب<textarea name="notes" maxlength="2000" rows="3" placeholder="أي تفاصيل إضافية"></textarea></label>
        <p class="count" style="line-height:1.8">الطلب غير مدفوع إلكترونيًا. هيتسجل كطلب منتظر مراجعة الفرع.</p>
        <button class="btn" style="width:100%;margin-top:12px" id="submit-order" type="submit">إرسال الطلب</button>
      </form>
    </section>
  </main>
  <footer class="cart"><div class="cart-inner"><div><div class="cart-label" id="cart-count">0 أصناف</div><div class="cart-total" id="cart-total">0 ج.م</div></div><button class="btn cart-button" id="review-cart" type="button" disabled>مراجعة الطلب</button></div></footer>
  <script>
    (() => {
      const slug = @json($slug);
      const menuEl = document.getElementById('menu');
      const noticeEl = document.getElementById('notice');
      const cart = new Map();
      let menuVersion = 0;
      let products = [];
      const money = (minor) => new Intl.NumberFormat('ar-EG', { style: 'currency', currency: 'EGP', maximumFractionDigits: 2 }).format(Number(minor || 0) / 100);
      const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
      const notice = (message, type) => { noticeEl.className = 'notice ' + type; noticeEl.textContent = message; };
      const getTotal = () => [...cart.entries()].reduce((sum, [id, qty]) => sum + Number(products.find((item) => item.source_product_id === id)?.price_minor || 0) * qty, 0);
      const getCount = () => [...cart.values()].reduce((sum, qty) => sum + qty, 0);

      function renderCart() {
        document.getElementById('cart-count').textContent = getCount() + ' صنف';
        document.getElementById('cart-total').textContent = money(getTotal());
        document.getElementById('review-cart').disabled = getCount() === 0;
        document.getElementById('checkout').hidden = getCount() === 0;
        const lines = [...cart.entries()].map(([id, qty]) => {
          const item = products.find((product) => product.source_product_id === id);
          if (!item) return '';
          return '<div class="cart-line"><div><strong>' + escapeHtml(item.name) + '</strong><div class="count">' + money(item.price_minor) + ' × ' + qty + '</div></div><div class="qty"><button type="button" data-qty="-1" data-id="' + escapeHtml(id) + '" aria-label="تقليل الكمية">−</button><strong>' + qty + '</strong><button type="button" data-qty="1" data-id="' + escapeHtml(id) + '" aria-label="زيادة الكمية">+</button></div></div>';
        }).join('');
        document.getElementById('cart-lines').innerHTML = lines || '<div class="empty">السلة فارغة</div>';
      }

      function renderMenu() {
        if (!products.length) {
          menuEl.innerHTML = '<div class="empty">المنيو لسه مفيهوش أصناف متاحة. جرّب تاني بعد شوية.</div>';
          return;
        }
        const categories = new Map();
        products.forEach((item) => {
          const name = item.category_name || 'أصناف متنوعة';
          if (!categories.has(name)) categories.set(name, []);
          categories.get(name).push(item);
        });
        menuEl.innerHTML = [...categories.entries()].map(([category, items]) =>
          '<section><div class="section-title"><h2>' + escapeHtml(category) + '</h2><span class="count">' + items.length + ' أصناف</span></div><div class="grid">' +
          items.map((item) => '<article class="item"><span class="category">' + escapeHtml(category) + '</span><h3>' + escapeHtml(item.name) + '</h3><p class="desc">' + escapeHtml(item.description || '') + '</p><div class="item-bottom"><span class="price">' + money(item.price_minor) + '</span><button class="btn btn-light" type="button" data-add="' + escapeHtml(item.source_product_id) + '">إضافة +</button></div></article>').join('') +
          '</div></section>'
        ).join('');
      }

      async function loadMenu() {
        try {
          const response = await fetch('/api/qr/v1/menus/' + encodeURIComponent(slug), { headers: { Accept: 'application/json' } });
          const data = await response.json();
          if (!response.ok) throw new Error(data.message || 'تعذر تحميل المنيو');
          products = Array.isArray(data.items) ? data.items : [];
          menuVersion = Number(data.menu_version || 0);
          document.getElementById('branch-name').textContent = data.branch?.name || 'المنيو الإلكتروني';
          document.getElementById('menu-status').textContent = 'المنيو جاهز للطلب';
          document.getElementById('menu-version').textContent = 'نسخة ' + menuVersion;
          renderMenu();
          renderCart();
        } catch (error) {
          document.getElementById('menu-status').textContent = 'تعذر الاتصال بالمنيو';
          menuEl.innerHTML = '<div class="empty">تعذر تحميل المنيو. تأكد من الاتصال وحاول مرة أخرى.</div>';
          notice(error.message || 'تعذر تحميل المنيو', 'error');
        }
      }

      menuEl.addEventListener('click', (event) => {
        const button = event.target.closest('[data-add]');
        if (!button) return;
        const id = button.getAttribute('data-add');
        cart.set(id, Math.min(99, (cart.get(id) || 0) + 1));
        noticeEl.textContent = '';
        noticeEl.className = '';
        renderCart();
      });

      document.getElementById('cart-lines').addEventListener('click', (event) => {
        const button = event.target.closest('[data-qty]');
        if (!button) return;
        const id = button.getAttribute('data-id');
        const next = (cart.get(id) || 0) + Number(button.getAttribute('data-qty'));
        if (next <= 0) cart.delete(id); else cart.set(id, Math.min(99, next));
        renderCart();
      });

      document.getElementById('review-cart').addEventListener('click', () => {
        document.getElementById('checkout').hidden = getCount() === 0;
        document.getElementById('checkout').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });

      document.getElementById('order-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (getCount() === 0) return;
        const form = new FormData(event.currentTarget);
        const button = document.getElementById('submit-order');
        button.disabled = true;
        button.textContent = 'جاري إرسال الطلب...';
        try {
          const payload = {
            source_order_uuid: crypto.randomUUID(),
            menu_version: menuVersion,
            fulfillment_type: 'takeaway',
            customer_name: String(form.get('customer_name') || '').trim() || null,
            customer_phone: String(form.get('customer_phone') || '').trim() || null,
            notes: String(form.get('notes') || '').trim() || null,
            items: [...cart.entries()].map(([source_product_id, quantity]) => ({ source_product_id, quantity })),
          };
          const response = await fetch('/api/qr/v1/menus/' + encodeURIComponent(slug) + '/orders', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const data = await response.json();
          if (!response.ok) {
            if (response.status === 409 && data.code === 'MENU_VERSION_STALE') await loadMenu();
            throw new Error(data.message || 'تعذر إرسال الطلب');
          }
          cart.clear();
          renderCart();
          event.currentTarget.reset();
          notice('تم تسجيل طلبك إلكترونيًا برقم ' + data.order_id + '. الطلب غير مدفوع وينتظر مراجعة الفرع.', 'success');
          window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
          notice(error.message || 'تعذر إرسال الطلب. حاول مرة أخرى.', 'error');
        } finally {
          button.disabled = false;
          button.textContent = 'إرسال الطلب';
        }
      });

      loadMenu();
    })();
  </script>
</body>
</html>

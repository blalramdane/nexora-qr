<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f766e">
  <title>لوحة إدارة QR | الجزيرة</title>
  <style>
    :root{--ink:#142523;--muted:#71817e;--brand:#0f766e;--brand-dark:#115e59;--line:#e1e9e6;--paper:#f4f7f6;--white:#fff}
    *{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:Tahoma,Arial,sans-serif}button,input{font:inherit}.wrap{max-width:1180px;margin:auto;padding:20px 14px 48px}
    .top{background:linear-gradient(130deg,#123e3a,#0f766e);color:white;padding:26px 18px;border-radius:22px;display:flex;justify-content:space-between;align-items:center;gap:16px}.eyebrow{color:#bde4d9;font-size:11px;font-weight:900;letter-spacing:1.5px}.top h1{font-size:clamp(23px,4vw,32px);margin:9px 0}.top p{font-size:13px;line-height:1.8;color:#d7eeea;margin:0}.top-actions{display:flex;gap:8px;flex-wrap:wrap}.btn{border:0;border-radius:11px;padding:11px 15px;background:var(--brand);color:#fff;font-weight:800;cursor:pointer}.btn-light{background:#ffffff1f;border:1px solid #ffffff40}.btn:disabled{opacity:.5;cursor:wait}
    .notice{padding:12px 14px;border-radius:12px;margin:14px 0;font-size:13px;line-height:1.8}.error{background:#fff1f0;color:#a42e25}.success{background:#e8f7ee;color:#17633c}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-top:18px}.metric{background:white;border:1px solid var(--line);border-radius:16px;padding:17px}.metric span{font-size:12px;color:var(--muted)}.metric strong{display:block;font-size:28px;margin-top:10px;font-weight:900}.section{margin-top:24px}.section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.section-head h2{font-size:19px;margin:0}.muted{font-size:12px;color:var(--muted);line-height:1.8}.branches{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}.branch{background:white;border:1px solid var(--line);border-radius:17px;padding:17px}.branch-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.branch h3{margin:0 0 6px;font-size:17px}.pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:6px 9px;font-size:11px;font-weight:900;white-space:nowrap}.online{background:#e5f7ec;color:#17633c}.offline{background:#fff2dd;color:#8b5a10}.branch-stats{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:15px}.mini{background:#f6f9f7;border-radius:10px;padding:10px}.mini span{display:block;color:var(--muted);font-size:11px}.mini strong{display:block;margin-top:5px;font-size:18px}.table-wrap{background:white;border:1px solid var(--line);border-radius:16px;overflow:hidden}.orders{width:100%;border-collapse:collapse}.orders th,.orders td{text-align:right;padding:13px 15px;border-bottom:1px solid var(--line);font-size:12px}.orders th{background:#f8fbf9;color:var(--muted);font-weight:800}.orders tr:last-child td{border-bottom:0}.status{border-radius:8px;padding:5px 7px;font-size:11px;font-weight:800;background:#eef2f1;color:#435450}.empty{padding:28px;text-align:center;color:var(--muted)}.login-wrap{max-width:430px;margin:7vh auto;background:white;border:1px solid var(--line);border-radius:22px;padding:24px;box-shadow:0 12px 40px #123e3a0c}.login-wrap h2{margin:0 0 8px}.field{display:flex;flex-direction:column;gap:7px;margin-top:15px;font-size:12px;font-weight:800}.field input{padding:13px;border:1px solid var(--line);border-radius:11px;outline:none}.field input:focus{border-color:var(--brand)}.footnote{font-size:11px;color:var(--muted);line-height:1.8;margin-top:15px}
    @media(max-width:650px){.top{align-items:flex-start;flex-direction:column}.wrap{padding:12px 10px 32px}.table-wrap{overflow-x:auto}.orders{min-width:680px}}
  </style>
</head>
<body>
  <main class="wrap">
    <section id="login" class="login-wrap" hidden>
      <div class="eyebrow" style="color:var(--brand)">AL JAZEERA · QR OPERATIONS</div>
      <h2 style="margin-top:10px">تسجيل دخول الإدارة</h2>
      <p class="muted">ادخل بحساب مالك أو مدير فرع مُجهّز من إدارة النظام.</p>
      <div id="login-notice" aria-live="polite"></div>
      <form id="login-form">
        <label class="field">البريد الإلكتروني<input name="email" type="email" required autocomplete="username" maxlength="255"></label>
        <label class="field">كلمة المرور<input name="password" type="password" required autocomplete="current-password" maxlength="255"></label>
        <button class="btn" style="width:100%;margin-top:20px" id="login-submit" type="submit">دخول آمن</button>
      </form>
      <p class="footnote">اللوحة دي بتعرض حالة مزامنة QR وطلبات المنيو. أرقام المبيعات الشاملة مش متاحة في المرحلة دي، والبيانات ممكن تتأخر لو الفرع غير متصل.</p>
    </section>

    <section id="dashboard" hidden>
      <header class="top">
        <div>
          <div class="eyebrow">AL JAZEERA · QR OPERATIONS</div>
          <h1>لوحة متابعة الفروع</h1>
          <p id="generated-at">متابعة الطلبات وحالة اتصال أجهزة الكاشير</p>
        </div>
        <div class="top-actions">
          <button class="btn btn-light" id="refresh">تحديث البيانات</button>
          <button class="btn btn-light" id="logout">تسجيل الخروج</button>
        </div>
      </header>
      <div id="dashboard-notice" aria-live="polite"></div>
      <div class="grid" id="metrics"></div>
      <section class="section">
        <div class="section-head"><h2>حالة الفروع</h2><span class="muted">الاتصال بيتحدد بآخر Heartbeat</span></div>
        <div id="branches" class="branches"></div>
      </section>
      <section class="section">
        <div class="section-head"><h2>أحدث طلبات QR</h2><span class="muted">آخر 50 طلب</span></div>
        <div class="table-wrap" id="orders-wrap"></div>
      </section>
      <p class="footnote">مصدر البيانات: Cloud QR Sync. الحالة «وصل للكاشير» تعني إنه محفوظ في Inbox، مش بالضرورة اتقبل كأوردر بيع. الطلبات غير المدفوعة لا تُحسب كمبيعات مؤكدة.</p>
    </section>
  </main>
  <script>
    (() => {
      const tokenKey = 'qr-owner-token';
      const loginEl = document.getElementById('login');
      const dashboardEl = document.getElementById('dashboard');
      const loginNotice = document.getElementById('login-notice');
      const dashboardNotice = document.getElementById('dashboard-notice');
      const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
      const money = (minor, currency) => new Intl.NumberFormat('ar-EG', { style: 'currency', currency: currency || 'EGP', maximumFractionDigits: 2 }).format(Number(minor || 0) / 100);
      const date = (value) => value ? new Date(value).toLocaleString('ar-EG', { dateStyle: 'short', timeStyle: 'short' }) : 'لم يتصل بعد';
      const statusLabel = (status) => ({pending_delivery:'بانتظار الاتصال',delivering:'جاري التسليم',received:'وصل للكاشير',imported:'اتقبل في الكاشير',rejected:'مرفوض'}[status] || status);
      const notice = (element, message, type) => { element.className = message ? 'notice ' + type : ''; element.textContent = message || ''; };

      async function api(path, options = {}) {
        const token = sessionStorage.getItem(tokenKey);
        const response = await fetch(path, {
          ...options,
          headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(token ? { Authorization: 'Bearer ' + token } : {}),
            ...(options.headers || {}),
          },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          if (response.status === 401 && path.includes('/owner/')) sessionStorage.removeItem(tokenKey);
          throw new Error(data.message || 'تعذر إكمال الطلب');
        }
        return data;
      }

      function showLogin() {
        dashboardEl.hidden = true;
        loginEl.hidden = false;
      }

      function showDashboard(data) {
        loginEl.hidden = true;
        dashboardEl.hidden = false;
        const summary = data.summary || {};
        const metrics = [
          ['الفروع', summary.branches_count],
          ['متصلة حاليًا', summary.online_branches_count],
          ['بانتظار الاتصال', summary.pending_qr_orders_count],
          ['وصلت للكاشير', summary.received_by_cashier_count],
          ['اتقبلت اليوم', summary.imported_today_count],
          ['اترفضت اليوم', summary.rejected_today_count],
        ];
        document.getElementById('metrics').innerHTML = metrics.map(([label, value]) =>
          '<article class="metric"><span>' + escapeHtml(label) + '</span><strong>' + Number(value || 0) + '</strong></article>'
        ).join('');
        document.getElementById('generated-at').textContent = 'آخر تحديث: ' + date(data.generated_at) + ' · بيانات QR وليست تقرير مبيعات شامل';
        const branches = Array.isArray(data.branches) ? data.branches : [];
        document.getElementById('branches').innerHTML = branches.length ? branches.map((branch) => {
          const online = branch.connection_status === 'online';
          return '<article class="branch"><div class="branch-head"><div><h3>' + escapeHtml(branch.name) + '</h3><p class="muted">' + escapeHtml(branch.code) + ' · ' + escapeHtml(branch.slug) + '</p></div><span class="pill ' + (online ? 'online' : 'offline') + '">' + (online ? '● متصل' : '● غير متصل / بيانات قديمة') + '</span></div><p class="muted">آخر اتصال: ' + escapeHtml(date(branch.last_seen_at)) + ' · Menu v' + Number(branch.menu_version || 0) + '</p><div class="branch-stats"><div class="mini"><span>بانتظار التوصيل</span><strong>' + Number(branch.pending_orders_count || 0) + '</strong></div><div class="mini"><span>داخل Inbox</span><strong>' + Number(branch.received_orders_count || 0) + '</strong></div><div class="mini"><span>اتقبل اليوم</span><strong>' + Number(branch.imported_today_count || 0) + '</strong></div><div class="mini"><span>اترفض اليوم</span><strong>' + Number(branch.rejected_today_count || 0) + '</strong></div></div></article>';
        }).join('') : '<div class="empty">مفيش فروع متسجلة لحد دلوقتي.</div>';

        const orders = Array.isArray(data.recent_qr_orders) ? data.recent_qr_orders : [];
        document.getElementById('orders-wrap').innerHTML = orders.length
          ? '<table class="orders"><thead><tr><th>رقم QR</th><th>العميل</th><th>الحالة</th><th>الإجمالي</th><th>تاريخ الطلب</th><th>ملاحظة</th></tr></thead><tbody>' + orders.map((order) => '<tr><td>#' + Number(order.id) + '</td><td>' + escapeHtml(order.customer_name || 'عميل QR') + '</td><td><span class="status">' + escapeHtml(statusLabel(order.status)) + '</span></td><td>' + money(order.subtotal_minor, order.currency) + '</td><td>' + escapeHtml(date(order.submitted_at)) + '</td><td>' + escapeHtml(order.resolution_note || '—') + '</td></tr>').join('') + '</tbody></table>'
          : '<div class="empty">لسه مفيش طلبات QR.</div>';
      }

      async function loadDashboard() {
        document.getElementById('refresh').disabled = true;
        notice(dashboardNotice, '', '');
        try {
          const data = await api('/api/qr/v1/owner/dashboard');
          showDashboard(data);
        } catch (error) {
          if (!sessionStorage.getItem(tokenKey)) showLogin();
          notice(dashboardNotice, error.message || 'تعذر تحميل لوحة الإدارة', 'error');
        } finally {
          document.getElementById('refresh').disabled = false;
        }
      }

      document.getElementById('login-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.getElementById('login-submit');
        const form = new FormData(event.currentTarget);
        button.disabled = true;
        notice(loginNotice, '', '');
        try {
          const data = await api('/api/qr/v1/owner/login', {
            method: 'POST',
            body: JSON.stringify({ email: String(form.get('email') || ''), password: String(form.get('password') || '') }),
          });
          sessionStorage.setItem(tokenKey, data.token);
          event.currentTarget.reset();
          await loadDashboard();
        } catch (error) {
          notice(loginNotice, error.message || 'تعذر تسجيل الدخول', 'error');
        } finally {
          button.disabled = false;
        }
      });

      document.getElementById('refresh').addEventListener('click', () => void loadDashboard());
      document.getElementById('logout').addEventListener('click', async () => {
        try { await api('/api/qr/v1/owner/logout', { method: 'POST', body: '{}' }); } catch {}
        sessionStorage.removeItem(tokenKey);
        showLogin();
      });

      if (sessionStorage.getItem(tokenKey)) void loadDashboard(); else showLogin();
      window.setInterval(() => {
        if (!dashboardEl.hidden && sessionStorage.getItem(tokenKey)) void loadDashboard();
      }, 30000);
    })();
  </script>
</body>
</html>

import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import PwaInstallPrompt from '../../Components/PwaInstallPrompt';

type Order = {
    id: number; order_number: string; customer_name: string; customer_phone: string; source?: string; payment_method?: string; payment_status?: 'unpaid' | 'paid' | 'refunded';
    notes?: string | null; status: string; total: string; subtotal?: string; delivery_fee?: string; fulfillment_type?: 'pickup' | 'delivery' | 'dine_in'; delivery_address?: string | null; restaurant_table_id?: number | null; restaurant_table_name?: string | null; restaurant_table?: { id: number; name: string } | null; items_count: number;
    coupon_code?: string | null; discount_amount?: string | number;
    created_at: string; menu?: { name: string } | null;
    items: Array<{ id: number; product_name: string; variant_name?: string | null; modifiers?: Array<{id: number; name: string; price: number}> | null; quantity: number; line_total: string }>;
};
type CatalogProduct = { id: number; name: string; price: number };
type CatalogMenu = { id: number; name: string; products: CatalogProduct[] };
type ManualOrderItem = { product_id: string; quantity: number };
type SourceSummary = { source: string; order_count: number; completed_revenue: number };

const statuses = [
    ['new', 'جديد'], ['confirmed', 'مؤكد'], ['preparing', 'قيد التجهيز'],
    ['ready', 'جاهز'], ['completed', 'مكتمل'], ['cancelled', 'ملغي'],
] as const;
const nextStatuses: Record<string, string[]> = {
    new: ['confirmed', 'cancelled'],
    confirmed: ['preparing', 'cancelled'],
    preparing: ['ready', 'cancelled'],
    ready: ['completed', 'cancelled'],
    completed: [],
    cancelled: [],
};
const money = (value: string | number) => `${Number(value).toFixed(2)} ج.م`;
const sourceLabels: Record<string, string> = { qr_menu: 'منيو NEXORA', whatsapp: 'واتساب', phone: 'مكالمة هاتفية', facebook: 'فيسبوك', instagram: 'إنستجرام', tiktok: 'تيك توك', external_app: 'تطبيق خارجي', manual: 'إدخال يدوي' };
const fulfillmentLabels: Record<string, string> = { delivery: 'توصيل', pickup: 'استلام من المطعم', dine_in: 'داخل المطعم' };
const statusStyles: Record<string, string> = { new: 'bg-sky-50 text-sky-700 ring-sky-200', confirmed: 'bg-indigo-50 text-indigo-700 ring-indigo-200', preparing: 'bg-amber-50 text-amber-800 ring-amber-200', ready: 'bg-violet-50 text-violet-700 ring-violet-200', completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200', cancelled: 'bg-rose-50 text-rose-700 ring-rose-200' };

export default function OrdersIndex({ orders, restaurantContact, salesSummary, manualOrderCatalog = [], sourceSummary = [], dashboardOrderNotificationsEnabled = true, ordersBaseUrl = '/orders', settingsUrl = '/settings/orders', backUrl = '/dashboard', ownerContext = false }: { orders: Order[]; restaurantContact: { name: string; whatsappPhone?: string | null }; salesSummary: { completed_count: number; completed_revenue: number; completed_discounts: number; today_revenue: number }; manualOrderCatalog?: CatalogMenu[]; sourceSummary?: SourceSummary[]; dashboardOrderNotificationsEnabled?: boolean; ordersBaseUrl?: string; settingsUrl?: string; backUrl?: string; ownerContext?: boolean }) {
    const [lastUpdated, setLastUpdated] = useState(() => new Date());
    const [refreshing, setRefreshing] = useState(false);
    const [notice, setNotice] = useState('');
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [fulfillmentFilter, setFulfillmentFilter] = useState('all');
    const [sourceFilter, setSourceFilter] = useState('all');
    const [manualOpen, setManualOpen] = useState(false);
    const [manualMenuId, setManualMenuId] = useState('');
    const [manualName, setManualName] = useState('');
    const [manualPhone, setManualPhone] = useState('');
    const [manualSource, setManualSource] = useState('manual');
    const [manualFulfillment, setManualFulfillment] = useState('pickup');
    const [manualAddress, setManualAddress] = useState('');
    const [manualPayment, setManualPayment] = useState('cash');
    const [manualPaymentStatus, setManualPaymentStatus] = useState('unpaid');
    const [manualNotes, setManualNotes] = useState('');
    const [manualItems, setManualItems] = useState<ManualOrderItem[]>([{ product_id: '', quantity: 1 }]);
    const [manualError, setManualError] = useState('');
    const [manualSaving, setManualSaving] = useState(false);
    const [updatingId, setUpdatingId] = useState<number | null>(null);
    const [updatingPaymentId, setUpdatingPaymentId] = useState<number | null>(null);
    const [notificationPermission, setNotificationPermission] = useState<NotificationPermission | 'unsupported'>('unsupported');
    const [printingOrderId, setPrintingOrderId] = useState<number | null>(null);
    const previousNewestOrder = useRef<number | null>(orders[0]?.id ?? null);

    const refreshOrders = () => {
        if (document.visibilityState !== 'visible') return;
        setRefreshing(true);
        router.reload({
            only: ['orders'],
            onSuccess: () => setLastUpdated(new Date()),
            onFinish: () => setRefreshing(false),
        });
    };

    useEffect(() => {
        const newestId = orders[0]?.id ?? null;
        if (dashboardOrderNotificationsEnabled && previousNewestOrder.current !== null && newestId !== null && newestId > previousNewestOrder.current) {
            const newestOrder = orders[0];
            setNotice(`وصل طلب جديد من ${newestOrder.customer_name} — ${newestOrder.order_number}`);
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('طلب جديد - NEXORA QR', { body: `${newestOrder.customer_name} · ${money(newestOrder.total)}` });
            }
        }
        if (newestId !== null) previousNewestOrder.current = newestId;
    }, [orders, dashboardOrderNotificationsEnabled]);

    useEffect(() => {
        if ('Notification' in window) setNotificationPermission(Notification.permission);
        const timer = window.setInterval(refreshOrders, 15000);
        return () => window.clearInterval(timer);
    }, []);

    const visibleOrders = useMemo(() => orders.filter(order => {
        const query = search.trim().toLocaleLowerCase();
        const matchesSearch = !query || [order.order_number, order.customer_name, order.customer_phone, order.delivery_address ?? '', order.restaurant_table_name ?? order.restaurant_table?.name ?? ''].some(value => value.toLocaleLowerCase().includes(query));
        return matchesSearch && (statusFilter === 'all' || order.status === statusFilter) && (fulfillmentFilter === 'all' || (order.fulfillment_type ?? 'pickup') === fulfillmentFilter) && (sourceFilter === 'all' || (order.source ?? 'qr_menu') === sourceFilter);
    }), [orders, search, statusFilter, fulfillmentFilter, sourceFilter]);
    const newCount = orders.filter(order => order.status === 'new').length;
    const activeCount = orders.filter(order => ['confirmed', 'preparing', 'ready'].includes(order.status)).length;
    const selectedMenu = manualOrderCatalog.find(menu => String(menu.id) === manualMenuId);
    const manualTotal = manualItems.reduce((sum, item) => sum + (selectedMenu?.products.find(product => String(product.id) === item.product_id)?.price ?? 0) * item.quantity, 0);
    const submitManualOrder = () => {
        setManualError('');
        if (!manualMenuId || !manualName.trim() || !manualPhone.trim() || !manualItems.length || manualItems.some(item => !item.product_id || item.quantity < 1)) {
            setManualError('كمّل بيانات العميل والمنيو واختار منتجًا وكمية صحيحة لكل سطر.');
            return;
        }
        if (manualFulfillment === 'delivery' && !manualAddress.trim()) {
            setManualError('اكتب عنوان التوصيل.');
            return;
        }
        setManualSaving(true);
        router.post(`${ordersBaseUrl}/manual`, {
            menu_id: Number(manualMenuId), customer_name: manualName, customer_phone: manualPhone,
            source: manualSource, fulfillment_type: manualFulfillment, delivery_address: manualAddress,
            payment_method: manualPayment, payment_status: manualPaymentStatus, notes: manualNotes,
            items: manualItems.map(item => ({ product_id: Number(item.product_id), quantity: item.quantity })),
        }, { preserveScroll: true, onError: errors => setManualError(Object.values(errors).flat().join(' ')), onSuccess: () => { setManualOpen(false); setManualName(''); setManualPhone(''); setManualAddress(''); setManualNotes(''); setManualItems([{ product_id: '', quantity: 1 }]); }, onFinish: () => setManualSaving(false) });
    };
    const enableNotifications = async () => {
        if (!('Notification' in window)) {
            setNotificationPermission('unsupported');
            setNotice('المتصفح الحالي لا يدعم إشعارات سطح المكتب. التنبيهات داخل لوحة الطلبات ستظل تعمل.');
            return;
        }
        const permission = await Notification.requestPermission();
        setNotificationPermission(permission);
        setNotice(permission === 'granted' ? 'تم تفعيل إشعارات الطلبات الجديدة على هذا المتصفح.' : 'الإشعارات غير مفعلة. يمكنك السماح بها من إعدادات الموقع في المتصفح.');
    };
    const printKitchenTicket = (orderId: number) => {
        setPrintingOrderId(orderId);
        window.setTimeout(() => window.print(), 50);
    };
    useEffect(() => {
        const afterPrint = () => setPrintingOrderId(null);
        window.addEventListener('afterprint', afterPrint);
        return () => window.removeEventListener('afterprint', afterPrint);
    }, []);
    const exportOrdersCsv = () => {
        const escapeCsv = (value: unknown) => `"${String(value ?? '').replace(/"/g, '""')}"`;
        const rows = [
            ['رقم الطلب', 'تاريخ الطلب', 'العميل', 'الهاتف', 'الحالة', 'مصدر الطلب', 'حالة الدفع', 'طريقة الدفع', 'طريقة الاستلام', 'الترابيزة', 'العنوان', 'عدد القطع', 'قيمة المنتجات', 'كود الخصم', 'قيمة الخصم', 'التوصيل', 'الإجمالي', 'المنتجات', 'ملاحظات'],
            ...visibleOrders.map(order => [
                order.order_number, new Date(order.created_at).toLocaleString('ar-EG'), order.customer_name, order.customer_phone,
                statuses.find(([value]) => value === order.status)?.[1] ?? order.status,
                sourceLabels[order.source ?? 'qr_menu'] ?? order.source ?? 'منيو NEXORA',
                ({ unpaid: 'غير مدفوع', paid: 'مدفوع', refunded: 'مسترد' } as Record<string, string>)[order.payment_status ?? 'unpaid'] ?? 'غير مدفوع',
                ({ cash: 'نقدًا', cash_on_delivery: 'نقدًا عند الاستلام', card: 'بطاقة', wallet: 'محفظة إلكترونية', bank_transfer: 'تحويل بنكي' } as Record<string, string>)[order.payment_method ?? 'cash_on_delivery'] ?? order.payment_method ?? '',
                fulfillmentLabels[order.fulfillment_type ?? 'pickup'] ?? 'استلام من المطعم', order.restaurant_table_name ?? order.restaurant_table?.name ?? '', order.delivery_address ?? '', order.items_count,
                order.subtotal ?? '', order.coupon_code ?? '', order.discount_amount ?? 0, order.delivery_fee ?? '', order.total,
                order.items.map(item => `${item.product_name}${item.variant_name ? ` - ${item.variant_name}` : ''} × ${item.quantity}${item.modifiers?.length ? ` (${item.modifiers.map(modifier => modifier.name).join('، ')})` : ''}`).join(' | '),
                order.notes ?? '',
            ]),
        ];
        const csv = '\uFEFF' + rows.map(row => row.map(escapeCsv).join(',')).join('\r\n');
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `nexora-orders-${new Date().toISOString().slice(0, 10)}.csv`;
        anchor.click();
        URL.revokeObjectURL(url);
    };

    return <main dir="rtl" className="min-h-screen bg-[#f4f6fa] px-4 py-6 text-slate-950 sm:px-8">
        <Head title="طلبات المطعم" />
        <style>{`@media print { body * { visibility: hidden !important; } .nx-kitchen-ticket { display:none !important; } .order-card[data-printing="true"] .nx-kitchen-ticket, .order-card[data-printing="true"] .nx-kitchen-ticket * { visibility: visible !important; } .order-card[data-printing="true"] .nx-kitchen-ticket { display: block !important; position: absolute; inset: 0 auto auto 0; width: 100%; padding: 8mm; color: #000 !important; background: #fff !important; font: 12pt/1.45 Arial, sans-serif; } .nx-kitchen-ticket h2 { font-size: 20pt; margin: 0 0 4mm; } .nx-kitchen-ticket p { margin: 2mm 0; } .nx-kitchen-ticket .ticket-line { display:flex; justify-content:space-between; gap:8mm; border-bottom:1px dashed #aaa; padding:3mm 0; } @page { margin: 5mm; size: auto; } }`}</style>
        <div className="mx-auto max-w-7xl">
            <header className="mb-7 flex flex-wrap items-center justify-between gap-4 rounded-[28px] bg-[#0b1020] p-6 text-white shadow-xl sm:p-8">
                <div><p className="text-[10px] font-black tracking-[.3em] text-amber-300">NEXORA / OPERATIONS</p><h1 className="mt-3 text-3xl font-black sm:text-4xl">طلبات المطعم</h1><p className="mt-2 text-sm text-slate-300">إدارة الطلبات ومتابعة تنفيذها من مكان واحد.</p></div>
                <div className="flex flex-wrap items-center gap-2">
                    <button type="button" onClick={enableNotifications} className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10">{notificationPermission === 'granted' ? '✓ إشعارات مفعلة' : 'تفعيل إشعارات الطلبات'}</button>
                    <button type="button" onClick={exportOrdersCsv} disabled={!visibleOrders.length} className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10 disabled:opacity-40">تصدير CSV</button>
                    <button type="button" onClick={() => setManualOpen(value => !value)} disabled={!manualOrderCatalog.length} className="rounded-xl bg-white px-4 py-3 text-sm font-black text-slate-950 transition hover:bg-amber-100 disabled:opacity-50">＋ تسجيل طلب يدوي</button>
                    <button type="button" onClick={refreshOrders} disabled={refreshing} className="rounded-xl bg-amber-300 px-4 py-3 text-sm font-black text-slate-950 transition hover:bg-amber-200 disabled:opacity-60">{refreshing ? 'جارٍ التحديث…' : '↻ تحديث الطلبات'}</button>
                    <Link href={settingsUrl} className="rounded-xl border border-emerald-300/40 bg-emerald-300/10 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-300/20">⚙ قنوات استقبال الطلبات</Link>
                    <Link href={backUrl} className="rounded-xl border border-white/20 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10">{ownerContext ? 'استوديو المنيوهات' : 'لوحة التحكم'}</Link>
                    {!ownerContext && <><Link href="/delivery-management" className="rounded-xl border border-emerald-300/40 bg-emerald-300/10 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-300/20">🛵 إدارة الدليفري</Link>
                    <Link href="/tables" className="rounded-xl border border-cyan-300/40 bg-cyan-300/10 px-4 py-3 text-sm font-bold text-white transition hover:bg-cyan-300/20">▦ ترابيزات وQR</Link></>}
                </div>
            </header>
            <PwaInstallPrompt />
            <section className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {[{label:'إجمالي الطلبات',value:orders.length,hint:'آخر 100 طلب',color:'text-slate-950'},{label:'طلبات جديدة',value:newCount,hint:'تحتاج مراجعة',color:'text-sky-700'},{label:'قيد التنفيذ',value:activeCount,hint:'مؤكد · تجهيز · جاهز',color:'text-amber-700'},{label:'مبيعات مكتملة',value:money(salesSummary.completed_revenue),hint:`${salesSummary.completed_count} طلب مكتمل`,color:'text-emerald-700'},{label:'مبيعات اليوم',value:money(salesSummary.today_revenue),hint:'طلبات مكتملة اليوم',color:'text-blue-700'},{label:'خصومات العروض',value:money(salesSummary.completed_discounts),hint:'خصومات الطلبات المكتملة',color:'text-violet-700'}].map((stat,index)=><article key={stat.label} className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><p className="text-sm font-bold text-slate-500">{stat.label}</p><span className={`grid h-9 w-9 place-items-center rounded-xl ${['bg-slate-100','bg-sky-50','bg-amber-50','bg-emerald-50','bg-blue-50','bg-violet-50'][index]} ${stat.color}`}>{['▤','✦','◷','↗','◷','％'][index]}</span></div><p className={`mt-4 text-2xl font-black ${stat.color}`}>{stat.value}</p><p className="mt-1 text-xs text-slate-400">{stat.hint}</p></article>)}
            </section>
            <section className="mb-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div className="mb-4"><h2 className="text-lg font-black">أداء مصادر الطلبات</h2><p className="mt-1 text-sm text-slate-500">عدد الطلبات المسجلة حسب القناة، والإيراد من الطلبات المكتملة فقط.</p></div>
                {sourceSummary.length ? <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{sourceSummary.map(row => <article key={row.source} className="rounded-xl border border-slate-100 bg-slate-50 p-4"><p className="text-sm font-bold text-slate-500">{sourceLabels[row.source] ?? row.source}</p><p className="mt-2 text-2xl font-black">{row.order_count}</p><p className="mt-1 text-xs text-slate-500">طلب مسجل</p><p className="mt-3 text-sm font-black text-emerald-700">{money(row.completed_revenue)}</p><p className="text-xs text-slate-500">إيراد مكتمل</p></article>)}</div> : <p className="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">لسه مفيش بيانات كفاية لعرض أداء المصادر.</p>}
            </section>
            {manualOpen && <section className="mb-6 rounded-2xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6">
                <div className="mb-4 flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-xl font-black">تسجيل طلب جديد</h2><p className="mt-1 text-sm text-slate-500">طلب تليفون أو واتساب أو قناة أخرى — هيتضاف لنفس صندوق الطلبات.</p></div><button type="button" onClick={() => setManualOpen(false)} className="rounded-lg border px-3 py-2 text-sm font-bold">إغلاق</button></div>
                {manualOrderCatalog.length === 0 ? <p className="rounded-xl bg-amber-50 p-4 text-sm">انشر منيو وأضف منتجات متاحة علشان تقدر تسجل طلب يدوي.</p> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <label className="text-sm font-bold">المنيو المنشورة<select value={manualMenuId} onChange={event => { setManualMenuId(event.target.value); setManualItems([{ product_id: '', quantity: 1 }]); }} className="mt-2 w-full rounded-xl border border-slate-200 p-3"><option value="">اختار المنيو</option>{manualOrderCatalog.map(menu => <option key={menu.id} value={menu.id}>{menu.name}</option>)}</select></label>
                    <label className="text-sm font-bold">اسم العميل<input value={manualName} onChange={event => setManualName(event.target.value)} maxLength={120} className="mt-2 w-full rounded-xl border border-slate-200 p-3" placeholder="اسم العميل" /></label>
                    <label className="text-sm font-bold">رقم الهاتف<input value={manualPhone} onChange={event => setManualPhone(event.target.value)} maxLength={30} className="mt-2 w-full rounded-xl border border-slate-200 p-3" placeholder="01xxxxxxxxx" /></label>
                    <label className="text-sm font-bold">مصدر الطلب<select value={manualSource} onChange={event => setManualSource(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 p-3"><option value="manual">إدخال يدوي</option><option value="whatsapp">واتساب</option><option value="phone">مكالمة هاتفية</option><option value="facebook">فيسبوك</option><option value="instagram">إنستجرام</option><option value="tiktok">تيك توك</option><option value="external_app">تطبيق خارجي</option></select></label>
                    <label className="text-sm font-bold">طريقة الاستلام<select value={manualFulfillment} onChange={event => setManualFulfillment(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 p-3"><option value="pickup">استلام من المطعم</option><option value="delivery">توصيل</option></select></label>
                    <label className="text-sm font-bold">طريقة الدفع<select value={manualPayment} onChange={event => setManualPayment(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 p-3"><option value="cash">نقدًا</option><option value="cash_on_delivery">نقدًا عند الاستلام</option><option value="card">بطاقة</option><option value="wallet">محفظة إلكترونية</option><option value="bank_transfer">تحويل بنكي</option></select></label>
                    <label className="text-sm font-bold">حالة الدفع<select value={manualPaymentStatus} onChange={event => setManualPaymentStatus(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 p-3"><option value="unpaid">غير مدفوع</option><option value="paid">مدفوع</option><option value="refunded">مسترد</option></select></label>
                    {manualFulfillment === 'delivery' && <label className="text-sm font-bold md:col-span-2">عنوان التوصيل<input value={manualAddress} onChange={event => setManualAddress(event.target.value)} maxLength={1000} className="mt-2 w-full rounded-xl border border-slate-200 p-3" placeholder="العنوان بالتفصيل" /></label>}
                    <label className="text-sm font-bold md:col-span-2 xl:col-span-3">ملاحظات الطلب<textarea value={manualNotes} onChange={event => setManualNotes(event.target.value)} maxLength={2000} className="mt-2 w-full rounded-xl border border-slate-200 p-3" placeholder="ملاحظات للمطبخ أو التوصيل (اختياري)" rows={2} /></label>
                    <div className="space-y-3 md:col-span-2 xl:col-span-3"><div className="flex items-center justify-between"><h3 className="font-black">المنتجات</h3><button type="button" onClick={() => setManualItems(items => [...items, { product_id: '', quantity: 1 }])} disabled={!selectedMenu || manualItems.length >= 50} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold disabled:opacity-40">＋ إضافة منتج</button></div>
                        {manualItems.map((item, index) => <div key={index} className="grid gap-2 sm:grid-cols-[1fr_120px_auto]"><select aria-label={`المنتج ${index + 1}`} value={item.product_id} onChange={event => setManualItems(items => items.map((current, i) => i === index ? { ...current, product_id: event.target.value } : current))} className="rounded-xl border border-slate-200 p-3"><option value="">اختار منتجًا من المنيو</option>{(selectedMenu?.products ?? []).map(product => <option key={product.id} value={product.id}>{product.name} — {money(product.price)}</option>)}</select><input aria-label={`الكمية ${index + 1}`} type="number" min={1} max={99} value={item.quantity} onChange={event => setManualItems(items => items.map((current, i) => i === index ? { ...current, quantity: Math.max(1, Math.min(99, Number(event.target.value) || 1)) } : current))} className="rounded-xl border border-slate-200 p-3" /><button type="button" disabled={manualItems.length === 1} onClick={() => setManualItems(items => items.filter((_, i) => i !== index))} className="rounded-xl border border-rose-200 px-3 py-2 text-sm font-bold text-rose-700 disabled:opacity-30">حذف</button></div>)}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 md:col-span-2 xl:col-span-3"><div><p className="text-xs font-bold text-slate-500">إجمالي المنتجات التقديري</p><p className="mt-1 text-2xl font-black">{money(manualTotal)}</p><p className="mt-1 text-xs text-slate-500">السيرفر بيحسب الإجمالي النهائي ورسوم التوصيل من إعدادات المطعم.</p></div><button type="button" onClick={submitManualOrder} disabled={manualSaving || !manualOrderCatalog.length} className="rounded-xl bg-[#0b1020] px-6 py-3 font-black text-white disabled:opacity-50">{manualSaving ? 'جارٍ تسجيل الطلب…' : 'تسجيل الطلب'}</button></div>
                    {manualError && <p role="alert" className="rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700 md:col-span-2 xl:col-span-3">{manualError}</p>}
                </div>}
            </section>}
            <section className="mb-5 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label className="relative block"><span className="sr-only">بحث عن طلب</span><span className="pointer-events-none absolute right-4 top-3 text-slate-400">⌕</span><input value={search} onChange={event=>setSearch(event.target.value)} placeholder="ابحث برقم الطلب أو اسم العميل أو الهاتف أو العنوان…" className="w-full rounded-xl border border-slate-200 bg-slate-50 py-3 pr-10 pl-3 text-sm outline-none transition focus:border-slate-400 focus:bg-white focus:ring-4 focus:ring-slate-100" /></label>
                    <select aria-label="تصفية حسب الحالة" value={statusFilter} onChange={event=>setStatusFilter(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold"><option value="all">كل الحالات</option>{statuses.map(([value,label])=><option key={value} value={value}>{label}</option>)}</select>
                    <select aria-label="تصفية حسب الاستلام" value={fulfillmentFilter} onChange={event=>setFulfillmentFilter(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold"><option value="all">كل طرق الاستلام</option><option value="delivery">التوصيل</option><option value="pickup">استلام من المطعم</option><option value="dine_in">داخل المطعم / ترابيزة</option></select>
                    <select aria-label="تصفية حسب مصدر الطلب" value={sourceFilter} onChange={event=>setSourceFilter(event.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold"><option value="all">كل مصادر الطلبات</option>{Object.entries(sourceLabels).map(([value,label])=><option key={value} value={value}>{label}</option>)}</select>
                </div>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs"><span className="font-bold text-slate-600">عرض {visibleOrders.length} من {orders.length} طلب</span><span className="text-slate-400">آخر تحديث {lastUpdated.toLocaleTimeString('ar-EG')}</span></div>
            </section>
            {notice && <div role="status" className="mb-4 flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-900"><span>{notice}</span><button type="button" onClick={() => setNotice('')} aria-label="إغلاق التنبيه">إغلاق</button></div>}
            <div className="grid gap-4 xl:grid-cols-2">
                {visibleOrders.map(order => <article key={order.id} data-printing={printingOrderId === order.id ? 'true' : undefined} className="order-card overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:border-slate-300 hover:shadow-md">
                    <div className="h-1 bg-gradient-to-l from-amber-300 via-amber-100 to-transparent" />
                    <section className="nx-kitchen-ticket hidden" style={{ display: printingOrderId === order.id ? 'block' : 'none' }}>
                        <p>NEXORA QR · تذكرة المطبخ</p><h2>طلب {order.order_number}</h2>
                        <p><strong>العميل:</strong> {order.customer_name}</p><p><strong>وقت الطلب:</strong> {new Date(order.created_at).toLocaleString('ar-EG')}</p>
                        <p><strong>طريقة الاستلام:</strong> {fulfillmentLabels[order.fulfillment_type ?? 'pickup'] ?? 'استلام من المطعم'}</p>
                        {order.fulfillment_type === 'dine_in' && (order.restaurant_table_name || order.restaurant_table?.name) && <p><strong>الترابيزة:</strong> {order.restaurant_table_name ?? order.restaurant_table?.name}</p>}
                        {order.fulfillment_type === 'delivery' && order.delivery_address && <p><strong>العنوان:</strong> {order.delivery_address}</p>}
                        <hr />{order.items.map(item => <div className="ticket-line" key={item.id}><div><strong>{item.quantity} × {item.product_name}</strong>{item.variant_name && <p>الحجم: {item.variant_name}</p>}{item.modifiers?.map(modifier => <p key={modifier.id}>+ {modifier.name}</p>)}</div><span>{money(item.line_total)}</span></div>)}
                        {order.notes && <p><strong>ملاحظات مهمة:</strong> {order.notes}</p>}
                        {order.coupon_code && <p>الكوبون: {order.coupon_code} · الخصم {money(order.discount_amount ?? 0)}</p>}
                        <hr /><p><strong>إجمالي الطلب: {money(order.total)}</strong></p><p>الحالة: {statuses.find(([value]) => value === order.status)?.[1] ?? order.status}</p>
                    </section>
                    <div className="p-4 sm:p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div><p className="text-[11px] font-black tracking-wide text-slate-400">{order.order_number} · {order.menu?.name ?? 'طلب مباشر'}</p><span className="mt-2 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-800">المصدر: {sourceLabels[order.source ?? 'qr_menu'] ?? order.source}</span><h2 className="mt-2 text-xl font-black">{order.customer_name}</h2><a className="mt-1 inline-block text-sm font-semibold text-blue-700 underline" href={`tel:${order.customer_phone}`}>{order.customer_phone}</a><p className="mt-2 text-xs text-slate-400">{new Date(order.created_at).toLocaleString('ar-EG')}</p></div>
                        <div className="text-left"><p className="text-xl font-black">{money(order.total)}</p><span className={`mt-2 inline-flex rounded-full px-3 py-1 text-[11px] font-black ring-1 ring-inset ${statusStyles[order.status] ?? 'bg-slate-100 text-slate-700 ring-slate-200'}`}>{statuses.find(([value])=>value===order.status)?.[1] ?? order.status}</span></div>
                    </div>
                    <div className="my-4 divide-y divide-slate-100 rounded-xl bg-slate-50/80 px-3">
                        {order.items.map(item => <div key={item.id} className="flex justify-between gap-3 py-2 text-sm"><span>{item.product_name}{item.variant_name ? ` — ${item.variant_name}` : ''} × {item.quantity}{item.modifiers?.length ? <span className="mt-1 block text-xs text-slate-500">الإضافات: {item.modifiers.map(modifier => modifier.name).join('، ')}</span> : null}</span><strong>{money(item.line_total)}</strong></div>)}
                    </div>
                    <div className="mb-3 flex flex-wrap gap-2 text-sm"><span className="rounded-lg bg-slate-100 px-3 py-2 font-bold">{fulfillmentLabels[order.fulfillment_type ?? 'pickup'] ?? 'استلام من المطعم'}</span>{order.fulfillment_type === 'dine_in' && (order.restaurant_table_name || order.restaurant_table?.name) && <span className="rounded-lg bg-cyan-50 px-3 py-2 font-bold text-cyan-900">الترابيزة: {order.restaurant_table_name ?? order.restaurant_table?.name}</span>}{order.fulfillment_type === 'delivery' && order.delivery_address && <span className="rounded-lg bg-blue-50 px-3 py-2">العنوان: {order.delivery_address}</span>}{Number(order.delivery_fee ?? 0) > 0 && <span className="rounded-lg bg-amber-50 px-3 py-2">التوصيل: {money(order.delivery_fee ?? 0)}</span>}<span className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold">الدفع: {{ unpaid: 'غير مدفوع', paid: 'مدفوع', refunded: 'مسترد' }[order.payment_status ?? 'unpaid']}</span><span className="rounded-lg bg-slate-100 px-3 py-2 text-xs">{{ cash: 'نقدًا', cash_on_delivery: 'نقدًا عند الاستلام', card: 'بطاقة', wallet: 'محفظة إلكترونية', bank_transfer: 'تحويل بنكي' }[order.payment_method ?? 'cash_on_delivery']}</span></div>
                    {order.notes && <p className="mb-3 rounded-lg bg-amber-50 p-3 text-sm">ملاحظات: {order.notes}</p>}
                    {Number(order.discount_amount ?? 0) > 0 && <div className="mb-3 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-900">كوبون {order.coupon_code} · خصم {money(order.discount_amount ?? 0)}</div>}
                    <div className="mb-3 flex flex-wrap gap-2"><button type="button" onClick={() => printKitchenTicket(order.id)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold">🖨️ طباعة تذكرة المطبخ</button>{order.customer_phone && <a className="rounded-lg border px-3 py-2 text-sm font-bold" href={`https://wa.me/${order.customer_phone.replace(/\D/g, '')}?text=${encodeURIComponent(`أهلًا ${order.customer_name}، بخصوص طلبك ${order.order_number} من ${restaurantContact.name}. الإجمالي ${money(order.total)}.`)}`} target="_blank" rel="noreferrer">مراسلة العميل واتساب</a>}{restaurantContact.whatsappPhone && <a className="rounded-lg border px-3 py-2 text-sm font-bold" href={`https://wa.me/${restaurantContact.whatsappPhone.replace(/\D/g, '')}?text=${encodeURIComponent(`طلب جديد ${order.order_number}\nالعميل: ${order.customer_name}\nالهاتف: ${order.customer_phone}\nطريقة الاستلام: ${fulfillmentLabels[order.fulfillment_type ?? 'pickup'] ?? 'استلام'}${order.restaurant_table?.name ? `\nالترابيزة: ${order.restaurant_table.name}` : ''}${order.delivery_address ? `\nالعنوان: ${order.delivery_address}` : ''}\n${order.items.map(item => `${item.product_name} × ${item.quantity}`).join('\n')}\n${order.coupon_code ? `الكوبون: ${order.coupon_code} (خصم ${money(order.discount_amount ?? 0)})\n` : ''}الإجمالي: ${money(order.total)}${order.notes ? `\nملاحظات: ${order.notes}` : ''}`)}`} target="_blank" rel="noreferrer">فتح الطلب على واتساب المطعم</a>}</div>
                    <div className="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4"><span className="text-xs font-bold text-slate-500">حالة الطلب</span><select aria-label="حالة الطلب" disabled={updatingId===order.id || nextStatuses[order.status]?.length === 0} value={order.status} onChange={e=>{setUpdatingId(order.id);router.patch(`${ordersBaseUrl}/${order.id}/status`,{status:e.target.value},{preserveScroll:true,onError:()=>setNotice('تعذر تحديث حالة الطلب. حدّث الصفحة وحاول مرة أخرى.'),onFinish:()=>setUpdatingId(null)});}} className="min-w-36 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold disabled:bg-slate-100"><option value={order.status}>{statuses.find(([value])=>value===order.status)?.[1] ?? order.status}</option>{statuses.filter(([value])=>nextStatuses[order.status]?.includes(value)).map(([value,label])=><option key={value} value={value}>{label}</option>)}</select><span className="text-xs font-bold text-slate-500">حالة الدفع</span><select aria-label="تحديث حالة الدفع" disabled={updatingPaymentId===order.id || order.payment_status === 'refunded' || order.status === 'cancelled'} value={order.payment_status ?? 'unpaid'} onChange={e=>{setUpdatingPaymentId(order.id);router.patch(`${ordersBaseUrl}/${order.id}/payment`,{payment_status:e.target.value},{preserveScroll:true,onError:()=>setNotice('تعذر تحديث حالة الدفع. راجع الحالة الحالية وحاول مرة أخرى.'),onFinish:()=>setUpdatingPaymentId(null)});}} className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold disabled:bg-slate-100"><option value={order.payment_status ?? 'unpaid'}>{{ unpaid: 'غير مدفوع', paid: 'مدفوع', refunded: 'مسترد' }[order.payment_status ?? 'unpaid']}</option>{order.payment_status === 'unpaid' && <option value="paid">تسجيل الدفع</option>}{order.payment_status === 'paid' && <option value="refunded">تسجيل الاسترداد</option>}</select>{(updatingId===order.id || updatingPaymentId===order.id)&&<span className="text-xs text-slate-400">جارٍ الحفظ…</span>}</div>
                    </div>
                </article>)}
                {visibleOrders.length === 0 && <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center xl:col-span-2"><h2 className="font-black">{orders.length ? 'لا توجد نتائج مطابقة' : 'لسه مفيش طلبات'}</h2><p className="mt-2 text-sm text-slate-500">{orders.length ? 'غيّر كلمات البحث أو خيارات التصفية.' : 'أي طلب يتأكد من المنيو المنشورة هيظهر هنا.'}</p></div>}
            </div>
        </div>
    </main>;
}

import { FormEvent, useState } from 'react';

type SuggestedProduct = {
    id: number;
    name: string;
    description?: string | null;
    price: number;
    category: string;
    requires_options: boolean;
};

type ChatMessage = {
    role: 'assistant' | 'user';
    text: string;
    products?: SuggestedProduct[];
};

export default function AiWaiter({ restaurantSlug, menuSlug, accent }: { restaurantSlug: string; menuSlug: string; accent?: string }) {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [busy, setBusy] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>([{
        role: 'assistant',
        text: 'أهلاً بيك! أنا مساعد NEXORA. أساعدك تختار من المنيو حسب ذوقك أو ميزانيتك. تحب تاكل إيه؟',
    }]);

    async function sendMessage(event?: FormEvent, preset?: string) {
        event?.preventDefault();
        const message = (preset ?? input).trim();
        if (!message || busy) return;

        setMessages((current) => [...current, { role: 'user', text: message }]);
        setInput('');
        setBusy(true);
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch(`/r/${encodeURIComponent(restaurantSlug)}/menu/${encodeURIComponent(menuSlug)}/ai-waiter`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ message }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'حصلت مشكلة مؤقتة. جرّب تاني.');
            setMessages((current) => [...current, {
                role: 'assistant',
                text: result.reply,
                products: result.products,
            }]);
        } catch (error) {
            setMessages((current) => [...current, {
                role: 'assistant',
                text: error instanceof Error ? error.message : 'مش قادر أرد دلوقتي. جرّب تاني بعد شوية.',
            }]);
        } finally {
            setBusy(false);
        }
    }

    function addToCart(product: SuggestedProduct) {
        if (product.requires_options) {
            setMessages((current) => [...current, { role: 'assistant', text: `صنف ${product.name} محتاج تختار إضافاته من المنيو الأول عشان نسجل الطلب صح. اختاره من المنيو وبعدها راجع السلة.` }]);
            return;
        }
        window.dispatchEvent(new CustomEvent('nexora:add-to-cart', {
            detail: { productId: product.id, name: product.name, price: product.price, quantity: 1 },
        }));
        window.dispatchEvent(new Event('nexora:open-cart'));
        setMessages((current) => [...current, { role: 'assistant', text: `ضفت ${product.name} للسلة. راجع الكمية والتفاصيل واكتب بياناتك قبل تأكيد الطلب.` }]);
    }

    return (
        <div className="fixed bottom-4 left-4 z-50 font-sans" dir="rtl">
            {open && (
                <section className="mb-3 flex h-[min(70vh,560px)] w-[min(92vw,380px)] flex-col overflow-hidden rounded-2xl border border-black/10 bg-white shadow-2xl" aria-label="مساعد NEXORA الذكي">
                    <header className="flex items-center justify-between px-4 py-3 text-white" style={{ backgroundColor: accent || '#166534' }}>
                        <div>
                            <p className="font-bold">NEXORA AI Waiter</p>
                            <p className="text-xs opacity-90">مساعدك في اختيار الأوردر</p>
                        </div>
                        <button type="button" onClick={() => setOpen(false)} className="rounded-lg px-3 py-1 text-xl hover:bg-white/15" aria-label="إغلاق المساعد">×</button>
                    </header>
                    <div className="flex-1 space-y-3 overflow-y-auto bg-stone-50 p-3" aria-live="polite">
                        {messages.map((message, index) => (
                            <div key={`${message.role}-${index}`} className={`max-w-[95%] rounded-2xl px-3 py-2 text-sm leading-6 ${message.role === 'user' ? 'mr-auto bg-emerald-100 text-stone-900' : 'ml-auto bg-white text-stone-800 shadow-sm ring-1 ring-black/5'}`}>
                                <p>{message.text}</p>
                                {message.products && message.products.length > 0 && (
                                    <div className="mt-2 space-y-2">
                                        {message.products.map((product) => (
                                            <div key={product.id} className="rounded-xl border border-stone-200 p-2">
                                                <div className="flex items-start justify-between gap-2">
                                                    <div>
                                                        <p className="font-semibold">{product.name}</p>
                                                        <p className="text-xs text-stone-500">{product.category}</p>
                                                    </div>
                                                    <p className="shrink-0 font-bold">{product.price.toLocaleString('ar-EG')} ج.م</p>
                                                </div>
                                                {product.description && <p className="mt-1 text-xs text-stone-600">{product.description}</p>}
                                                <button type="button" onClick={() => addToCart(product)} disabled={busy} className="mt-2 w-full rounded-lg border border-stone-300 px-2 py-1 text-xs font-medium hover:bg-stone-100 disabled:opacity-50">
                                                    {product.requires_options ? 'اختاره من المنيو لتحديد الإضافات' : 'ضيف للسلة'}
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                        {busy && <p className="text-xs text-stone-500">بفكر في اختيارات مناسبة...</p>}
                    </div>
                    <form onSubmit={sendMessage} className="flex gap-2 border-t border-stone-200 bg-white p-3">
                        <input value={input} onChange={(event) => setInput(event.target.value)} maxLength={1000} placeholder="مثلاً: عايز حاجة اقتصادية" className="min-w-0 flex-1 rounded-xl border border-stone-300 px-3 py-2 text-sm outline-none focus:border-emerald-600" aria-label="رسالتك للمساعد" />
                        <button type="submit" disabled={busy || !input.trim()} className="rounded-xl px-4 py-2 text-sm font-bold text-white disabled:opacity-50" style={{ backgroundColor: accent || '#166534' }}>إرسال</button>
                    </form>
                    <p className="bg-white px-3 pb-2 text-[10px] text-stone-500">الأسعار من المنيو الحالي. راجع اختياراتك في سلة الطلبات قبل التأكيد.</p>
                </section>
            )}
            <button type="button" onClick={() => setOpen((value) => !value)} className="flex items-center gap-2 rounded-full px-5 py-3 font-bold text-white shadow-lg transition hover:scale-[1.02]" style={{ backgroundColor: accent || '#166534' }} aria-expanded={open}>
                <span aria-hidden="true">✦</span> اسأل المساعد الذكي
            </button>
        </div>
    );
}

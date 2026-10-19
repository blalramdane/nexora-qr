import { useState } from 'react';
import { useLocale } from '../i18n/LocaleProvider';

type Props = { tableToken?: string; tableId?: number; accent?: string };

export default function CallWaiterButton({ tableToken, tableId, accent }: Props) {
  const { t, dir } = useLocale();
  const [open, setOpen] = useState(false);
  const [sending, setSending] = useState(false);
  const [done, setDone] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  if (!tableId) return null;

  async function send(type: string) {
    if (!tableToken || sending) return;
    setSending(true);
    setError(null);
    try {
      const res = await fetch('/service-requests', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ? { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')!.content } : {}) },
        credentials: 'same-origin',
        body: JSON.stringify({ restaurant_table_id: tableId, table_token: tableToken, request_type: type }),
      });
      if (res.ok) {
        const labelKey = type === 'call_waiter' ? 'components.callWaiter' : type === 'request_bill' ? 'components.requestBill' : 'components.refillWater';
        setDone(t(labelKey));
        setOpen(false);
        window.setTimeout(() => setDone(null), 4000);
      } else setError(t('components.sendError'));
    } catch {
      setError(t('components.sendError'));
    } finally {
      setSending(false);
    }
  }

  return (
    <>
      {done && (
        <div dir={dir} role="status" className="fixed bottom-24 left-1/2 z-50 -translate-x-1/2 rounded-full bg-emerald-600 px-4 py-2 text-sm text-white shadow-lg">
          ✅ {done}
        </div>
      )}
      <button
        onClick={() => setOpen((v) => !v)}
        className="fixed bottom-6 left-6 z-40 w-14 h-14 rounded-full shadow-xl flex items-center justify-center text-2xl"
        style={{ background: accent || '#111827', color: '#fff' }}
        aria-label={t('components.callWaiter')}
      >
        🛎️
      </button>
      {open && (
        <div dir={dir} className="fixed bottom-24 left-6 z-40 w-60 space-y-2 rounded-2xl bg-white p-3 shadow-2xl">
          {[
            { key: 'call_waiter', icon: '🔔', label: t('components.callWaiter'), desc: t('components.needHelp') },
            { key: 'request_bill', icon: '🧾', label: t('components.requestBill'), desc: t('components.wantToPay') },
            { key: 'refill_water', icon: '💧', label: t('components.refillWater'), desc: t('components.wantWater') },
          ].map((item) => (
            <button
              key={item.key}
              disabled={sending}
              onClick={() => send(item.key)}
              className="w-full text-right px-3 py-2 rounded-xl hover:bg-gray-100 transition text-sm"
            >
              <div className="font-semibold">{item.icon} {item.label}</div>
              <div className="text-xs text-gray-500">{item.desc}</div>
            </button>
          ))}
          {error && <p role="alert" className="rounded-lg bg-rose-50 p-2 text-xs text-rose-700">{error}</p>}
        </div>
      )}
    </>
  );
}

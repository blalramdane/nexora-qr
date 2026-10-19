import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';

type Item = { name: string; price: string };
type Participant = { id: number; participant_name: string | null; order_id: number | null };

export default function GroupOrder({ token }: { token: string }) {
  const [group, setGroup] = useState<any>(null);
  const [name, setName] = useState('');
  const [joined, setJoined] = useState(false);

  useEffect(() => {
    fetch('/group-orders/' + token).then((r) => r.json()).then(setGroup);
  }, [token]);

  async function join() {
    const res = await fetch('/group-orders/' + token + '/join', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ participant_name: name }),
    });
    if (res.ok) setJoined(true);
    const g = await fetch('/group-orders/' + token).then((r) => r.json());
    setGroup(g);
  }

  if (!group) return <div className="p-6 text-center">جاري التحميل...</div>;

  return (
    <>
      <Head title="طلب جماعي" />
      <div className="max-w-2xl mx-auto p-4 space-y-4" dir="rtl">
        <h1 className="text-2xl font-bold">👥 طلب جماعي</h1>
        <p className="text-sm text-gray-500">شارك الرابط مع أصدقائك لإضافة طلباتهم.</p>
        <div className="text-sm">المشاركون: {group.participants?.length ?? 0} / {group.max_participants}</div>

        {!joined && group.status === 'open' && (
          <div className="flex gap-2">
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="اسمك" className="flex-1 px-3 py-2 rounded-xl border" />
            <button onClick={join} className="px-4 py-2 rounded-xl bg-emerald-600 text-white">انضم</button>
          </div>
        )}
        {joined && <div className="bg-emerald-50 text-emerald-700 p-3 rounded-xl">✅ تم الانضمام. يمكنك الآن طلب أصنافك.</div>}

        <div className="space-y-2">
          {(group.participants || []).map((p: Participant) => (
            <div key={p.id} className="p-3 border rounded-xl text-sm">{p.participant_name || 'مجهول'}</div>
          ))}
        </div>
      </div>
    </>
  );
}

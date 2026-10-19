import { useState } from 'react';
import { useLocale } from '../i18n/LocaleProvider';

type Product = { id: number; name: string; price: string; image_path?: string | null; is_available: boolean; is_featured: boolean };

type Props = { products: Product[]; onEdit?: (p: Product) => void; onToggle?: (p: Product) => void };

export default function ProductGrid({ products, onEdit, onToggle }: Props) {
  const { t, dir } = useLocale();
  const [q, setQ] = useState('');
  const filtered = products.filter((p) => p.name.toLowerCase().includes(q.toLowerCase()));

  return (
    <div dir={dir} className="space-y-3">
      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder={t('components.searchProducts')}
        className="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm"
      />
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        {filtered.map((p) => (
          <div key={p.id} className="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
            {p.image_path ? (
              <img src={'/storage/' + p.image_path} alt={p.name} className="w-full h-28 object-cover" />
            ) : (
              <div className="w-full h-28 bg-gray-100 flex items-center justify-center text-3xl">🍽️</div>
            )}
            <div className="p-2 space-y-1">
              <div className="font-semibold text-sm truncate">{p.name}</div>
              <div className="text-emerald-600 font-bold text-sm">{p.price}</div>
              <div className="flex gap-1">
                <button type="button" onClick={() => onEdit?.(p)} className="text-xs px-2 py-1 rounded-lg bg-gray-100 hover:bg-gray-200">{t('components.edit')}</button>
                <button type="button" aria-pressed={p.is_available} onClick={() => onToggle?.(p)} className={`text-xs px-2 py-1 rounded-lg ${p.is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                  {p.is_available ? t('components.available') : t('components.unavailable')}
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
      {filtered.length === 0 && <div className="text-center text-gray-400 py-8">{t('components.noProducts')}</div>}
    </div>
  );
}

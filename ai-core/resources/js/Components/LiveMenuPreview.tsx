import { useState } from 'react';
import { useLocale } from '../i18n/LocaleProvider';

type Product = { id: number; name: string; price: string; image_path?: string | null; is_available: boolean };
type Category = { id: number; name: string; products: Product[] };

type Props = { categories: Category[]; restaurantName: string; style: string; primary?: string };

const STYLE_CLASSES: Record<string, string> = {
  modern: 'bg-white text-gray-900',
  luxury: 'bg-stone-900 text-amber-100',
  playful: 'bg-pink-50 text-pink-900',
  classic: 'bg-amber-50 text-amber-900',
};

export default function LiveMenuPreview({ categories, restaurantName, style, primary }: Props) {
  const { t, dir } = useLocale();
  const [activeCat, setActiveCat] = useState(categories[0]?.id);
  const cls = STYLE_CLASSES[style] || STYLE_CLASSES.modern;

  return (
    <div className={`rounded-3xl overflow-hidden shadow-2xl ${cls}`} dir={dir}>
      <div className="p-4 text-center border-b" style={{ borderColor: primary }}>
        <h2 className="text-xl font-bold">{restaurantName}</h2>
        <p className="text-xs opacity-70">{t('components.livePreview')}</p>
      </div>
      <div className="flex gap-2 overflow-x-auto p-3 border-b" style={{ borderColor: primary }}>
        {categories.map((c) => (
          <button
            key={c.id}
            onClick={() => setActiveCat(c.id)}
            className={`px-3 py-1 rounded-full text-xs whitespace-nowrap ${activeCat === c.id ? 'bg-emerald-600 text-white' : 'bg-gray-100/20'}`}
          >
            {c.name}
          </button>
        ))}
      </div>
      <div className="p-3 space-y-2 max-h-96 overflow-y-auto">
        {categories.find((c) => c.id === activeCat)?.products.map((p) => (
          <div key={p.id} className="flex gap-3 items-center p-2 rounded-xl bg-black/5">
            {p.image_path ? (
              <img src={'/storage/' + p.image_path} className="w-12 h-12 rounded-lg object-cover" alt="" />
            ) : (
              <div className="w-12 h-12 rounded-lg bg-gray-200/30 flex items-center justify-center">🍽️</div>
            )}
            <div className="flex-1">
              <div className="font-semibold text-sm">{p.name}</div>
              <div className="text-xs opacity-70">{p.is_available ? t('components.available') : t('components.unavailable')}</div>
            </div>
            <div className="font-bold text-sm" style={{ color: primary }}>{p.price}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

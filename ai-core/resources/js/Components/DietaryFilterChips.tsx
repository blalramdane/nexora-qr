import { useLocale } from '../i18n/LocaleProvider';

type Props = { selected: string[]; onChange: (tags: string[]) => void; variant?: 'light' | 'dark' };

export default function DietaryFilterChips({ selected, onChange, variant = 'light' }: Props) {
  const { t, dir } = useLocale();
  const dark = variant === 'dark';
  const tags = [
    { key: 'vegan', label: `🌱 ${t('components.vegan')}` },
    { key: 'vegetarian', label: `🥗 ${t('components.vegetarian')}` },
    { key: 'halal', label: `☪️ ${t('components.halal')}` },
    { key: 'gluten_free', label: `🌾 ${t('components.glutenFree')}` },
    { key: 'dairy_free', label: `🥛 ${t('components.dairyFree')}` },
    { key: 'nut_free', label: `🥜 ${t('components.nutFree')}` },
  ];
  function toggle(key: string) {
    onChange(selected.includes(key) ? selected.filter((t) => t !== key) : [...selected, key]);
  }
  return (
    <div dir={dir} className="flex flex-wrap gap-2 py-2" aria-label={t('components.dietaryFilters')}>
      {tags.map((tag) => (
        <button
          key={tag.key}
          type="button"
          aria-pressed={selected.includes(tag.key)}
          onClick={() => toggle(tag.key)}
          className={`px-3 py-1 rounded-full text-xs border transition ${dark
            ? (selected.includes(tag.key) ? 'bg-[#d6a45b] text-[#1b100a] border-[#d6a45b]' : 'bg-white/[0.04] text-[#f5e7cf]/80 border-[#d6a45b]/20 hover:bg-white/[0.08]')
            : (selected.includes(tag.key) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-300')
          }`}
        >
          {tag.label}
        </button>
      ))}
      {selected.length > 0 && (
        <button onClick={() => onChange([])} className={`px-3 py-1 rounded-full text-xs border ${dark ? 'text-rose-200 border-rose-300/30' : 'text-red-600 border-red-300'}`}>
          ✕ {t('components.clearFilters')}
        </button>
      )}
    </div>
  );
}

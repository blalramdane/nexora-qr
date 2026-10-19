import { useLocale } from '../i18n/LocaleProvider';

const STYLES = [
  { key: 'modern', labelKey: 'components.modern', descKey: 'components.minimal', preview: 'bg-white border-gray-200' },
  { key: 'luxury', labelKey: 'components.luxury', descKey: 'components.luxuryDesc', preview: 'bg-stone-900 text-amber-100 border-amber-900' },
  { key: 'playful', labelKey: 'components.playful', descKey: 'components.playfulDesc', preview: 'bg-pink-50 border-pink-300' },
  { key: 'classic', labelKey: 'components.classic', descKey: 'components.classicDesc', preview: 'bg-amber-50 border-amber-300' },
];

type Props = { value: string; onChange: (style: string) => void };

export default function DesignStyleSwitcher({ value, onChange }: Props) {
  const { t, dir } = useLocale();
  return (
    <div dir={dir} className="grid grid-cols-2 gap-3">
      {STYLES.map((s) => (
        <button
          key={s.key}
          type="button"
          aria-pressed={value === s.key}
          onClick={() => onChange(s.key)}
          className={`p-3 rounded-2xl border-2 text-right transition ${s.preview} ${value === s.key ? 'ring-2 ring-emerald-500' : ''}`}
        >
          <div className="font-bold">{t(s.labelKey)}</div>
          <div className="text-xs opacity-70">{t(s.descKey)}</div>
        </button>
      ))}
    </div>
  );
}

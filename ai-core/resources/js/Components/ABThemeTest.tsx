import { useLocale } from '../i18n/LocaleProvider';

type Props = { variantA: string; variantB: string; split: number; onChange: (split: number) => void };

export default function ABThemeTest({ variantA, variantB, split, onChange }: Props) {
  const { t, dir } = useLocale();
  return (
    <div dir={dir} className="space-y-3 p-4 border rounded-2xl">
      <h3 className="font-bold">{t('components.abThemeTest')}</h3>
      <div className="grid grid-cols-2 gap-3">
        <div className="p-3 rounded-xl bg-gray-100 text-center">
          <div className="font-semibold">{t('components.variantA')}</div>
          <div className="text-xs text-gray-500">{variantA}</div>
        </div>
        <div className="p-3 rounded-xl bg-gray-100 text-center">
          <div className="font-semibold">{t('components.variantB')}</div>
          <div className="text-xs text-gray-500">{variantB}</div>
        </div>
      </div>
      <div>
        <label className="text-sm">{t('components.trafficToB')}: {split}%</label>
        <input type="range" min={0} max={100} value={split} onChange={(e) => onChange(Number(e.target.value))} aria-valuetext={`${split}%`} className="w-full" />
      </div>
    </div>
  );
}

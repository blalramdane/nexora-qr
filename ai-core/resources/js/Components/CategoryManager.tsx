import { useState } from 'react';
import { useLocale } from '../i18n/LocaleProvider';

type Category = { id: number; name: string; sort_order: number; is_active: boolean };

export default function CategoryManager({ categories, onCreate, onRename, onDelete }: {
  categories: Category[];
  onCreate: (name: string) => void;
  onRename: (id: number, name: string) => void;
  onDelete: (id: number) => void;
}) {
  const { t, dir } = useLocale();
  const [newName, setNewName] = useState('');
  const [editing, setEditing] = useState<number | null>(null);
  const [editName, setEditName] = useState('');

  return (
    <div className="space-y-3" dir={dir}>
      <div className="flex gap-2">
        <input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder={t('components.newCategory')} className="flex-1 px-3 py-2 rounded-xl border" />
        <button type="button" onClick={() => { if (newName.trim()) { onCreate(newName.trim()); setNewName(''); } }} className="px-4 py-2 rounded-xl bg-emerald-600 text-white">{t('components.add')}</button>
      </div>
      <div className="space-y-2">
        {categories.map((c) => (
          <div key={c.id} className="flex items-center gap-2 p-3 border rounded-xl">
            {editing === c.id ? (
              <>
                <input value={editName} onChange={(e) => setEditName(e.target.value)} className="flex-1 px-2 py-1 rounded-lg border" />
                <button type="button" onClick={() => { if (editName.trim()) onRename(c.id, editName.trim()); setEditing(null); }} className="text-emerald-600 text-sm">{t('components.save')}</button>
                <button type="button" onClick={() => setEditing(null)} className="text-gray-400 text-sm">{t('components.cancel')}</button>
              </>
            ) : (
              <>
                <span className="flex-1 font-medium">{c.name}</span>
                <button type="button" onClick={() => { setEditing(c.id); setEditName(c.name); }} className="text-blue-600 text-sm">{t('components.rename')}</button>
                <button type="button" onClick={() => { if (window.confirm(t('components.deleteConfirm'))) onDelete(c.id); }} className="text-red-600 text-sm">{t('components.delete')}</button>
              </>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

export type ModifierChoice = {
    id: number;
    name: string;
    is_active?: boolean;
    is_required?: boolean;
    group_name?: string | null;
    group_min_choices?: number;
    group_max_choices?: number;
};

export function toggleModifierSelection(modifiers: ModifierChoice[], selectedIds: number[], modifier: ModifierChoice): number[] {
    if (selectedIds.includes(modifier.id)) return selectedIds.filter(id => id !== modifier.id);

    const groupName = modifier.group_name?.trim() || null;
    const peers = modifiers.filter(item => (item.group_name?.trim() || null) === groupName && item.is_active !== false);
    const maximum = groupName ? Math.max(1, Math.min(...peers.map(item => Number(item.group_max_choices ?? 1)))) : 20;
    const selectedInGroup = selectedIds.filter(id => peers.some(item => item.id === id));

    if (selectedInGroup.length >= maximum) {
        if (groupName && maximum === 1) {
            return [...selectedIds.filter(id => !peers.some(item => item.id === id)), modifier.id];
        }
        return selectedIds;
    }

    return [...selectedIds, modifier.id];
}

export function modifierSelectionError(modifiers: ModifierChoice[], selectedIds: number[]): string | null {
    const active = modifiers.filter(item => item.is_active !== false);
    if (active.some(item => !item.group_name?.trim() && item.is_required && !selectedIds.includes(item.id))) {
        return 'اختار كل الإضافات المطلوبة.';
    }

    const groups = [...new Set(active.map(item => item.group_name?.trim()).filter((name): name is string => Boolean(name)))];
    for (const group of groups) {
        const options = active.filter(item => item.group_name?.trim() === group);
        const minimum = Math.max(...options.map(item => Number(item.group_min_choices ?? 0)));
        const maximum = Math.max(1, Math.min(...options.map(item => Number(item.group_max_choices ?? 1))));
        const count = options.filter(item => selectedIds.includes(item.id)).length;
        if (count < minimum || count > maximum) return `اختار من ${minimum} إلى ${maximum} من مجموعة ${group}.`;
    }

    return null;
}

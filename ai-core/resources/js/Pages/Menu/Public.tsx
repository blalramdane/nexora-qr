import { Head } from '@inertiajs/react';
import TemplateRenderer from './TemplateRenderer';
import PublicOrderCart from './PublicOrderCart';
import AiWaiter from './AiWaiter';

type Restaurant = {
    name: string;
    slug: string;
    logo_path?: string | null;
    delivery_fee?: string | number;
    delivery_areas?: string[];
    delivery_enabled?: boolean;
    pickup_enabled?: boolean;
};

type PublicMenu = {
    name: string;
    slug: string;
    theme?: {
        primary: string;
        accent: string;
        background: string;
        foreground: string;
        radius: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    } | null;
    template?: { key: string; name: string } | null;
    template_version?: { version: number; is_active: boolean } | null;
    categories: Array<{
        id: number;
        name: string;
        description?: string | null;
        is_active: boolean;
        products: Array<{
            id: number;
            name: string;
            description?: string | null;
            price: string;
            image_path?: string | null;
            is_available: boolean;
            is_featured: boolean;
            variants?: Array<{ id: number; name: string; price?: string | null; price_delta?: string; is_active: boolean }>;
            modifiers?: Array<{ id: number; name: string; price_delta: string; is_active: boolean; is_required: boolean; group_name?: string | null; group_min_choices?: number; group_max_choices?: number }>;
        }>;
    }>;
};

export default function Public({ restaurant, menu }: { restaurant: Restaurant; menu: PublicMenu }) {
    return (
        <>
            <Head title={`${menu.name} — ${restaurant.name}`}>
                <meta name="description" content={`${menu.name} من ${restaurant.name}. تصفح قائمة الطعام والأسعار.`} />
                <meta name="robots" content="index,follow" />
            </Head>
            <TemplateRenderer menu={menu} restaurantName={restaurant.name} />
            <PublicOrderCart restaurantSlug={restaurant.slug} menuSlug={menu.slug} accent={menu.theme?.accent} primary={menu.theme?.primary} deliveryFee={restaurant.delivery_fee} deliveryAreas={restaurant.delivery_areas} deliveryEnabled={restaurant.delivery_enabled} pickupEnabled={restaurant.pickup_enabled} />
            <AiWaiter restaurantSlug={restaurant.slug} menuSlug={menu.slug} accent={menu.theme?.primary} />
        </>
    );
}

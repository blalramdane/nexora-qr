import { Head } from '@inertiajs/react';

type Product = {
  id: number;
  name: string;
  description: string | null;
  price: string;
  image_path: string | null;
};

type Category = { id: number; name: string; products: Product[] };

export default function Show({
  restaurant,
  categories,
}: {
  restaurant: { name: string; slug: string; phone: string | null };
  categories: Category[];
}) {
  return (
    <>
      <Head title={restaurant.name} />
      <main className="min-h-screen bg-slate-950 text-white">
        <header className="border-b border-slate-800 px-5 py-8">
          <div className="mx-auto max-w-3xl">
            <p className="text-sm text-slate-400">Nexora QR</p>
            <h1 className="mt-2 text-3xl font-bold">{restaurant.name}</h1>
            <p className="mt-2 text-slate-400">Digital menu</p>
          </div>
        </header>
        <div className="mx-auto max-w-3xl space-y-8 px-5 py-8">
          {categories.map(category => (
            <section key={category.id}>
              <h2 className="mb-4 text-2xl font-semibold">{category.name}</h2>
              <div className="space-y-3">
                {category.products.map(product => (
                  <article key={product.id} className="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <h3 className="font-semibold">{product.name}</h3>
                        {product.description && <p className="mt-1 text-sm text-slate-400">{product.description}</p>}
                      </div>
                      <strong className="whitespace-nowrap">{product.price}</strong>
                    </div>
                  </article>
                ))}
              </div>
            </section>
          ))}
          {categories.length === 0 && <p className="text-slate-400">Menu is being prepared.</p>}
        </div>
      </main>
    </>
  );
}
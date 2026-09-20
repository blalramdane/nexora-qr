import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

type Category = {
  id: number;
  name: string;
  slug: string;
  is_active: boolean;
  products_count: number;
};

type Product = {
  id: number;
  name: string;
  slug: string;
  category: string | null;
  price: string;
  is_available: boolean;
  is_featured: boolean;
};

function ProductActions({ product }: { product: Product }) {
  const form = useForm();
  return <div className="mt-3 flex gap-2">
    <button onClick={() => form.post(`/menu/products/${product.id}/toggle`, { preserveScroll: true })} className="rounded-lg border border-slate-700 px-3 py-1 text-xs">{product.is_available ? 'Disable' : 'Enable'}</button>
    <button onClick={() => { if (confirm('Delete this product?')) form.delete(`/menu/products/${product.id}`, { preserveScroll: true }); }} className="rounded-lg border border-red-900 px-3 py-1 text-xs text-red-300">Delete</button>
  </div>;
}

export default function MenuIndex({
  restaurant,
  categories,
  products,
}: {
  restaurant: { id: number; name: string };
  categories: Category[];
  products: Product[];
}) {
  const categoryForm = useForm({ name: '', description: '' });
  const productForm = useForm({
    category_id: categories[0]?.id ?? '',
    name: '',
    description: '',
    price: '',
  });

  const submitCategory = (event: FormEvent) => {
    event.preventDefault();
    categoryForm.post('/menu/categories', { preserveScroll: true, onSuccess: () => categoryForm.reset() });
  };

  const submitProduct = (event: FormEvent) => {
    event.preventDefault();
    productForm.post('/menu/products', { preserveScroll: true, onSuccess: () => productForm.reset('name', 'description', 'price') });
  };

  return (
    <>
      <Head title="Menu" />
      <main className="min-h-screen bg-slate-950 px-6 py-10 text-white">
        <div className="mx-auto max-w-6xl space-y-8">
          <header className="flex items-center justify-between">
            <div>
              <p className="text-sm text-slate-400">Nexora QR</p>
              <h1 className="text-3xl font-bold">{restaurant.name} — Menu</h1>
            </div>
            <a href="/dashboard" className="rounded-lg border border-slate-700 px-4 py-2 text-sm">Dashboard</a>
          </header>

          <section className="grid gap-6 md:grid-cols-2">
            <form onSubmit={submitCategory} className="rounded-2xl border border-slate-800 bg-slate-900 p-6">
              <h2 className="mb-4 text-xl font-semibold">Add category</h2>
              <input className="mb-3 w-full rounded-lg bg-slate-800 p-3" placeholder="Category name" value={categoryForm.data.name} onChange={e => categoryForm.setData('name', e.target.value)} />
              <textarea className="mb-3 w-full rounded-lg bg-slate-800 p-3" placeholder="Description (optional)" value={categoryForm.data.description} onChange={e => categoryForm.setData('description', e.target.value)} />
              <button className="rounded-lg bg-white px-4 py-2 font-semibold text-slate-900" disabled={categoryForm.processing}>Add category</button>
            </form>

            <form onSubmit={submitProduct} className="rounded-2xl border border-slate-800 bg-slate-900 p-6">
              <h2 className="mb-4 text-xl font-semibold">Add product</h2>
              <select className="mb-3 w-full rounded-lg bg-slate-800 p-3" value={productForm.data.category_id} onChange={e => productForm.setData('category_id', Number(e.target.value))}>
                <option value="">Select category</option>
                {categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}
              </select>
              <input className="mb-3 w-full rounded-lg bg-slate-800 p-3" placeholder="Product name" value={productForm.data.name} onChange={e => productForm.setData('name', e.target.value)} />
              <input className="mb-3 w-full rounded-lg bg-slate-800 p-3" placeholder="Price" type="number" min="0" step="0.01" value={productForm.data.price} onChange={e => productForm.setData('price', e.target.value)} />
              <textarea className="mb-3 w-full rounded-lg bg-slate-800 p-3" placeholder="Description (optional)" value={productForm.data.description} onChange={e => productForm.setData('description', e.target.value)} />
              <button className="rounded-lg bg-white px-4 py-2 font-semibold text-slate-900" disabled={productForm.processing || categories.length === 0}>Add product</button>
            </form>
          </section>

          <section className="grid gap-6 md:grid-cols-2">
            <div className="rounded-2xl border border-slate-800 bg-slate-900 p-6">
              <h2 className="mb-4 text-xl font-semibold">Categories</h2>
              <div className="space-y-3">
                {categories.map(category => (
                  <div key={category.id} className="flex items-center justify-between rounded-lg bg-slate-800 p-3">
                    <span>{category.name}</span><span className="text-sm text-slate-400">{category.products_count} products</span>
                  </div>
                ))}
                {categories.length === 0 && <p className="text-slate-400">No categories yet.</p>}
              </div>
            </div>

            <div className="rounded-2xl border border-slate-800 bg-slate-900 p-6">
              <h2 className="mb-4 text-xl font-semibold">Products</h2>
              <div className="space-y-3">
                {products.map(product => (
                  <div key={product.id} className="rounded-lg bg-slate-800 p-3">
                    <div className="flex items-center justify-between gap-3">
                      <div><div className="font-medium">{product.name}</div><div className="text-sm text-slate-400">{product.category ?? '—'} · {product.is_available ? 'Available' : 'Unavailable'}</div></div>
                      <span>{product.price}</span>
                    </div>
                    <ProductActions product={product} />
                  </div>
                ))}
                {products.length === 0 && <p className="text-slate-400">No products yet.</p>}
              </div>
            </div>
          </section>
        </div>
      </main>
    </>
  );
}
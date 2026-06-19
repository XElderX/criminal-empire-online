import { useEffect, useMemo, useState } from 'react';
import { buyFromShop, getShop, getShops, sellToShop } from '../api/shops';
import { GameHeader } from '../components/game/GameHeader';
import { Notice } from '../components/Notice';
import { ShopCard } from '../components/shop/ShopCard';
import { ShopCategoryFilter } from '../components/shop/ShopCategoryFilter';
import { ShopItemCard } from '../components/shop/ShopItemCard';
import { ShopLocationNotice } from '../components/shop/ShopLocationNotice';
import { ShopTransactionPanel } from '../components/shop/ShopTransactionPanel';
import type { PageName } from '../types';
import type { SellableInventoryItem, ShopDetailResponse, ShopItem, ShopSummary } from '../types/shop';

interface ShopsPageProps {
  onChanged: () => void;
  onNavigate: (page: PageName) => void;
}

export function ShopsPage({ onChanged, onNavigate }: ShopsPageProps) {
  const [shops, setShops] = useState<ShopSummary[]>([]);
  const [currentShopSlug, setCurrentShopSlug] = useState<string | null>(null);
  const [detail, setDetail] = useState<ShopDetailResponse | null>(null);
  const [category, setCategory] = useState('all');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    void loadShops();
  }, []);

  useEffect(() => {
    if (currentShopSlug) {
      void loadDetail(currentShopSlug);
    }
  }, [currentShopSlug]);

  const categories = useMemo(() => {
    if (!detail) return [];
    return Array.from(new Set(detail.items.map((item) => item.category))).sort();
  }, [detail]);

  const visibleItems = useMemo(() => {
    if (!detail) return [];
    return detail.items.filter((item) => category === 'all' || item.category === category);
  }, [detail, category]);

  async function loadShops(): Promise<void> {
    setError('');
    try {
      const response = await getShops();
      setShops(response.data);
      if (!currentShopSlug && response.data.length > 0) {
        setCurrentShopSlug(response.data[0].slug);
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  async function loadDetail(slug: string): Promise<void> {
    setError('');
    try {
      setDetail(await getShop(slug));
      setCategory('all');
    } catch (requestError) {
      setDetail(null);
      setError((requestError as Error).message);
    }
  }

  async function buy(item: ShopItem): Promise<void> {
    if (!detail) return;
    setBusy(true);
    setMessage('');
    setError('');
    try {
      const response = await buyFromShop(detail.shop.slug, item.item_key, 1);
      setMessage(response.message);
      await loadDetail(detail.shop.slug);
      await loadShops();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function sell(item: SellableInventoryItem): Promise<void> {
    if (!detail) return;
    setBusy(true);
    setMessage('');
    setError('');
    try {
      const response = await sellToShop(detail.shop.slug, item.item_key, 1);
      setMessage(response.message);
      await loadDetail(detail.shop.slug);
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="page-section shops-page">
      <GameHeader
        eyebrow="Map commerce"
        title="Shops & Dealers"
        description="Buy and sell gear through map locations. Inventory is for owned-item management; shops require travel for transactions."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="map-top-actions">
        <button className="btn" onClick={() => onNavigate('world map')}>Open World Map</button>
        <button className="btn" onClick={() => onNavigate('equipment')}>Manage Inventory</button>
      </div>

      <div className="map-layout-grid">
        <div className="map-side-stack">
          {shops.map((shop) => (
            <ShopCard
              key={shop.slug}
              shop={shop}
              selected={currentShopSlug === shop.slug}
              onSelect={(selectedShop) => setCurrentShopSlug(selectedShop.slug)}
            />
          ))}
        </div>

        <div className="card shop-detail-card">
          {!detail ? (
            <p className="muted">Select a shop to inspect its catalog.</p>
          ) : (
            <>
              <p className="eyebrow">{detail.shop.shop_type.replace(/_/g, ' ')}</p>
              <h2>{detail.shop.name}</h2>
              <p>{detail.shop.description}</p>
              <ShopLocationNotice shop={detail.shop} />

              <div className="location-effect-summary">
                <span className="info-pill">{detail.shop.is_legal ? 'Legal shop' : 'Shady contact'}</span>
                {detail.shop.is_black_market && <span className="info-pill">Black market</span>}
                <span className="info-pill">Heat risk {detail.shop.heat_risk}</span>
                <span className="info-pill">Catalog {detail.items.length}</span>
              </div>

              <section className="page-subsection">
                <h2>Buy catalog</h2>
                <p className="muted">Prices, stock, requirements, and disabled status come from backend shop config and database stock.</p>
                <ShopCategoryFilter value={category} categories={categories} onChange={setCategory} />
                <div className="inventory-icon-grid">
                  {visibleItems.map((item) => (
                    <ShopItemCard key={item.id} item={item} busy={busy} onBuy={buy} />
                  ))}
                </div>
              </section>

              <ShopTransactionPanel items={detail.sellableInventory} busy={busy} onSell={sell} />
            </>
          )}
        </div>
      </div>
    </section>
  );
}

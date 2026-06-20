import { useEffect, useMemo, useState } from 'react';
import { buyFromShop, getShop, getShops, sellToShop } from '../api/shops';
import { GameHeader } from '../components/game/GameHeader';
import { Notice } from '../components/Notice';
import { ShopCard } from '../components/shop/ShopCard';
import { ShopCategoryFilter } from '../components/shop/ShopCategoryFilter';
import { ShopItemCard } from '../components/shop/ShopItemCard';
import { ShopLocationNotice } from '../components/shop/ShopLocationNotice';
import { PaymentTypeSelector, type PaymentType } from '../components/shop/PaymentTypeSelector';
import { ShopTransactionPanel } from '../components/shop/ShopTransactionPanel';
import type { PageName } from '../types';
import type { SellableInventoryItem, ShopDetailResponse, ShopItem, ShopSummary } from '../types/shop';

interface ShopsPageProps {
  onChanged: () => void;
  onNavigate: (page: PageName) => void;
}

function readShopSlugFromUrl(): string | null {
  const params = new URLSearchParams(window.location.search);
  return params.get('shop');
}

function writeShopSlugToUrl(slug: string | null): void {
  const params = new URLSearchParams(window.location.search);

  if (slug) {
    params.set('shop', slug);
  } else {
    params.delete('shop');
  }

  const query = params.toString();
  window.history.pushState({}, '', query ? `/?${query}` : '/');
}

export function ShopsPage({ onChanged, onNavigate }: ShopsPageProps) {
  const [shops, setShops] = useState<ShopSummary[]>([]);
  const [currentShopSlug, setCurrentShopSlug] = useState<string | null>(() => readShopSlugFromUrl());
  const [detail, setDetail] = useState<ShopDetailResponse | null>(null);
  const [category, setCategory] = useState('all');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [showShortcuts, setShowShortcuts] = useState(false);
  const [paymentType, setPaymentType] = useState<PaymentType>('cash');

  useEffect(() => {
    void loadShops();

    const handlePopState = () => setCurrentShopSlug(readShopSlugFromUrl());
    window.addEventListener('popstate', handlePopState);

    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  useEffect(() => {
    if (currentShopSlug) {
      void loadDetail(currentShopSlug);
      writeShopSlugToUrl(currentShopSlug);
      return;
    }

    setDetail(null);
    writeShopSlugToUrl(null);
  }, [currentShopSlug]);

  const categories = useMemo(() => {
    if (!detail) return [];
    return Array.from(new Set(detail.items.map((item) => item.category))).sort();
  }, [detail]);

  const visibleItems = useMemo(() => {
    if (!detail) return [];
    return detail.items.filter((item) => category === 'all' || item.category === category);
  }, [detail, category]);

  const currentLocationLabel = useMemo(() => {
    const current = detail?.currentLocation;

    if (current) {
      return `${current.region_name} / ${current.location_name}`;
    }

    const firstAvailable = shops.find((shop) => shop.local_presence_satisfied);
    return firstAvailable ? `${firstAvailable.region_name} / ${firstAvailable.location_name}` : 'Open the map to pick a shop location';
  }, [detail, shops]);

  async function loadShops(): Promise<void> {
    setError('');
    try {
      const response = await getShops();
      setShops(response.data);
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
      const response = await buyFromShop(detail.shop.slug, item.item_key, 1, paymentType);
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
        title="Map Shops & Dealers"
        description="Shops now live on map hotspots. Use the map shop icons to open a local catalog, then travel there to buy or sell. Inventory stays focused on owned gear."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="shop-map-hero card">
        <div>
          <p className="eyebrow">Current location</p>
          <h2>{currentLocationLabel}</h2>
          <p className="muted">Use the World Map shop icons to choose the exact store. This page opens the selected shop catalog, not a global equipment market.</p>
        </div>
        <div className="shop-hero-actions">
          <button className="btn primary" onClick={() => onNavigate('world map')}>Open World Map</button>
          <button className="btn" onClick={() => onNavigate('equipment')}>Manage Inventory</button>
          {detail && <button className="btn" onClick={() => setCurrentShopSlug(null)}>Close Shop</button>}
        </div>
      </div>

      {!detail ? (
        <div className="shops-map-first-layout">
          <section className="card shop-map-instructions">
            <p className="eyebrow">How to use shops</p>
            <h2>Pick a shop icon on the map</h2>
            <p>
              Travel to a shop hotspot to trade. Tool shops, workwear stores, garages, medical counters, pawn fences,
              and shady contacts now live in the world instead of inside Inventory.
            </p>
            <div className="shop-instruction-grid">
              <span>🛒 Known legal shop</span>
              <span>◆ Black-market contact</span>
              <span>Travel required for buying/selling</span>
              <span>Inventory is owned gear only</span>
            </div>
            <button className="btn primary" onClick={() => onNavigate('world map')}>Go to map</button>
          </section>

          <section className="card known-shop-shortcuts">
            <div className="card-heading small-heading">
              <div>
                <p className="eyebrow">Known shop shortcuts</p>
                <h2>Optional quick access</h2>
                <p className="muted">The clean flow is map → hotspot shop icon → shop. Open these shortcuts only when you already know what you need.</p>
              </div>
              <button className="btn" onClick={() => setShowShortcuts((open) => !open)}>
                {showShortcuts ? 'Hide shortcuts' : `Show ${shops.length} known shops`}
              </button>
            </div>

            {showShortcuts && (
              <div className="shop-shortcut-grid">
                {shops.map((shop) => (
                  <ShopCard
                    key={shop.slug}
                    shop={shop}
                    selected={currentShopSlug === shop.slug}
                    onSelect={(selectedShop) => setCurrentShopSlug(selectedShop.slug)}
                    compact
                  />
                ))}
              </div>
            )}
          </section>
        </div>
      ) : (
        <div className="shop-detail-layout">
          <aside className="card shop-detail-summary">
            <p className="eyebrow">{detail.shop.shop_type.replace(/_/g, ' ')}</p>
            <h2>{detail.shop.name}</h2>
            <p>{detail.shop.description}</p>
            <ShopLocationNotice shop={detail.shop} />
            <div className="location-effect-summary shop-summary-pills">
              <span className="info-pill">{detail.shop.is_legal ? 'Legal shop' : 'Shady contact'}</span>
              {detail.shop.is_black_market && <span className="info-pill">Black market</span>}
              <span className="info-pill">Heat risk {detail.shop.heat_risk}</span>
              <span className="info-pill">Catalog {detail.items.length}</span>
            </div>
            <PaymentTypeSelector
              value={paymentType}
              options={(detail.shop.accepted_payment_types || (detail.shop.is_black_market ? ['dirty_money', 'cash'] : ['cash'])) as PaymentType[]}
              onChange={setPaymentType}
            />
            <button className="btn full-width" onClick={() => onNavigate('world map')}>Open map location</button>
          </aside>

          <main className="card shop-catalog-card">
            <section className="page-subsection">
              <div className="card-heading small-heading">
                <div>
                  <p className="eyebrow">Buy catalog</p>
                  <h2>Available gear</h2>
                  <p className="muted">Images are previews only. Price, stock, requirements, and disabled status come from backend shop config and database stock.</p>
                </div>
              </div>
              <ShopCategoryFilter value={category} categories={categories} onChange={setCategory} />
              <div className="shop-item-grid">
                {visibleItems.map((item) => (
                  <ShopItemCard key={item.id} item={item} busy={busy} onBuy={buy} />
                ))}
              </div>
            </section>

            <ShopTransactionPanel items={detail.sellableInventory} busy={busy} onSell={sell} />
          </main>
        </div>
      )}
    </section>
  );
}

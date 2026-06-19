import type { ShopSummary } from '../../types/shop';

export function ShopLocationNotice({ shop }: { shop: ShopSummary }) {
  return (
    <div className={shop.local_presence_satisfied ? 'notice success' : 'notice warning'}>
      <strong>{shop.local_presence_satisfied ? 'You are here.' : 'Travel required.'}</strong>
      <span>
        {' '}Located at {shop.location_label}. {shop.local_presence_satisfied ? 'Buying and selling are unlocked.' : 'You can browse remotely, but transactions require local presence.'}
      </span>
    </div>
  );
}

export function ShopStockBadge({ stock, maxStock }: { stock?: number | null; maxStock?: number | null }) {
  if (stock === null || stock === undefined) {
    return <span className="info-pill">No stock limit</span>;
  }

  return <span className="info-pill">Stock {stock}{maxStock ? `/${maxStock}` : ''}</span>;
}

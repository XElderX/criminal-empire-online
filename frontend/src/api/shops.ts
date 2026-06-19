import { api } from './client';
import type { ShopDetailResponse, ShopsListResponse, ShopTransactionResponse } from '../types/shop';

export function getShops(): Promise<ShopsListResponse> {
  return api<ShopsListResponse>('/shops');
}

export function getShop(slug: string): Promise<ShopDetailResponse> {
  return api<ShopDetailResponse>(`/shops/${encodeURIComponent(slug)}`);
}

export function buyFromShop(slug: string, itemKey: string, quantity = 1): Promise<ShopTransactionResponse> {
  return api<ShopTransactionResponse>(`/shops/${encodeURIComponent(slug)}/buy`, {
    method: 'POST',
    body: JSON.stringify({ item_key: itemKey, quantity }),
  });
}

export function sellToShop(slug: string, itemKey: string, quantity = 1): Promise<ShopTransactionResponse> {
  return api<ShopTransactionResponse>(`/shops/${encodeURIComponent(slug)}/sell`, {
    method: 'POST',
    body: JSON.stringify({ item_key: itemKey, quantity }),
  });
}

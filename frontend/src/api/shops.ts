import { api } from './client';
import type { ShopDetailResponse, ShopsListResponse, ShopTransactionResponse } from '../types/shop';

export function getShops(): Promise<ShopsListResponse> {
  return api<ShopsListResponse>('/shops');
}

export function getShop(slug: string): Promise<ShopDetailResponse> {
  return api<ShopDetailResponse>(`/shops/${encodeURIComponent(slug)}`);
}

export function buyFromShop(slug: string, itemKey: string, quantity = 1, paymentType = 'cash'): Promise<ShopTransactionResponse> {
  return api<ShopTransactionResponse>(`/shops/${encodeURIComponent(slug)}/buy`, {
    method: 'POST',
    body: JSON.stringify({ item_key: itemKey, quantity, payment_type: paymentType }),
  });
}

export function sellToShop(slug: string, itemKey: string, quantity = 1): Promise<ShopTransactionResponse> {
  return api<ShopTransactionResponse>(`/shops/${encodeURIComponent(slug)}/sell`, {
    method: 'POST',
    body: JSON.stringify({ item_key: itemKey, quantity }),
  });
}

export function getShopPaymentOptions(slug: string): Promise<{ data: Array<'cash' | 'bank' | 'dirty_money'> }> {
  return api<{ data: Array<'cash' | 'bank' | 'dirty_money'> }>(`/shops/${encodeURIComponent(slug)}/payment-options`);
}

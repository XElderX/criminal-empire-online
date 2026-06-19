import { api } from './client';
import type {
  ExploreHotspotResponse,
  LocationActivitiesResponse,
  LocationMapResponse,
  RegionMapResponse,
  TravelRequest,
  TravelResponse,
  UserLocationState,
  WorldMapResponse,
} from '../types/worldMap';

export function getWorldMap(): Promise<WorldMapResponse> {
  return api<WorldMapResponse>('/world-map');
}

export function getRegionMap(regionSlug: string): Promise<RegionMapResponse> {
  return api<RegionMapResponse>(`/world-map/regions/${regionSlug}`);
}

export function getLocationMap(locationSlug: string): Promise<LocationMapResponse> {
  return api<LocationMapResponse>(`/world-map/locations/${locationSlug}`);
}

export async function getCurrentLocation(): Promise<UserLocationState> {
  const response = await api<{ currentLocation: UserLocationState }>('/world-map/current-location');
  return response.currentLocation;
}

export function travelToLocation(payload: TravelRequest): Promise<TravelResponse> {
  return api<TravelResponse>('/world-map/travel', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}


export function getLocationActivities(locationSlug: string): Promise<LocationActivitiesResponse> {
  return api<LocationActivitiesResponse>(`/world-map/locations/${locationSlug}/activities`);
}

export function exploreHotspot(locationSlug: string): Promise<ExploreHotspotResponse> {
  return api<ExploreHotspotResponse>(`/world-map/locations/${locationSlug}/explore`, {
    method: 'POST',
  });
}

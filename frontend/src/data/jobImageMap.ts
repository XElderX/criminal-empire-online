import { slugify } from '../utils/stringFormat';

const DEFAULT_JOB_IMAGE = '/assets/placeholders/default_job.webp';

const jobImageMap: Record<string, string> = {
  back_alley_collection: '/assets/jobs/dirty_job_back_alley_collection.webp',
  dirty_job_back_alley_collection: '/assets/jobs/dirty_job_back_alley_collection.webp',
  warehouse_pickup: '/assets/jobs/dirty_job_warehouse_pickup.webp',
  warehouse_loading: '/assets/jobs/dirty_job_warehouse_pickup.webp',
  night_delivery: '/assets/jobs/dirty_job_night_delivery.webp',
  evidence_cleanup: '/assets/jobs/dirty_job_evidence_cleanup.webp',
  protection_visit: '/assets/jobs/dirty_job_protection_visit.webp',
  stolen_goods_pickup: '/assets/jobs/dirty_job_stolen_goods_pickup.webp',
  stolen_goods_run: '/assets/jobs/dirty_job_stolen_goods_pickup.webp',
  debt_pressure: '/assets/jobs/dirty_job_debt_pressure.webp',
  fake_document_run: '/assets/jobs/dirty_job_fake_document_run.webp',
  dockside_drop: '/assets/jobs/dirty_job_dockside_drop.webp',
  cargo_theft: '/assets/jobs/dirty_job_cargo_theft.webp',
};

export function getJobImage(value: string | null | undefined): string {
  return jobImageMap[slugify(value)] || DEFAULT_JOB_IMAGE;
}

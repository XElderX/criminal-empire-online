import { slugify } from '../utils/stringFormat';

const DEFAULT_JOB_IMAGE = '/assets/placeholders/default_job.svg';

const jobImageMap: Record<string, string> = {
  back_alley_collection: '/assets/jobs/dirty_job_back_alley_collection.svg',
  dirty_job_back_alley_collection: '/assets/jobs/dirty_job_back_alley_collection.svg',
  warehouse_pickup: '/assets/jobs/dirty_job_warehouse_pickup.svg',
  warehouse_loading: '/assets/jobs/dirty_job_warehouse_pickup.svg',
  night_delivery: '/assets/jobs/dirty_job_night_delivery.svg',
  evidence_cleanup: '/assets/jobs/dirty_job_evidence_cleanup.svg',
  protection_visit: '/assets/jobs/dirty_job_protection_visit.svg',
  stolen_goods_pickup: '/assets/jobs/dirty_job_stolen_goods_pickup.svg',
  stolen_goods_run: '/assets/jobs/dirty_job_stolen_goods_pickup.svg',
  debt_pressure: '/assets/jobs/dirty_job_debt_pressure.svg',
  fake_document_run: '/assets/jobs/dirty_job_fake_document_run.svg',
  dockside_drop: '/assets/jobs/dirty_job_dockside_drop.svg',
  cargo_theft: '/assets/jobs/dirty_job_cargo_theft.svg',
};

export function getJobImage(value: string | null | undefined): string {
  return jobImageMap[slugify(value)] || DEFAULT_JOB_IMAGE;
}

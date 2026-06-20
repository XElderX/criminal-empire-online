<?php

namespace App\Services;

final class AdminLogService
{
    public const TYPES = ['system', 'audit', 'economy', 'heat', 'crime', 'dirty_job', 'shop', 'travel', 'tutorial', 'error'];

    public function list(array $query): array
    {
        $type = (string) ($query['type'] ?? 'audit');
        $pagination = new PaginationService();

        if ($type === 'economy') {
            return $pagination->query('SELECT * FROM economy_ledger ORDER BY id DESC', 'SELECT COUNT(*) FROM economy_ledger', [], $query);
        }
        if ($type === 'heat') {
            return $pagination->query('SELECT * FROM heat_logs ORDER BY id DESC', 'SELECT COUNT(*) FROM heat_logs', [], $query);
        }
        if ($type === 'crime') {
            return $pagination->query('SELECT * FROM crime_logs ORDER BY id DESC', 'SELECT COUNT(*) FROM crime_logs', [], $query);
        }
        if ($type === 'dirty_job') {
            return $pagination->query('SELECT * FROM dirty_job_runs ORDER BY id DESC', 'SELECT COUNT(*) FROM dirty_job_runs', [], $query);
        }
        if ($type === 'shop') {
            return $pagination->query('SELECT * FROM shop_transactions ORDER BY id DESC', 'SELECT COUNT(*) FROM shop_transactions', [], $query);
        }
        if ($type === 'travel') {
            return $pagination->query('SELECT * FROM user_travel_logs ORDER BY id DESC', 'SELECT COUNT(*) FROM user_travel_logs', [], $query);
        }
        if ($type === 'tutorial') {
            return $pagination->query('SELECT * FROM user_tutorial_progress ORDER BY id DESC', 'SELECT COUNT(*) FROM user_tutorial_progress', [], $query);
        }

        return $pagination->query('SELECT * FROM audit_logs ORDER BY id DESC', 'SELECT COUNT(*) FROM audit_logs', [], $query);
    }
}

<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class PaginationService
{
    public const DEFAULT_PER_PAGE = 30;
    public const MAX_PER_PAGE = 30;

    public function page(array $query): int
    {
        return max(1, (int) ($query['page'] ?? 1));
    }

    public function perPage(array $query): int
    {
        $requested = (int) ($query['limit'] ?? $query['per_page'] ?? self::DEFAULT_PER_PAGE);
        return max(1, min(self::MAX_PER_PAGE, $requested));
    }

    /** @param array<int, mixed> $params */
    public function query(string $sql, string $countSql, array $params, array $query): array
    {
        $page = $this->page($query);
        $perPage = $this->perPage($query);
        $offset = ($page - 1) * $perPage;
        $pdo = Database::pdo();

        $countStatement = $pdo->prepare($countSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $statement = $pdo->prepare($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset);
        foreach (array_values($params) as $index => $value) {
            $statement->bindValue($index + 1, $value);
        }
        $statement->execute();
        $data = $statement->fetchAll();
        $totalPages = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1,
            ],
        ];
    }
}

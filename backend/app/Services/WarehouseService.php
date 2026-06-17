<?php

namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class WarehouseService
{
    public function listings(array $user): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    listing.*,
                    type.code AS building_type_code,
                    type.name AS building_type_name,
                    territory.name AS territory_name,
                    npc.first_name AS seller_first_name,
                    npc.last_name AS seller_last_name,
                    npc.nickname AS seller_nickname,
                    EXISTS(
                        SELECT 1
                        FROM player_buildings building
                        WHERE building.user_id = ?
                          AND building.source_listing_id = listing.id
                    ) AS already_owned
                FROM property_listings listing
                JOIN building_types type ON type.id = listing.building_type_id
                JOIN territories territory ON territory.id = listing.territory_id
                LEFT JOIN npcs npc ON npc.id = listing.seller_npc_id
                WHERE listing.status = 'available'
                  AND type.code = 'warehouse'
                ORDER BY listing.purchase_price
            SQL
        );
        $statement->execute([$user['id']]);
        $listings = $statement->fetchAll();

        foreach ($listings as &$listing) {
            $listing['can_purchase'] = !(bool) $listing['already_owned']
                && (int) $user['cash'] >= (int) $listing['purchase_price'];
        }

        return $listings;
    }

    public function purchase(array $user, int $listingId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $listingStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT listing.*, type.code AS building_type_code
                    FROM property_listings listing
                    JOIN building_types type ON type.id = listing.building_type_id
                    WHERE listing.id = ?
                    FOR UPDATE
                SQL
            );
            $listingStatement->execute([$listingId]);
            $listing = $listingStatement->fetch();

            if (!$listing || $listing['status'] !== 'available') {
                throw new RuntimeException('Warehouse listing is unavailable.');
            }

            if ($listing['building_type_code'] !== 'warehouse') {
                throw new RuntimeException('This listing is not a warehouse.');
            }

            $duplicateStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT COUNT(*)
                    FROM player_buildings
                    WHERE user_id = ?
                      AND source_listing_id = ?
                SQL
            );
            $duplicateStatement->execute([$user['id'], $listingId]);

            if ((int) $duplicateStatement->fetchColumn() > 0) {
                throw new RuntimeException('This warehouse listing was already purchased.');
            }

            $userStatement = $pdo->prepare(
                'SELECT * FROM users WHERE id = ? FOR UPDATE'
            );
            $userStatement->execute([$user['id']]);
            $freshUser = $userStatement->fetch();

            if ((int) $freshUser['cash'] < (int) $listing['purchase_price']) {
                throw new RuntimeException('Not enough cash to purchase this warehouse.');
            }

            $pdo->prepare(
                'UPDATE users SET cash = cash - ?, updated_at = NOW() WHERE id = ?'
            )->execute([$listing['purchase_price'], $freshUser['id']]);

            $insert = $pdo->prepare(
                <<<'SQL'
                    INSERT INTO player_buildings (
                        user_id,
                        building_type_id,
                        source_listing_id,
                        territory_id,
                        name,
                        storage_capacity,
                        vehicle_capacity,
                        security_rating,
                        condition_rating,
                        weekly_operating_cost,
                        operating_debt,
                        heat_visibility,
                        status,
                        purchased_at,
                        last_cost_processed_at,
                        created_at,
                        updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?,
                        'active', NOW(), NOW(), NOW(), NOW()
                    )
                SQL
            );

            $insert->execute([
                $freshUser['id'],
                $listing['building_type_id'],
                $listing['id'],
                $listing['territory_id'],
                $listing['name'],
                $listing['storage_capacity'],
                $listing['vehicle_capacity'],
                $listing['security_rating'],
                $listing['condition_rating'],
                $listing['weekly_operating_cost'],
                $listing['heat_visibility'],
            ]);

            $warehouseId = (int) $pdo->lastInsertId();

            (new EconomyLedgerService())->record(
                'property_purchase',
                (int) $listing['purchase_price'],
                "Purchased warehouse: {$listing['name']}",
                [
                    'source_type' => 'player',
                    'source_id' => $freshUser['id'],
                    'destination_type' => 'npc_property_seller',
                    'destination_id' => $listing['seller_npc_id'],
                    'user_id' => $freshUser['id'],
                    'npc_id' => $listing['seller_npc_id'],
                    'territory_id' => $listing['territory_id'],
                ]
            );

            AuditService::log(
                (int) $freshUser['id'],
                'warehouse.purchase',
                [
                    'listing_id' => $listingId,
                    'warehouse_id' => $warehouseId,
                    'price' => (int) $listing['purchase_price'],
                ]
            );

            $pdo->commit();

            return [
                'message' => 'Warehouse purchased.',
                'warehouse_id' => $warehouseId,
                'name' => $listing['name'],
                'purchase_price' => (int) $listing['purchase_price'],
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function overview(array $user): array
    {
        $warehouses = $this->warehousesForUser((int) $user['id']);

        foreach ($warehouses as &$warehouse) {
            $warehouse = $this->hydrateWarehouse($warehouse);
        }

        return [
            'warehouses' => $warehouses,
            'listings' => $this->listings($user),
            'upgrade_catalog' => $this->upgradeCatalog(),
        ];
    }

    public function transfer(
        array $user,
        int $warehouseId,
        string $direction,
        string $assetType,
        int $assetId,
        int $quantity
    ): array {
        if (!in_array($direction, ['deposit', 'withdraw'], true)) {
            throw new RuntimeException('Invalid transfer direction.');
        }

        if (!in_array($assetType, ['item', 'weapon', 'drug'], true)) {
            throw new RuntimeException('Invalid storage asset type.');
        }

        if ($quantity < 1) {
            throw new RuntimeException('Quantity must be positive.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $warehouse = $this->lockWarehouse($warehouseId, (int) $user['id']);
            $asset = $this->loadStorageAsset($assetType, $assetId);
            $unitsEach = $this->storageUnitsEach($assetType, $asset);

            if ($direction === 'deposit') {
                $this->deposit(
                    $user,
                    $warehouse,
                    $assetType,
                    $asset,
                    $quantity,
                    $unitsEach
                );
            } else {
                $this->withdraw(
                    $user,
                    $warehouse,
                    $assetType,
                    $asset,
                    $quantity
                );
            }

            $this->writeStorageLog(
                $warehouseId,
                (int) $user['id'],
                $direction,
                $assetType,
                (int) $asset['id'],
                $quantity,
                ucfirst($direction) . " {$quantity} × {$asset['name']}"
            );

            $pdo->commit();

            return [
                'message' => $direction === 'deposit'
                    ? 'Asset deposited into the warehouse.'
                    : 'Asset withdrawn from the warehouse.',
                'warehouse' => $this->hydrateWarehouse(
                    $this->findWarehouse($warehouseId, (int) $user['id'])
                ),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function storeVehicle(
        array $user,
        int $warehouseId,
        int $vehicleId
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $warehouse = $this->lockWarehouse($warehouseId, (int) $user['id']);
            $vehicleStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT *
                    FROM vehicles
                    WHERE id = ?
                      AND user_id = ?
                    FOR UPDATE
                SQL
            );
            $vehicleStatement->execute([$vehicleId, $user['id']]);
            $vehicle = $vehicleStatement->fetch();

            if (!$vehicle) {
                throw new RuntimeException('Vehicle not found.');
            }

            if ($vehicle['status'] === 'stored') {
                throw new RuntimeException('This vehicle is already stored.');
            }

            $usedSlots = $this->usedVehicleSlots($warehouseId);

            if ($usedSlots >= (int) $warehouse['vehicle_capacity']) {
                throw new RuntimeException('The warehouse has no free vehicle slots.');
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE vehicles
                    SET
                        warehouse_id = ?,
                        status = 'stored',
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$warehouseId, $vehicleId]);

            $this->writeStorageLog(
                $warehouseId,
                (int) $user['id'],
                'vehicle_store',
                'vehicle',
                $vehicleId,
                1,
                "Stored vehicle: {$vehicle['name']}"
            );

            $pdo->commit();

            return ['message' => 'Vehicle stored in the warehouse.'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function removeVehicle(
        array $user,
        int $warehouseId,
        int $vehicleId
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $this->lockWarehouse($warehouseId, (int) $user['id']);

            $vehicleStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT *
                    FROM vehicles
                    WHERE id = ?
                      AND user_id = ?
                      AND warehouse_id = ?
                      AND status = 'stored'
                    FOR UPDATE
                SQL
            );
            $vehicleStatement->execute([
                $vehicleId,
                $user['id'],
                $warehouseId,
            ]);
            $vehicle = $vehicleStatement->fetch();

            if (!$vehicle) {
                throw new RuntimeException('Stored vehicle not found.');
            }

            $pdo->prepare(
                <<<'SQL'
                    UPDATE vehicles
                    SET
                        warehouse_id = NULL,
                        status = 'unsecured',
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([$vehicleId]);

            $this->writeStorageLog(
                $warehouseId,
                (int) $user['id'],
                'vehicle_remove',
                'vehicle',
                $vehicleId,
                1,
                "Removed vehicle: {$vehicle['name']}"
            );

            $pdo->commit();

            return ['message' => 'Vehicle removed from the warehouse.'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function purchaseUpgrade(
        array $user,
        int $warehouseId,
        int $upgradeId
    ): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $warehouse = $this->lockWarehouse($warehouseId, (int) $user['id']);

            $upgradeStatement = $pdo->prepare(
                'SELECT * FROM building_upgrades WHERE id = ? FOR UPDATE'
            );
            $upgradeStatement->execute([$upgradeId]);
            $upgrade = $upgradeStatement->fetch();

            if (!$upgrade) {
                throw new RuntimeException('Warehouse upgrade not found.');
            }

            $existingStatement = $pdo->prepare(
                <<<'SQL'
                    SELECT COUNT(*)
                    FROM player_building_upgrades
                    WHERE player_building_id = ?
                      AND building_upgrade_id = ?
                SQL
            );
            $existingStatement->execute([$warehouseId, $upgradeId]);

            if ((int) $existingStatement->fetchColumn() > 0) {
                throw new RuntimeException('This upgrade is already installed.');
            }

            $userStatement = $pdo->prepare(
                'SELECT cash FROM users WHERE id = ? FOR UPDATE'
            );
            $userStatement->execute([$user['id']]);
            $cash = (int) $userStatement->fetchColumn();

            if ($cash < (int) $upgrade['price']) {
                throw new RuntimeException('Not enough cash for this upgrade.');
            }

            $effects = json_decode((string) $upgrade['effects'], true) ?: [];
            $securityBonus = (int) ($effects['security_bonus'] ?? 0);
            $storageBonus = (int) ($effects['storage_bonus'] ?? 0);
            $visibilityModifier = (int) ($effects['heat_visibility_modifier'] ?? 0);

            $pdo->prepare(
                'UPDATE users SET cash = cash - ?, updated_at = NOW() WHERE id = ?'
            )->execute([$upgrade['price'], $user['id']]);

            $pdo->prepare(
                <<<'SQL'
                    INSERT INTO player_building_upgrades (
                        player_building_id,
                        building_upgrade_id,
                        installed_at
                    ) VALUES (?, ?, NOW())
                SQL
            )->execute([$warehouseId, $upgradeId]);

            $pdo->prepare(
                <<<'SQL'
                    UPDATE player_buildings
                    SET
                        security_rating = LEAST(100, security_rating + ?),
                        storage_capacity = storage_capacity + ?,
                        heat_visibility = GREATEST(0, heat_visibility + ?),
                        updated_at = NOW()
                    WHERE id = ?
                SQL
            )->execute([
                $securityBonus,
                $storageBonus,
                $visibilityModifier,
                $warehouseId,
            ]);

            (new EconomyLedgerService())->record(
                'warehouse_upgrade',
                (int) $upgrade['price'],
                "Installed warehouse upgrade: {$upgrade['name']}",
                [
                    'source_type' => 'player',
                    'source_id' => $user['id'],
                    'destination_type' => 'npc_contractor',
                    'user_id' => $user['id'],
                    'territory_id' => $warehouse['territory_id'],
                ]
            );

            $pdo->commit();

            return ['message' => 'Warehouse upgrade installed.'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function processWeeklyCosts(): array
    {
        $pdo = Database::pdo();
        $buildings = $pdo->query(
            <<<'SQL'
                SELECT *
                FROM player_buildings
                WHERE status <> 'closed'
                  AND (
                    last_cost_processed_at IS NULL
                    OR last_cost_processed_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  )
                ORDER BY id
            SQL
        )->fetchAll();

        $summary = [
            'buildings_processed' => count($buildings),
            'operating_cost_paid' => 0,
            'operating_debt_remaining' => 0,
        ];

        foreach ($buildings as $building) {
            $pdo->beginTransaction();

            try {
                $userStatement = $pdo->prepare(
                    'SELECT cash FROM users WHERE id = ? FOR UPDATE'
                );
                $userStatement->execute([$building['user_id']]);
                $cash = (int) $userStatement->fetchColumn();

                $currentCost = (int) $building['weekly_operating_cost'];
                $previousDebt = (int) $building['operating_debt'];
                $totalDue = $currentCost + $previousDebt;
                $payment = min($cash, $totalDue);
                $remainingDebt = $totalDue - $payment;

                if ($payment > 0) {
                    $pdo->prepare(
                        <<<'SQL'
                            UPDATE users
                            SET cash = cash - ?, updated_at = NOW()
                            WHERE id = ?
                        SQL
                    )->execute([
                        $payment,
                        $building['user_id'],
                    ]);

                    (new EconomyLedgerService())->record(
                        'warehouse_operating_cost',
                        $payment,
                        "Warehouse operating cost: {$building['name']}",
                        [
                            'source_type' => 'player',
                            'source_id' => $building['user_id'],
                            'destination_type' => 'property_economy_sink',
                            'user_id' => $building['user_id'],
                            'territory_id' => $building['territory_id'],
                        ]
                    );
                }

                $status = $remainingDebt > 0 ? 'restricted' : 'active';
                $securityPenalty = $remainingDebt > 0 ? 3 : 0;

                $pdo->prepare(
                    <<<'SQL'
                        UPDATE player_buildings
                        SET
                            operating_debt = ?,
                            status = ?,
                            security_rating = GREATEST(5, security_rating - ?),
                            last_cost_processed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    SQL
                )->execute([
                    $remainingDebt,
                    $status,
                    $securityPenalty,
                    $building['id'],
                ]);

                $summary['operating_cost_paid'] += $payment;
                $summary['operating_debt_remaining'] += $remainingDebt;

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }
        }

        return $summary;
    }

    public function firstWarehouseForUser(int $userId): ?array
    {
        $warehouses = $this->warehousesForUser($userId);

        return $warehouses[0] ?? null;
    }

    public function usedStorageUnits(int $warehouseId): float
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COALESCE(SUM(quantity * storage_units_each), 0)
                FROM warehouse_storage
                WHERE warehouse_id = ?
            SQL
        );
        $statement->execute([$warehouseId]);

        return (float) $statement->fetchColumn();
    }

    private function deposit(
        array $user,
        array $warehouse,
        string $assetType,
        array $asset,
        int $quantity,
        float $unitsEach
    ): void {
        $availableQuantity = $this->personalAvailableQuantity(
            (int) $user['id'],
            $assetType,
            (int) $asset['id']
        );

        if ($availableQuantity < $quantity) {
            throw new RuntimeException('Not enough unassigned inventory is available.');
        }

        $usedUnits = $this->usedStorageUnits((int) $warehouse['id']);
        $newUnits = $quantity * $unitsEach;

        if ($usedUnits + $newUnits > (float) $warehouse['storage_capacity']) {
            throw new RuntimeException('Warehouse storage capacity would be exceeded.');
        }

        $this->adjustPersonalInventory(
            (int) $user['id'],
            $assetType,
            (int) $asset['id'],
            -$quantity
        );

        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO warehouse_storage (
                    warehouse_id,
                    asset_type,
                    asset_id,
                    quantity,
                    reserved_quantity,
                    storage_units_each,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, 0, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    storage_units_each = VALUES(storage_units_each),
                    updated_at = NOW()
            SQL
        );

        $statement->execute([
            $warehouse['id'],
            $assetType,
            $asset['id'],
            $quantity,
            $unitsEach,
        ]);
    }

    private function withdraw(
        array $user,
        array $warehouse,
        string $assetType,
        array $asset,
        int $quantity
    ): void {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM warehouse_storage
                WHERE warehouse_id = ?
                  AND asset_type = ?
                  AND asset_id = ?
                FOR UPDATE
            SQL
        );
        $statement->execute([
            $warehouse['id'],
            $assetType,
            $asset['id'],
        ]);
        $storage = $statement->fetch();

        if (!$storage) {
            throw new RuntimeException('The asset is not stored in this warehouse.');
        }

        $available = (int) $storage['quantity']
            - (int) $storage['reserved_quantity'];

        if ($available < $quantity) {
            throw new RuntimeException('Not enough unreserved quantity is available.');
        }

        Database::pdo()->prepare(
            <<<'SQL'
                UPDATE warehouse_storage
                SET
                    quantity = quantity - ?,
                    updated_at = NOW()
                WHERE id = ?
            SQL
        )->execute([$quantity, $storage['id']]);

        Database::pdo()->prepare(
            'DELETE FROM warehouse_storage WHERE id = ? AND quantity = 0'
        )->execute([$storage['id']]);

        $this->adjustPersonalInventory(
            (int) $user['id'],
            $assetType,
            (int) $asset['id'],
            $quantity
        );
    }

    private function personalAvailableQuantity(
        int $userId,
        string $assetType,
        int $assetId
    ): int {
        $ownedQuantity = $this->personalQuantity($userId, $assetType, $assetId);

        if (!in_array($assetType, ['item', 'weapon'], true)) {
            return $ownedQuantity;
        }

        $equippedStatement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM crew_equipment
                WHERE user_id = ?
                  AND asset_type = ?
                  AND asset_id = ?
            SQL
        );
        $equippedStatement->execute([$userId, $assetType, $assetId]);
        $equippedQuantity = (int) $equippedStatement->fetchColumn();

        return max(0, $ownedQuantity - $equippedQuantity);
    }

    private function personalQuantity(
        int $userId,
        string $assetType,
        int $assetId
    ): int {
        [$table, $assetColumn] = match ($assetType) {
            'item' => ['user_items', 'item_definition_id'],
            'weapon' => ['user_weapons', 'weapon_id'],
            'drug' => ['user_drugs', 'drug_id'],
        };

        $statement = Database::pdo()->prepare(
            "SELECT quantity FROM {$table} WHERE user_id = ? AND {$assetColumn} = ? FOR UPDATE"
        );
        $statement->execute([$userId, $assetId]);

        return (int) ($statement->fetchColumn() ?: 0);
    }

    private function adjustPersonalInventory(
        int $userId,
        string $assetType,
        int $assetId,
        int $quantityChange
    ): void {
        [$table, $assetColumn, $timestamps] = match ($assetType) {
            'item' => ['user_items', 'item_definition_id', true],
            'weapon' => ['user_weapons', 'weapon_id', true],
            'drug' => ['user_drugs', 'drug_id', false],
        };

        if ($quantityChange < 0) {
            $amount = abs($quantityChange);
            $sql = $timestamps
                ? "UPDATE {$table} SET quantity = quantity - ?, updated_at = NOW() WHERE user_id = ? AND {$assetColumn} = ?"
                : "UPDATE {$table} SET quantity = quantity - ? WHERE user_id = ? AND {$assetColumn} = ?";

            Database::pdo()->prepare($sql)->execute([
                $amount,
                $userId,
                $assetId,
            ]);

            return;
        }

        if ($timestamps) {
            $sql = <<<SQL
                INSERT INTO {$table} (
                    user_id,
                    {$assetColumn},
                    quantity,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    updated_at = NOW()
            SQL;
        } else {
            $sql = <<<SQL
                INSERT INTO {$table} (user_id, {$assetColumn}, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity)
            SQL;
        }

        Database::pdo()->prepare($sql)->execute([
            $userId,
            $assetId,
            $quantityChange,
        ]);
    }

    private function loadStorageAsset(string $assetType, int $assetId): array
    {
        $sql = match ($assetType) {
            'item' => <<<'SQL'
                SELECT id, name, storage_units
                FROM item_definitions
                WHERE id = ?
            SQL,
            'weapon' => <<<'SQL'
                SELECT id, name, storage_units
                FROM weapons
                WHERE id = ?
            SQL,
            'drug' => <<<'SQL'
                SELECT id, name
                FROM drugs
                WHERE id = ?
            SQL,
        };

        $statement = Database::pdo()->prepare($sql);
        $statement->execute([$assetId]);
        $asset = $statement->fetch();

        if (!$asset) {
            throw new RuntimeException('Storage asset not found.');
        }

        return $asset;
    }

    private function storageUnitsEach(string $assetType, array $asset): float
    {
        return match ($assetType) {
            'item' => (float) ($asset['storage_units'] ?? 1),
            'weapon' => (float) ($asset['storage_units'] ?? GameConfig::WAREHOUSE_DEFAULT_WEAPON_UNITS),
            'drug' => GameConfig::WAREHOUSE_DRUG_UNITS_PER_TEN / 10,
        };
    }

    private function hydrateWarehouse(array $warehouse): array
    {
        $warehouse['used_storage_capacity'] = $this->usedStorageUnits(
            (int) $warehouse['id']
        );
        $warehouse['available_storage_capacity'] = max(
            0,
            (float) $warehouse['storage_capacity']
                - $warehouse['used_storage_capacity']
        );
        $warehouse['used_vehicle_slots'] = $this->usedVehicleSlots(
            (int) $warehouse['id']
        );
        $warehouse['available_vehicle_slots'] = max(
            0,
            (int) $warehouse['vehicle_capacity']
                - $warehouse['used_vehicle_slots']
        );
        $warehouse['storage'] = $this->storageRows((int) $warehouse['id']);
        $warehouse['vehicles'] = $this->vehicles((int) $warehouse['id']);
        $warehouse['upgrades'] = $this->installedUpgrades((int) $warehouse['id']);
        $warehouse['recent_logs'] = $this->storageLogs((int) $warehouse['id']);

        return $warehouse;
    }

    private function warehousesForUser(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    building.*,
                    type.code AS building_type_code,
                    type.name AS building_type_name,
                    territory.name AS territory_name
                FROM player_buildings building
                JOIN building_types type ON type.id = building.building_type_id
                JOIN territories territory ON territory.id = building.territory_id
                WHERE building.user_id = ?
                  AND type.code = 'warehouse'
                  AND building.status <> 'closed'
                ORDER BY building.purchased_at, building.id
            SQL
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    private function findWarehouse(int $warehouseId, int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    building.*,
                    type.code AS building_type_code,
                    type.name AS building_type_name,
                    territory.name AS territory_name
                FROM player_buildings building
                JOIN building_types type ON type.id = building.building_type_id
                JOIN territories territory ON territory.id = building.territory_id
                WHERE building.id = ?
                  AND building.user_id = ?
                  AND type.code = 'warehouse'
                LIMIT 1
            SQL
        );
        $statement->execute([$warehouseId, $userId]);
        $warehouse = $statement->fetch();

        if (!$warehouse) {
            throw new RuntimeException('Warehouse not found.');
        }

        return $warehouse;
    }

    private function lockWarehouse(int $warehouseId, int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT building.*
                FROM player_buildings building
                JOIN building_types type ON type.id = building.building_type_id
                WHERE building.id = ?
                  AND building.user_id = ?
                  AND type.code = 'warehouse'
                FOR UPDATE
            SQL
        );
        $statement->execute([$warehouseId, $userId]);
        $warehouse = $statement->fetch();

        if (!$warehouse) {
            throw new RuntimeException('Warehouse not found or not owned by this player.');
        }

        if ($warehouse['status'] === 'closed') {
            throw new RuntimeException('This warehouse is closed.');
        }

        return $warehouse;
    }

    private function storageRows(int $warehouseId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    storage.*,
                    COALESCE(item.name, weapon.name, drug.name) AS name,
                    item.category AS item_category
                FROM warehouse_storage storage
                LEFT JOIN item_definitions item
                    ON storage.asset_type = 'item'
                    AND item.id = storage.asset_id
                LEFT JOIN weapons weapon
                    ON storage.asset_type = 'weapon'
                    AND weapon.id = storage.asset_id
                LEFT JOIN drugs drug
                    ON storage.asset_type = 'drug'
                    AND drug.id = storage.asset_id
                WHERE storage.warehouse_id = ?
                  AND storage.quantity > 0
                ORDER BY storage.asset_type, name
            SQL
        );
        $statement->execute([$warehouseId]);

        return $statement->fetchAll();
    }

    private function vehicles(int $warehouseId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM vehicles
                WHERE warehouse_id = ?
                  AND status = 'stored'
                ORDER BY acquired_at DESC
            SQL
        );
        $statement->execute([$warehouseId]);

        return $statement->fetchAll();
    }

    private function usedVehicleSlots(int $warehouseId): int
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT COUNT(*)
                FROM vehicles
                WHERE warehouse_id = ?
                  AND status = 'stored'
            SQL
        );
        $statement->execute([$warehouseId]);

        return (int) $statement->fetchColumn();
    }

    private function upgradeCatalog(): array
    {
        $upgrades = Database::pdo()->query(
            'SELECT * FROM building_upgrades ORDER BY price, name'
        )->fetchAll();

        foreach ($upgrades as &$upgrade) {
            $upgrade['effects'] = json_decode((string) $upgrade['effects'], true) ?: [];
        }

        return $upgrades;
    }

    private function installedUpgrades(int $warehouseId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT upgrade.*, installed.installed_at
                FROM player_building_upgrades installed
                JOIN building_upgrades upgrade
                    ON upgrade.id = installed.building_upgrade_id
                WHERE installed.player_building_id = ?
                ORDER BY installed.installed_at
            SQL
        );
        $statement->execute([$warehouseId]);
        $upgrades = $statement->fetchAll();

        foreach ($upgrades as &$upgrade) {
            $upgrade['effects'] = json_decode((string) $upgrade['effects'], true) ?: [];
        }

        return $upgrades;
    }

    private function storageLogs(int $warehouseId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT *
                FROM storage_logs
                WHERE warehouse_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT 50
            SQL
        );
        $statement->execute([$warehouseId]);

        return $statement->fetchAll();
    }

    private function writeStorageLog(
        int $warehouseId,
        int $userId,
        string $action,
        string $assetType,
        int $assetId,
        int $quantity,
        string $description
    ): void {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                INSERT INTO storage_logs (
                    warehouse_id,
                    user_id,
                    action,
                    asset_type,
                    asset_id,
                    quantity,
                    description,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            SQL
        );

        $statement->execute([
            $warehouseId,
            $userId,
            $action,
            $assetType,
            $assetId,
            $quantity,
            $description,
        ]);
    }
}

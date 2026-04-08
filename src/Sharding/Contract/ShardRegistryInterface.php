<?php

namespace App\Sharding\Contract;

use App\Sharding\Model\Shard;

/**
 * ShardRegistryInterface
 *
 * SOURCE DE VÉRITÉ du mapping :
 * tenant → shard
 *
 * RÔLE :
 * - abstraction du stockage (memory, DB, API, etc.)
 * - découpler routing et persistance
 */
interface ShardRegistryInterface
{
    public function registerShard(Shard $shard): void;

    public function assignTenant(string $tenantId, string $shardId): void;

    public function getShardForTenant(string $tenantId): ?Shard;

    /**
     * @return Shard[]
     */
    public function listShards(): array;
}
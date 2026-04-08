<?php

namespace App\Sharding\Registry;

use App\Sharding\Contract\ShardRegistryInterface;
use App\Sharding\Model\Shard;

/**
 * InMemoryShardRegistry
 *
 * IMPLEMENTATION V1 SIMPLE
 *
 * AVANTAGES :
 * - ultra rapide à tester
 * - zéro dépendance
 * - parfait pour POC
 *
 * LIMITES :
 * - non persistant
 * - reset à chaque reboot
 */
final class InMemoryShardRegistry implements ShardRegistryInterface
{
    private array $shards = [];
    private array $tenantMap = [];

    public function registerShard(Shard $shard): void
    {
        $this->shards[$shard->id] = $shard;
    }

    public function assignTenant(string $tenantId, string $shardId): void
    {
        if (!isset($this->shards[$shardId])) {
            throw new \RuntimeException("Shard inconnu: $shardId");
        }

        $this->tenantMap[$tenantId] = $shardId;
    }

    public function getShardForTenant(string $tenantId): ?Shard
    {
        $shardId = $this->tenantMap[$tenantId] ?? null;

        return $shardId ? ($this->shards[$shardId] ?? null) : null;
    }

    public function listShards(): array
    {
        return array_values($this->shards);
    }
}
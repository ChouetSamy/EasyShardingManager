<?php

namespace App\Sharding\Registry;

use App\Sharding\Contract\ShardRegistryInterface;
use App\Sharding\Model\Shard;
use Doctrine\DBAL\Connection;

/**
 * DoctrineShardRegistry
 *
 * VERSION PERSISTANTE
 *
 * AVANTAGES :
 * - durable
 * - multi-instance ready
 *
 * INCONVÉNIENTS :
 * - plus complexe
 */
final class DoctrineShardRegistry implements ShardRegistryInterface
{
    public function __construct(private Connection $connection) {}

    public function registerShard(Shard $shard): void
    {
        $this->connection->insert('shards', [
            'id' => $shard->id,
            'type' => $shard->type,
            'region' => $shard->region,
        ]);
    }

    public function assignTenant(string $tenantId, string $shardId): void
    {
        $this->connection->insert('tenant_shards', [
            'tenant_id' => $tenantId,
            'shard_id' => $shardId,
        ]);
    }

    public function getShardForTenant(string $tenantId): ?Shard
    {
        $row = $this->connection->fetchAssociative(
            'SELECT s.* FROM shards s
             JOIN tenant_shards t ON t.shard_id = s.id
             WHERE t.tenant_id = ?',
            [$tenantId]
        );

        if (!$row) {
            return null;
        }

        return new Shard($row['id'], $row['type'], $row['region']);
    }

    public function listShards(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM shards');

        return array_map(
            fn($r) => new Shard($r['id'], $r['type'], $r['region']),
            $rows
        );
    }
}
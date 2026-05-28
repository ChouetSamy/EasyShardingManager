<?php

namespace App\Sharding\Strategy;

use App\Sharding\Contract\ShardRegistryInterface;
use App\Sharding\Model\Shard;
use App\Sharding\Strategy\PlacementStrategyInterface;

/**
 * PartitionEngine
 *
 * CERVEAU DU SHARDING.
 *
 * RESPONSABILITÉ UNIQUE :
 * =======================
 *
 * Décider où placer un tenant.
 *
 * IMPORTANT :
 * ===========
 *
 * Ce service NE gère PAS :
 *
 * - les connexions DB
 * - le monitoring
 * - le scaling provider
 * - les migrations
 * - les métriques
 *
 * Il ne fait QU’UNE chose :
 *
 * tenant → shard
 *
 * ARCHITECTURE :
 * ==============
 *
 * Tenant
 *   ↓
 * PartitionEngine
 *   ↓
 * PlacementStrategy
 *   ↓
 * Registry
 *   ↓
 * tenant → shard persisted
 *
 * DÉCISION ARCHITECTURALE :
 * =========================
 *
 * L’application utilise un
 * DATABASE-LEVEL SHARDING.
 *
 * Cela signifie :
 *
 * un tenant entier est assigné
 * à une base/shard spécifique.
 *
 * et NON :
 *
 * row-level sharding
 * table-level sharding
 *
 * POURQUOI ?
 * ==========
 *
 * 1. Migration simple
 *
 * dump shard
 * → provider externe
 *
 * 2. Isolation client
 *
 * gros tenant
 * ≠ impact global
 *
 * 3. Architecture stable
 *
 * peu de dette technique
 *
 * 4. Compatible self-host
 *
 * compréhension facile.
 *
 * IMPORTANT :
 * ===========
 *
 * Ce moteur est volontairement
 * "boring".
 *
 * Le but est :
 *
 * stable forever
 *
 * plutôt que :
 *
 * smart but fragile.
 */
final class PartitionEngine
{
    public function __construct(
        private ShardRegistryInterface $registry,
        private PlacementStrategyInterface $strategy,
    ) {}

    /**
     * Assigne automatiquement un tenant
     * à un shard.
     *
     * COMPORTEMENT :
     * ==============
     *
     * 1. Vérifie si le tenant
     *    possède déjà un shard.
     *
     * 2. Sinon :
     *    - récupère les shards
     *    - demande à la strategy
     *      lequel choisir
     *    - persiste le mapping
     *
     * 3. Retourne le shard final.
     *
     * IMPORTANT :
     * ===========
     *
     * Idempotent.
     *
     * Si un tenant est déjà assigné,
     * il gardera toujours le même shard.
     *
     * Cela évite :
     *
     * - migrations involontaires
     * - incohérences
     * - corruption de routing
     *
     * @throws \RuntimeException
     * Si aucun shard n'existe.
     */
 /**
 * Assigne automatiquement un tenant
 * à un shard.
 *
 * Idempotent :
 * un tenant déjà assigné
 * garde toujours le même shard.
 */
public function assignTenant(
    string $tenantId,
    ?string $region = null
): Shard {

    // 1. Tenant déjà assigné ?
    $existing = $this->registry->getShardForTenant($tenantId);

    if ($existing !== null) {
        return $existing;
    }

    // 2. Liste des shards disponibles
    $shards = $this->registry->listShards();

    if (empty($shards)) {
        throw new \RuntimeException(
            'Aucun shard disponible'
        );
    }

    // 3. Choix automatique du shard
    $chosenShard = $this->strategy->chooseShard(
        $shards,
        $tenantId,
        $region
    );

    // sécurité
    if ($chosenShard === null) {
        throw new \RuntimeException(
            'Impossible de choisir un shard'
        );
    }

    // 4. Persistance mapping
    $this->registry->assignTenant(
        $tenantId,
        $chosenShard->id
    );

    // 5. Retour
    return $chosenShard;
}
}
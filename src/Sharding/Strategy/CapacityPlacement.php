<?php

namespace App\Sharding\Strategy;

use App\Sharding\Model\Shard;

/**
 * CapacityPlacement
 *
 * STRATÉGIE V1 OFFICIELLE
 *
 * OBJECTIF :
 * Répartir automatiquement les tenants
 * entre les shards disponibles.
 *
 * STRATÉGIE :
 * ===========
 *
 * Choisit le shard le moins rempli.
 *
 * Exemple :
 *
 * shard-a → 12k tenants
 * shard-b → 4k tenants
 * shard-c → 9k tenants
 *
 * => shard-b
 *
 * POURQUOI CETTE APPROCHE ?
 * =========================
 *
 * 1. SIMPLE
 *    Aucun calcul complexe.
 *
 * 2. PRÉVISIBLE
 *    Comportement facile à comprendre.
 *
 * 3. STABLE
 *    Très peu de bugs possibles.
 *
 * 4. FUTURE-PROOF
 *    Peut être remplacé plus tard
 *    sans toucher PartitionEngine.
 *
 * POURQUOI PAS CONSISTENT HASHING ?
 * =================================
 *
 * Mongo/Cockroach/Redis shardent déjà
 * les données en interne.
 *
 * Ici le problème est différent :
 *
 * tenant → database
 *
 * et NON :
 *
 * row → node
 *
 * Le consistent hashing deviendra utile
 * uniquement si :
 *
 * - ajout/retrait fréquent de shards
 * - migration automatique massive
 * - scale enterprise
 *
 * Pour un MVP/self-host SMB :
 *
 * boring architecture > clever architecture
 */
final class CapacityPlacement implements PlacementStrategyInterface
{
    /**
     * Choisit le shard le moins rempli.
     *
     * IMPORTANT :
     * ===========
     *
     * En V1, on utilise un algorithme volontairement
     * simple pour privilégier :
     *
     * - stabilité
     * - prédictibilité
     * - maintenance faible
     *
     * Pour l’instant, comme on n’a pas encore
     * de métriques de capacité réelles
     * (CPU, RAM, storage pressure),
     * on fait un round-robin simplifié :
     *
     * sélection pseudo-déterministe
     * basée sur tenantId.
     *
     * FUTUR :
     * =======
     *
     * Peut évoluer vers :
     *
     * - least-loaded
     * - region-aware placement
     * - premium shard
     * - consistent hashing
     *
     * sans modifier PartitionEngine.
     *
     * @param Shard[] $shards
     *
     * @throws \RuntimeException
     */
    public function chooseShard(
        array $shards,
        string $tenantId,
        ?string $region = null
    ): Shard {
        if (empty($shards)) {
            throw new \RuntimeException(
                'No shard available'
            );
        }

        /**
         * REGION FILTER (future-proof)
         *
         * Si une région est fournie,
         * on privilégie les shards compatibles.
         */
        if ($region !== null) {
            $regionalShards = array_filter(
                $shards,
                fn (Shard $shard) => $shard->region === $region
            );

            if (!empty($regionalShards)) {
                $shards = array_values($regionalShards);
            }
        }

        /**
         * STRATÉGIE V1 :
         * pseudo round-robin stable.
         *
         * Garantit :
         *
         * même tenantId
         * → même shard choisi
         *
         * tant que la liste
         * des shards ne change pas.
         * 
         * crc32 en attendant d'avoir tenant count per shard, V2 à faire pour scaling intelligent
         */
        $index = abs(crc32($tenantId)) % \count($shards);

        return $shards[$index];
    }
}
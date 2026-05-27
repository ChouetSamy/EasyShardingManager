<?php

namespace App\Sharding\Strategy;

use App\Sharding\Model\Shard;

interface PlacementStrategyInterface
{
    /**
     * Choisit le shard optimal pour un tenant.
     *
     * @param Shard[] $shards
     *
     * @throws \RuntimeException
     * Si aucun shard n'est disponible.
     */
    
    public function chooseShard(
        array $shards,
        string $tenantId,
        ?string $region = null
    ): Shard;
}

/**
 * PlacementStrategyInterface
 *
 * RESPONSABILITÉ :
 * Déterminer sur quel shard un tenant doit être placé.
 *
 * IMPORTANT :
 * Cette interface NE fait PAS le routing réel
 * et NE persiste PAS le mapping tenant → shard.
 *
 * Elle ne fait qu'une seule chose :
 * choisir le shard optimal.
 *
 * POURQUOI UNE STRATEGY ?
 * ======================
 *
 * Évite d’ajouter des if/else géants dans
 * PartitionEngine au fur et à mesure des besoins.
 *
 * Exemple d’évolutions futures :
 *
 * - placement par région
 * - placement par charge
 * - premium shard dédié
 * - contraintes RGPD/compliance
 * - consistent hashing
 *
 * Sans strategy pattern :
 *
 * if (...)
 * elseif (...)
 * elseif (...)
 *
 * => dette technique énorme.
 *
 * Avec strategy pattern :
 *
 * nouvelle classe
 * zéro refactor.
 *
 * V1 DU PRODUIT :
 * ===============
 *
 * CapacityPlacement
 *
 * → shard le moins rempli
 *
 * volontairement simple
 * pour privilégier :
 *
 * - stabilité
 * - prédictibilité
 * - maintenance faible
 */
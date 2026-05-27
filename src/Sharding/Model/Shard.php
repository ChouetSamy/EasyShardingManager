<?php

namespace App\Sharding\Model;

/**
 * Shard
 *
 * Représente une unité logique de stockage.
 *
 * RESPONSABILITÉ :
 * - Décrire un shard (pas le gérer)
 *
 * IMPORTANT :
 * - Aucun accès DB ici
 * - Pas de logique métier
 */
final class Shard
{
    public function __construct(
        public readonly string $id,

        /**
         * mongo | cockroach | redis
         */
        public readonly string $type,

        /**
         * eu-west | us-east | asia
         */
        public readonly string $region,

        /**
         * Connection target
         */
        public readonly string $dsn,

        /**
         * Health state
         */
        public readonly bool $healthy = true,

        /**
         * Computed load
         * 0.0 → 1.0
         */
        public readonly float $loadScore = 0.0,

        /**
         * Free-form metadata
         */
        public readonly array $tags = []
    ) {
    }
}
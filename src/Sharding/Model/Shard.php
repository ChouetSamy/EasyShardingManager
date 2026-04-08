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
        public readonly string $type,   // mongo | cockroach | redis
        public readonly string $region  // eu | us | asia
    ) {}
}
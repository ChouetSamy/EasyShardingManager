<?php

namespace App\Controller;

use App\Storage\StorageFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Storage\Service\MetricsTranslator;
use App\Storage\Insight\InsightEngine;

/**
 * StorageController
 *
 * Exposes storage orchestration API.
 *
 * PURPOSE:
 * - Entry point for frontend / dashboard
 * - Allows interaction with all storage drivers
 */
#[Route('/api/storage')]
final class StorageController extends AbstractController
{
    public function __construct(
        private readonly StorageFactory $factory,
        private readonly MetricsTranslator $translator,
        private readonly InsightEngine $insightEngine
    ) {
    }

    /**
     * Health check endpoint
     */
    #[Route('/health', methods: ['POST'])]
    public function health(Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true);

        $storage = $this->factory->create($config);
        $storage->connect();

        $health = $storage->getHealth();

        return $this->json([
            'status' => $health->getStatus()->value,
            'latencyMs' => $health->getLatencyMs(),
            'error' => $health->getError(),
            'checkedAt' => $health->getCheckedAt()->format('c'),
        ]);
    }

    /**
     * Metrics endpoint
     */
    #[Route('/metrics', methods: ['POST'])]
    public function metrics(Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true);

        $storage = $this->factory->create($config);
        $storage->connect();

        $metrics = $storage->getMetrics();

        return $this->json([
            'driver' => $metrics->getStorageDriver(),
            'users' => $metrics->getUserCount(),
            'regions' => $metrics->getRegionCount(),
            'shards' => $metrics->getShardCount(),
            'extra' => $metrics->getDriverMetrics()->toArray() // ⚠️ brut pour MVP
        ]);
    }

    /**
     * Balance analysis endpoint
     */
    #[Route('/balance', methods: ['POST'])]
    public function balance(Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true);

        $storage = $this->factory->create($config);
        $storage->connect();

        $balance = $storage->analyzeBalance();

        return $this->json([
            'isBalanced' => $balance->isBalanced(),
            'deviationPercent' => $balance->getDeviationPercent(),
            'message' => $balance->getMessage(),
        ]);
    }

    /**
     * Rebalance action endpoint
     */
    #[Route('/rebalance', methods: ['POST'])]
    public function rebalance(Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true);
        $strategy = $config['strategy'] ?? 'auto';

        $storage = $this->factory->create($config);
        $storage->connect();

        try {
            $storage->rebalance($strategy);

            return $this->json([
                'success' => true,
                'message' => 'Action exécutée avec succès'
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/insights', methods: ['POST'])]
    public function insights(Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true);

        $storage = $this->factory->create($config);
        $storage->connect();

        $metrics = $storage->getMetrics();

        // 🔥 étape clé
        $normalized = $this->translator->translate(
            $metrics->getDriverMetrics()
        );

        $insights = $this->insightEngine->generate($normalized);

        return $this->json([
            'insights' => array_map(fn($i) => [
                'level' => $i->level,
                'message' => $i->message,
                'action' => $i->action
            ], $insights)
        ]);
    }

    #[Route('/full', methods: ['POST'])]
    public function full(Request $request): JsonResponse
    {
        try {
            $config = json_decode($request->getContent(), true);

            $storage = $this->factory->create($config);
            $storage->connect();

            $health = $storage->getHealth();
            $metrics = $storage->getMetrics();
            $balance = $storage->analyzeBalance();

            $normalized = $this->translator->translate(
                $metrics->getDriverMetrics()
            );

            $insights = $this->insightEngine->generate($normalized);

            return $this->json([
                'health' => [
                    'status' => $health->getStatus()->value,
                    'latencyMs' => $health->getLatencyMs(),
                    'error' => $health->getError(),
                ],
                'metrics' => $metrics->toArray(),
                'balance' => $balance->toArray(),
                'insights' => array_map(fn($i) => [
                    'level' => $i->level,
                    'message' => $i->message,
                    'action' => $i->action
                ], $insights)
            ]);

        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
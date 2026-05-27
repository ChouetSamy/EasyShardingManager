# Easy Scaling Manager — DevOps Runbook (V1)

> Objectif : pouvoir relancer le projet rapidement après un crash PC / pause longue sans devoir se souvenir des commandes.

---

# 1. Démarrer le projet

Se placer dans le dossier Docker :

```bash
cd ~/git/EasyShardingManager/docker
```

Lancer tous les containers :

```bash
docker compose up -d
```

Vérifier les containers :

```bash
docker ps
```

Containers attendus :

- app
- mongodb
- redis
- cockroachdb
- grafana

---

# 2. Si un container ne répond pas

Voir les logs :

```bash
docker compose logs
```

Logs d’un service spécifique :

```bash
docker compose logs app
```

ou :

```bash
docker compose logs cockroachdb
```

Redémarrer un container :

```bash
docker compose restart cockroachdb
```

Redémarrer tout :

```bash
docker compose down

docker compose up -d
```

---

# 3. Entrer dans le container PHP

Très utile pour les commandes Symfony.

```bash
docker compose exec app sh
```

Une fois dedans :

```bash
php bin/console
```

Pour sortir :

```bash
exit
```

---

# 4. Vérifier le réseau Docker

## Mongo

```bash
getent hosts mongodb
```

## Redis

```bash
getent hosts redis
```

## CockroachDB

```bash
getent hosts cockroachdb
```

Si un service ne répond pas :

```bash
docker ps
```

Puis relancer le container.

---

# 5. Endpoints API de test

Base URL :

```text
http://127.0.0.1:8001
```

---

# 6. Test MongoDB

```bash
curl -X POST http://127.0.0.1:8001/api/storage/full \
  -H "Content-Type: application/json" \
  -d '{"driver":"mongo","dsn":"mongodb://mongodb:27017"}'
```

Résultat attendu :

- health UP
- latency OK
- metrics mongo

---

# 7. Test Redis

```bash
curl -X POST http://127.0.0.1:8001/api/storage/full \
  -H "Content-Type: application/json" \
  -d '{"driver":"redis","dsn":"tcp://redis:6379"}'
```

Résultat attendu :

- health UP
- connectedClients
- memoryUsed

---

# 8. Test CockroachDB

```bash
curl -X POST http://127.0.0.1:8001/api/storage/full \
  -H "Content-Type: application/json" \
  -d '{"driver":"cockroach","dsn":"postgresql://root@cockroachdb:26257/defaultdb?sslmode=disable"}'
```

Résultat attendu :

- health UP
- userCount
- rangeCount
- shardCount

---

# 9. CockroachDB shell

Entrer dans CockroachDB :

```bash
docker compose exec cockroachdb ./cockroach sql \
  --insecure \
  --host=cockroachdb
```

Compter les users :

```sql
SELECT count(*) FROM users;
```

Distribution régionale :

```sql
SELECT region, count(*)
FROM users
GROUP BY region
ORDER BY region;
```

Lister les tables :

```sql
SHOW TABLES;
```

Quitter :

```text
CTRL + D
```

---

# 10. Seed CockroachDB

Entrer dans le container :

```bash
docker compose exec app sh
```

Puis :

### 100 users

```bash
php bin/console app:seed:cockroach 100
```

### 10k users

```bash
php bin/console app:seed:cockroach 10000
```

### 100k users

```bash
php bin/console app:seed:cockroach 100000
```

Notes observées :

- 10k = rapide
- 100k = quelques secondes
- monitoring OK

---

# 11. Vérification rapide santé projet

## Tout tourne ?

```bash
docker ps
```

## API répond ?

```bash
curl http://127.0.0.1:8001
```

## Mongo OK ?

Faire curl mongo.

## Redis OK ?

Faire curl redis.

## Cockroach OK ?

Faire curl cockroach.

---

# 12. Ports du projet

| Service | Port |
|----------|------|
| App | 8001 |
| MongoDB | 27017 |
| Redis | 6380 |
| Cockroach SQL | 26257 |
| Cockroach UI | 8080 |
| Grafana | 3001 |

---

# 13. URLs utiles

App :

```text
http://127.0.0.1:8001
```

Grafana :

```text
http://127.0.0.1:3001
```

Cockroach Admin UI :

```text
http://127.0.0.1:8080
```

---

# 14. Roadmap immédiate (quand tu reprends)

Ordre recommandé :

1. Finaliser sharding V1
2. Commands console shard/tenant
3. API stable
4. Front débutant-friendly
5. Seed Mongo
6. Seed Redis
7. Migration engine
8. CapacityPlacement V2

---

# 15. TODO important (à ne pas oublier)

## CapacityPlacement V2

À faire UNE FOIS V1 stable.

Objectif :

placement intelligent basé sur :

- CPU
- RAM
- Latency
- Storage pressure
- Saturation shard
- Health score

Remplacera la V1 actuelle basée sur `crc32(tenantId)`.

IMPORTANT :

Ne pas faire avant stabilisation complète de la V1.


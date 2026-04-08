shards (
  id VARCHAR PRIMARY KEY,
  type VARCHAR,
  region VARCHAR
)

tenant_shards (
  tenant_id VARCHAR PRIMARY KEY,
  shard_id VARCHAR
)
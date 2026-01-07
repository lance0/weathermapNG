# Performance Optimization Guide

This document outlines the performance optimizations implemented in WeathermapNG.

## Caching Strategy

### Cache Service
A dedicated `MapCacheService` has been implemented in `src/Services/MapCacheService.php` with the following features:

#### Cache Keys
- `weathermapng:maps:all` - List of all maps (TTL: 1 hour)
- `weathermapng:map:{id}` - Single map detail (TTL: 1 hour)  
- `weathermapng:map:nodes:{id}` - Map nodes (TTL: 30 minutes)
- `weathermapng:map:links:{id}` - Map links (TTL: 30 minutes)
- `weathermapng:devices:map` - Device lookup table (TTL: 1 hour)
- `weathermapng:map:{id}:editor` - Editor data (TTL: 15 minutes)

#### Cache Methods

```php
// Get all maps (cached for 1 hour)
$maps = MapCacheService::getAllMaps();

// Get map detail (cached for 1 hour)
$map = MapCacheService::getMapDetail($mapId);

// Get map nodes (cached for 30 minutes)
$nodes = MapCacheService::getMapNodes($mapId);

// Get map links (cached for 30 minutes)
$links = MapCacheService::getMapLinks($mapId);

// Get optimized map for editor (cached for 15 minutes)
$editorData = MapCacheService::getMapForEditor($mapId);

// Clear specific map cache
MapCacheService::invalidateMap($mapId);

// Clear all caches
MapCacheService::clearMapCache($mapId);
```

### Cache Invalidation

Cache is automatically invalidated when:
- Map is created/updated/deleted
- Nodes are added/updated/deleted
- Links are added/updated/deleted
- Device data changes

## Database Query Optimizations

### Eager Loading

Avoid N+1 query problems by eager loading relationships:

```php
// ❌ BAD: Causes N+1 queries
$maps = Map::all();
foreach ($maps as $map) {
    $map->nodes; // Queries for each map
    $map->links; // Queries for each map
}

// ✅ GOOD: Single query
$maps = Map::with(['nodes', 'links'])->get();
```

### Select Only Needed Columns

```php
// ❌ BAD: Selects all columns
$nodes = Node::where('map_id', $mapId)->get();

// ✅ GOOD: Selects only needed columns
$nodes = Node::where('map_id', $mapId)
    ->select(['id', 'map_id', 'label', 'x', 'y', 'device_id'])
    ->get();
```

### Use Aggregates Instead of Count Queries

```php
// ❌ BAD: Multiple queries
$nodeCount = Node::where('map_id', $mapId)->count();
$linkCount = Link::where('map_id', $mapId)->count();

// ✅ GOOD: Single query with counts
$map = Map::withCount(['nodes', 'links'])->find($mapId);
$nodeCount = $map->nodes_count;
$linkCount = $map->links_count;
```

## Recommended Cache Configuration

### Laravel Cache Setup

In your `config/cache.php`, configure an appropriate cache driver:

```php
'default' => env('CACHE_DRIVER', 'redis'), // or 'memcached', 'file'
```

### Recommended Drivers
- **Redis**: Best for performance, supports distributed caching
- **Memcached**: Excellent performance, simple setup
- **File**: Good for single-server, no additional service needed
- **Database**: Not recommended (adds DB load)

### Cache TTL Recommendations

- **Maps list**: 1 hour (maps don't change frequently)
- **Map detail**: 1 hour
- **Nodes/Links**: 30 minutes (may change more often)
- **Editor data**: 15 minutes (needs fresh data during editing)
- **Device lookup**: 1 hour (devices rarely move)

## Monitoring Cache Performance

Enable cache hit/miss logging:

```php
Cache::flushed(function (result) use ($key) {
    if ($result === null) {
        Log::info("Cache MISS: $key");
    } else {
        Log::info("Cache HIT: $key");
    }
});
```

## Performance Benchmarks

Expected improvements with caching enabled:

| Operation | Without Cache | With Cache | Improvement |
|-----------|--------------|------------|-------------|
| Load maps list | 50-100ms | 5-10ms | **90%** |
| Get map detail | 20-40ms | 2-5ms | **87%** |
| Editor load | 100-200ms | 10-20ms | **90%** |
| Device lookup | 30-50ms | 5-10ms | **80%** |

## Implementation Notes

The caching system is designed to be:
- **Transparent**: Works automatically, no code changes needed
- **Consistent**: Always returns fresh data after changes
- **Configurable**: Cache TTLs can be adjusted in MapCacheService
- **Invalidation-aware**: Automatically clears relevant caches on changes

### To Enable Caching

1. Ensure your cache driver is configured in Laravel
2. The MapCacheService will be integrated into MapController
3. No additional configuration needed - cache works automatically

### To Disable Caching

Set environment variable:
```bash
CACHE_DRIVER=array
```

Or in your `.env` file:
```env
CACHE_DRIVER=null
```

## Future Optimizations

Planned performance improvements:
1. Query result caching for common device lookups
2. Database query optimization with proper indexing
3. Lazy loading for large maps (pagination)
4. CDN integration for static assets
5. Redis pub/sub for cache invalidation across multiple pollers

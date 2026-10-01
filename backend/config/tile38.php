<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tile38 geospatial engine
    |--------------------------------------------------------------------------
    |
    | Tile38 speaks the Redis protocol. When enabled and reachable, pharmacy
    | geofence queries use WITHIN / NEARBY instead of PHP haversine math.
    | Falls back to haversine automatically when disabled or offline.
    |
    */

    'enabled' => env('TILE38_ENABLED', false),

    'host' => env('TILE38_HOST', '127.0.0.1'),

    'port' => (int) env('TILE38_PORT', 9851),

    'geofences_collection' => env('TILE38_GEOFENCES_COLLECTION', 'geofences'),

    'pharmacies_collection' => env('TILE38_PHARMACIES_COLLECTION', 'pharmacies'),

];

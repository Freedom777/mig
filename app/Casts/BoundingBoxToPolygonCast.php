<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\LineString;

class BoundingBoxToPolygonCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return Polygon::fromWkt($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_array($value) && count($value) === 4) {
            [$minLat, $maxLat, $minLon, $maxLon] = $value;

            $polygon = new Polygon([
                new LineString([
                    new Point((float) $minLon, (float) $minLat),
                    new Point((float) $maxLon, (float) $minLat),
                    new Point((float) $maxLon, (float) $maxLat),
                    new Point((float) $minLon, (float) $maxLat),
                    new Point((float) $minLon, (float) $minLat),
                ])
            ]);

            return $polygon->toWkt();
        }

        return null;
    }
}

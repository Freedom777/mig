<?php

namespace App\Services;

use App\Models\Face;
use App\Models\Person;
use Illuminate\Support\Collection;

class PersonService
{
    private const EMBEDDING_SIZE = 128;
    private const MIN_QUALITY = 30;
    private const OUTLIER_PERCENTILE = 0.8; // Оставляем 80% ближайших

    /**
     * Пересчитать centroid для человека
     */
    public function recalculateCentroid(Person $person): void
    {
        $faces = Face::where('person_id', $person->id)
            ->where('status', Face::STATUS_OK)
            ->whereNotNull('encoding')
            ->get();

        if ($faces->isEmpty()) {
            $person->update([
                'centroid_embedding' => null,
                'embeddings_count' => 0,
            ]);
            return;
        }

        // Если мало лиц — простой weighted centroid
        if ($faces->count() < 4) {
            $this->calculateWeightedCentroid($person, $faces);
            return;
        }

        // Убираем outliers и считаем weighted centroid
        $filtered = $this->removeOutliers($faces);
        $this->calculateWeightedCentroid($person, $filtered);
    }

    /**
     * Weighted centroid — вес = quality_score
     */
    private function calculateWeightedCentroid(Person $person, Collection $faces): void
    {
        $centroid = array_fill(0, self::EMBEDDING_SIZE, 0.0);
        $totalWeight = 0;

        foreach ($faces as $face) {
            $encoding = $this->getEncoding($face);
            if (!$encoding) continue;

            $weight = max($face->quality_score ?? 50, self::MIN_QUALITY);

            for ($i = 0; $i < self::EMBEDDING_SIZE; $i++) {
                $centroid[$i] += $encoding[$i] * $weight;
            }
            $totalWeight += $weight;
        }

        if ($totalWeight === 0) {
            $person->update(['centroid_embedding' => null, 'embeddings_count' => 0]);
            return;
        }

        // Нормализация
        for ($i = 0; $i < self::EMBEDDING_SIZE; $i++) {
            $centroid[$i] /= $totalWeight;
        }

        $person->update([
            'centroid_embedding' => $centroid,
            'embeddings_count' => $faces->count(),
        ]);
    }

    /**
     * Убрать outliers — 20% самых далёких от предварительного centroid
     */
    private function removeOutliers(Collection $faces): Collection
    {
        // Простой centroid для определения outliers
        $tempCentroid = $this->calculateSimpleCentroid($faces);
        if (!$tempCentroid) {
            return $faces;
        }

        // Считаем distance каждого от centroid
        $withDistances = $faces->map(function ($face) use ($tempCentroid) {
            $encoding = $this->getEncoding($face);
            return [
                'face' => $face,
                'distance' => $encoding ? $this->euclideanDistance($encoding, $tempCentroid) : PHP_FLOAT_MAX,
            ];
        })->sortBy('distance');

        // Оставляем 80% ближайших
        $keepCount = max((int) ceil($faces->count() * self::OUTLIER_PERCENTILE), 3);

        return $withDistances->take($keepCount)->pluck('face');
    }

    /**
     * Простой centroid (без весов) для определения outliers
     */
    private function calculateSimpleCentroid(Collection $faces): ?array
    {
        $centroid = array_fill(0, self::EMBEDDING_SIZE, 0.0);
        $count = 0;

        foreach ($faces as $face) {
            $encoding = $this->getEncoding($face);
            if (!$encoding) continue;

            for ($i = 0; $i < self::EMBEDDING_SIZE; $i++) {
                $centroid[$i] += $encoding[$i];
            }
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        for ($i = 0; $i < self::EMBEDDING_SIZE; $i++) {
            $centroid[$i] /= $count;
        }

        return $centroid;
    }

    /**
     * Найти подходящего человека для нового лица
     */
    public function findMatchingPerson(array $encoding, float $threshold = 0.6): ?Person
    {
        $persons = Person::whereNotNull('centroid_embedding')
            ->where('embeddings_count', '>', 0)
            ->get();

        $bestMatch = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($persons as $person) {
            $distance = $this->euclideanDistance($encoding, $person->centroid_embedding);

            if ($distance < $threshold && $distance < $bestDistance) {
                $bestMatch = $person;
                $bestDistance = $distance;
            }
        }

        return $bestMatch;
    }

    /**
     * Евклидово расстояние
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0;
        for ($i = 0; $i < self::EMBEDDING_SIZE; $i++) {
            $sum += pow(($a[$i] ?? 0) - ($b[$i] ?? 0), 2);
        }
        return sqrt($sum);
    }

    /**
     * Получить encoding как array
     */
    private function getEncoding(Face $face): ?array
    {
        if (!$face->encoding) {
            return null;
        }

        if (is_array($face->encoding)) {
            return $face->encoding;
        }

        return json_decode($face->encoding, true);
    }
}

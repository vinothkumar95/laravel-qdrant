<?php

namespace Vinothkumar\Qdrant\Services;

use GuzzleHttp\Client as GuzzleClient; // Added alias
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Uid\Uuid;

class QdrantService
{
    protected GuzzleClient $client; // Use aliased GuzzleClient
    protected string $host;
    protected ?string $apiKey;

    public function __construct(GuzzleClient $client = null)
    {
        $this->host = config('qdrant.host');
        $this->apiKey = config('qdrant.api_key');

        if ($client) {
            $this->client = $client;
        } else {
            $headers = [];
            if ($this->apiKey) {
                $headers['api-key'] = $this->apiKey;
            }
            $this->client = new GuzzleClient([
                'base_uri' => $this->host,
                'headers' => $headers,
                'timeout' => config('qdrant.timeout', 10),
            ]);
        }
    }

    /**
     * Create a new collection
     *
     * @param string $collection Collection name
     * @param array $options Collection options
     * @return array
     * @throws GuzzleException
     */
    public function createCollection(string $collection, array $options = []): array
    {
        $payload = array_merge([
            'name' => $collection,
            'vectors' => [
                'size' => $options['size'] ?? 1536,
                'distance' => $options['distance'] ?? 'Cosine',
            ],
        ], $options);

        $response = $this->client->put("/collections/{$collection}", [
            'json' => $payload,
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Get collection info
     *
     * @param string $collection Collection name
     * @return array
     * @throws GuzzleException
     */
    public function getCollection(string $collection): array
    {
        $response = $this->client->get("/collections/{$collection}");

        return json_decode($response->getBody(), true);
    }

    /**
     * List all collections
     *
     * @return array
     * @throws GuzzleException
     */
    public function listCollections(): array
    {
        $response = $this->client->get("/collections");

        return json_decode($response->getBody(), true);
    }

    /**
     * Delete a collection
     *
     * @param string $collection Collection name
     * @return array
     * @throws GuzzleException
     */
    public function deleteCollection(string $collection): array
    {
        $response = $this->client->delete("/collections/{$collection}");

        return json_decode($response->getBody(), true);
    }

    /**
     * Insert a single point into a collection.
     *
     * @param string $collection Collection name.
     * @param string|int|null $id Point ID (string UUID, integer, or null for auto-generation).
     * @param array $vector Vector data.
     * @param array $payload Optional payload data.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function insert(string $collection, string|int|null $id, array $vector, array $payload = []): array
    {
        $pointId = null;
        if ($id === null) {
            $pointId = Uuid::v4()->toRfc4122();
        } elseif (is_int($id)) {
            $pointId = $id;
        } elseif (is_string($id)) {
            if (Uuid::isValid($id)) {
                $pointId = $id;
            } else {
                $pointId = Uuid::v4()->toRfc4122();
            }
        } else {
            // Fallback for other types, though type hint should prevent this
            $pointId = Uuid::v4()->toRfc4122();
        }

        $point = [
            'id' => $pointId,
            'vector' => $vector,
        ];

        if (! empty($payload)) {
            $point['payload'] = $payload;
        }

        $response = $this->client->put("/collections/{$collection}/points", [
            'json' => [
                'points' => [$point],
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Insert multiple points into a collection.
     *
     * @param string $collection Collection name.
     * @param array<int, array{id: string|int|null, vector: array<float|int>, payload?: array<mixed>}> $points Array of points.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function batchInsert(string $collection, array $points): array
    {
        $processedPoints = [];
        foreach ($points as $point) {
            $id = $point['id'] ?? null;
            $pointId = null;

            if ($id === null) {
                $pointId = Uuid::v4()->toRfc4122();
            } elseif (is_int($id)) {
                $pointId = $id;
            } elseif (is_string($id)) {
                if (Uuid::isValid($id)) {
                    $pointId = $id;
                } else {
                    $pointId = Uuid::v4()->toRfc4122();
                }
            } else {
                // Fallback for other types, though type hint should prevent this
                $pointId = Uuid::v4()->toRfc4122();
            }

            $processedPoint = [
                'id' => $pointId,
                'vector' => $point['vector'],
            ];

            if (isset($point['payload'])) {
                $processedPoint['payload'] = $point['payload'];
            }

            $processedPoints[] = $processedPoint;
        }

        $response = $this->client->put("/collections/{$collection}/points", [
            'json' => [
                'points' => $processedPoints,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Update vectors for existing points.
     *
     * @param string $collection Collection name.
     * @param array<int, array{id: string|int, vector: array<float|int>}> $points Array of points with id and vector.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function updateVectors(string $collection, array $points): array
    {
        $response = $this->client->put("/collections/{$collection}/points/vectors", [
            'json' => [
                'points' => $points,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete points from a collection.
     *
     * @param string $collection Collection name.
     * @param array<int, string|int> $ids Array of point IDs to delete.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function deletePoints(string $collection, array $ids): array
    {
        $response = $this->client->post("/collections/{$collection}/points/delete", [
            'json' => [
                'points' => $ids,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Search for similar vectors in a collection.
     *
     * @param string $collection Collection name.
     * @param array<float|int> $vector Query vector.
     * @param int $limit Number of results to return.
     * @param array<mixed> $filter Optional filter for the search.
     * @param array<mixed> $params Additional search parameters.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function search(string $collection, array $vector, int $limit = 5, array $filter = [], array $params = []): array
    {
        $payload = array_merge([
            'vector' => $vector,
            'limit' => $limit,
        ], $params);

        if (! empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client->post("/collections/{$collection}/points/search", [
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Perform a recommendation search based on positive and negative examples.
     *
     * @param string $collection Collection name.
     * @param array<string|int> $positiveIds Positive example point IDs.
     * @param array<string|int> $negativeIds Negative example point IDs (optional).
     * @param int $limit Number of results to return.
     * @param array<mixed> $filter Optional filter for the search.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function recommend(string $collection, array $positiveIds, array $negativeIds = [], int $limit = 5, array $filter = []): array
    {
        $payload = [
            'positive' => $positiveIds,
            'limit' => $limit,
        ];

        if (! empty($negativeIds)) {
            $payload['negative'] = $negativeIds;
        }

        if (! empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client->post("/collections/{$collection}/points/recommend", [
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get points by their IDs.
     *
     * @param string $collection Collection name.
     * @param array<string|int> $ids Array of point IDs to retrieve.
     * @param bool $withPayload Whether to include payload.
     * @param bool $withVector Whether to include vector.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function getPoints(string $collection, array $ids, bool $withPayload = true, bool $withVector = true): array
    {
        $response = $this->client->post("/collections/{$collection}/points", [
            'json' => [
                'ids' => $ids,
                'with_payload' => $withPayload,
                'with_vector' => $withVector,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Scroll through points in a collection.
     *
     * @param string $collection Collection name.
     * @param int $limit Number of points per page.
     * @param string|int|null $offset Offset for pagination (can be point ID or page number).
     * @param array<mixed> $filter Optional filter.
     * @param bool $withPayload Whether to include payload.
     * @param bool $withVector Whether to include vector.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function scroll(string $collection, int $limit = 10, string|int|null $offset = null, array $filter = [], bool $withPayload = true, bool $withVector = false): array
    {
        $payload = [
            'limit' => $limit,
            'with_payload' => $withPayload,
            'with_vector' => $withVector,
        ];

        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        if (! empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client->post("/collections/{$collection}/points/scroll", [
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Count points in a collection.
     *
     * @param string $collection Collection name.
     * @param array<mixed> $filter Optional filter.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function count(string $collection, array $filter = []): array
    {
        $payload = [];

        if (! empty($filter)) {
            $payload['filter'] = $filter;
            // Qdrant expects 'exact_count' for filtered counts to be accurate if that's desired.
            // However, the simple 'count' endpoint with a filter works too.
            // For exact count with filter, the API is slightly different or needs a param.
            // For now, this matches existing behavior.
        }


        $response = $this->client->post("/collections/{$collection}/points/count", [
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Create field index for payload field.
     *
     * @param string $collection Collection name.
     * @param string $fieldName Field name to index.
     * @param string $fieldSchema Field schema type (e.g., "keyword", "integer", "float", "geo", "text").
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function createFieldIndex(string $collection, string $fieldName, string $fieldSchema): array
    {
        $response = $this->client->put("/collections/{$collection}/index", [
            'json' => [
                'field_name' => $fieldName,
                'field_schema' => $fieldSchema,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete field index.
     *
     * @param string $collection Collection name.
     * @param string $fieldName Field name to delete index for.
     * @return array Response from Qdrant.
     * @throws GuzzleException
     */
    public function deleteFieldIndex(string $collection, string $fieldName): array
    {
        $response = $this->client->delete("/collections/{$collection}/index/{$fieldName}");

        return json_decode($response->getBody()->getContents(), true);
    }
}

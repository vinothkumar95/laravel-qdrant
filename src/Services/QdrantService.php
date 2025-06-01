<?php
namespace Vinothkumar\Qdrant\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class QdrantService
{
    protected Client $client;
    protected string $host;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->host = config('qdrant.host');
        $this->apiKey = config('qdrant.api_key');
        
        $headers = [];
        if ($this->apiKey) {
            $headers['api-key'] = $this->apiKey;
        }
        
        $this->client = new Client([
            'base_uri' => $this->host,
            'headers' => $headers
        ]);
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
            ]
        ], $options);

        $response = $this->client->put("/collections/{$collection}", [
            'json' => $payload
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
     * Insert a single point into a collection
     *
     * @param string $collection Collection name
     * @param string|int|null $id Point ID (string UUID, integer, or null for auto-generation)
     * @param array $vector Vector data
     * @param array $payload Optional payload data
     * @return array
     * @throws GuzzleException
     */
    public function insert(string $collection, $id, array $vector, array $payload = []): array
    {
        // Handle ID according to Qdrant requirements (must be uint or UUID)
        if (is_null($id)) {
            // Generate a UUID v4
            $pointId = $this->generateUuid();
        } elseif (is_numeric($id)) {
            // Use as integer if it's numeric
            $pointId = (int)$id;
        } elseif ($this->isValidUuid($id)) {
            // Use as is if it's already a valid UUID
            $pointId = $id;
        } else {
            // Generate a UUID v4 based on the string
            $pointId = $this->generateUuid();
        }
        
        $point = [
            'id' => $pointId,
            'vector' => $vector,
        ];
        
        if (!empty($payload)) {
            $point['payload'] = $payload;
        }
        
        $response = $this->client->put("/collections/{$collection}/points", [
            'json' => [
                'points' => [$point]
            ]
        ]);

        return json_decode($response->getBody(), true);
    }
    
    /**
     * Generate a UUID v4
     *
     * @return string
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Check if a string is a valid UUID
     *
     * @param string $uuid
     * @return bool
     */
    protected function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Insert multiple points into a collection
     *
     * @param string $collection Collection name
     * @param array $points Array of points with id, vector, and optional payload
     * @return array
     * @throws GuzzleException
     */
    public function batchInsert(string $collection, array $points): array
    {
        // Process each point to ensure valid IDs
        $processedPoints = [];
        foreach ($points as $point) {
            $id = $point['id'] ?? null;
            
            // Handle ID according to Qdrant requirements
            if (is_null($id)) {
                $pointId = $this->generateUuid();
            } elseif (is_numeric($id)) {
                $pointId = (int)$id;
            } elseif (is_string($id) && $this->isValidUuid($id)) {
                $pointId = $id;
            } else {
                $pointId = $this->generateUuid();
            }
            
            $processedPoint = [
                'id' => $pointId,
                'vector' => $point['vector']
            ];
            
            if (isset($point['payload'])) {
                $processedPoint['payload'] = $point['payload'];
            }
            
            $processedPoints[] = $processedPoint;
        }
        
        $response = $this->client->put("/collections/{$collection}/points", [
            'json' => [
                'points' => $processedPoints
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Update vectors for existing points
     *
     * @param string $collection Collection name
     * @param array $points Array of points with id and vector
     * @return array
     * @throws GuzzleException
     */
    public function updateVectors(string $collection, array $points): array
    {
        $response = $this->client->put("/collections/{$collection}/points/vectors", [
            'json' => [
                'points' => $points
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Delete points from a collection
     *
     * @param string $collection Collection name
     * @param array $ids Array of point IDs to delete
     * @return array
     * @throws GuzzleException
     */
    public function deletePoints(string $collection, array $ids): array
    {
        $response = $this->client->post("/collections/{$collection}/points/delete", [
            'json' => [
                'points' => $ids
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Search for similar vectors in a collection
     *
     * @param string $collection Collection name
     * @param array $vector Query vector
     * @param int $limit Number of results to return
     * @param array $filter Optional filter for the search
     * @param array $params Additional search parameters
     * @return array
     * @throws GuzzleException
     */
    public function search(string $collection, array $vector, int $limit = 5, array $filter = [], array $params = []): array
    {
        $payload = array_merge([
            'vector' => $vector,
            'limit' => $limit,
        ], $params);

        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client->post("/collections/{$collection}/points/search", [
            'json' => $payload
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Perform a recommendation search based on positive and negative examples
     *
     * @param string $collection Collection name
     * @param array $positiveIds Positive example point IDs
     * @param array $negativeIds Negative example point IDs (optional)
     * @param int $limit Number of results to return
     * @param array $filter Optional filter for the search
     * @return array
     * @throws GuzzleException
     */
    public function recommend(string $collection, array $positiveIds, array $negativeIds = [], int $limit = 5, array $filter = []): array
    {
        $payload = [
            'positive' => $positiveIds,
            'limit' => $limit,
        ];

        if (!empty($negativeIds)) {
            $payload['negative'] = $negativeIds;
        }

        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client->post("/collections/{$collection}/points/recommend", [
            'json' => $payload
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Get points by their IDs
     *
     * @param string $collection Collection name
     * @param array $ids Array of point IDs to retrieve
     * @param bool $withPayload Whether to include payload
     * @param bool $withVector Whether to include vector
     * @return array
     * @throws GuzzleException
     */
    public function getPoints(string $collection, array $ids, bool $withPayload = true, bool $withVector = true): array
    {
        $response = $this->client->post("/collections/{$collection}/points", [
            'json' => [
                'ids' => $ids,
                'with_payload' => $withPayload,
                'with_vector' => $withVector
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Scroll through points in a collection
     *
     * @param string $collection Collection name
     * @param int $limit Number of points per page
     * @param string|null $offset Offset for pagination
     * @param array $filter Optional filter
     * @param bool $withPayload Whether to include payload
     * @param bool $withVector Whether to include vector
     * @return array
     * @throws GuzzleException
     */
    public function scroll(string $collection, int $limit = 10, ?string $offset = null, array $filter = [], bool $withPayload = true, bool $withVector = false): array
    {
        $payload = [
            'limit' => $limit,
            'with_payload' => $withPayload,
            'with_vector' => $withVector
        ];

        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client->post("/collections/{$collection}/points/scroll", [
            'json' => $payload
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Count points in a collection
     *
     * @param string $collection Collection name
     * @param array $filter Optional filter
     * @return array
     * @throws GuzzleException
     */
    public function count(string $collection, array $filter = []): array
    {
        $payload = [];
        
        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client->post("/collections/{$collection}/points/count", [
            'json' => $payload
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Create field index for payload field
     *
     * @param string $collection Collection name
     * @param string $fieldName Field name to index
     * @param string $fieldSchema Field schema type
     * @return array
     * @throws GuzzleException
     */
    public function createFieldIndex(string $collection, string $fieldName, string $fieldSchema): array
    {
        $response = $this->client->put("/collections/{$collection}/index", [
            'json' => [
                'field_name' => $fieldName,
                'field_schema' => $fieldSchema
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Delete field index
     *
     * @param string $collection Collection name
     * @param string $fieldName Field name to delete index for
     * @return array
     * @throws GuzzleException
     */
    public function deleteFieldIndex(string $collection, string $fieldName): array
    {
        $response = $this->client->delete("/collections/{$collection}/index/{$fieldName}");
        return json_decode($response->getBody(), true);
    }
}
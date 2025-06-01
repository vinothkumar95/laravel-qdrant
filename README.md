# Laravel Qdrant

A Laravel-friendly wrapper for the Qdrant vector database. This package allows you to interact with Qdrant v2 API easily using a Laravel-style API.

## Installation

```bash
composer require vinothkumar/laravel-qdrant
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=config
```

Set your Qdrant configuration in your `.env` file:

```
QDRANT_HOST=http://localhost:6333
QDRANT_API_KEY=your_api_key_if_needed
```

## Usage

### Collection Management

```php
// Create a collection
Qdrant::createCollection('products', [
    'size' => 1536,
    'distance' => 'Cosine'
]);

// Get collection info
$info = Qdrant::getCollection('products');

// List all collections
$collections = Qdrant::listCollections();

// Delete a collection
Qdrant::deleteCollection('products');
```

### Points Management

```php
// Insert a single point
Qdrant::insert('products', 'product-123', [0.23, 0.44, 0.98], [
    'name' => 'Product Name',
    'category' => 'Electronics'
]);

// Batch insert multiple points
$points = [
    [
        'id' => 'product-124',
        'vector' => [0.24, 0.45, 0.97],
        'payload' => ['name' => 'Another Product', 'category' => 'Home']
    ],
    [
        'id' => 'product-125',
        'vector' => [0.25, 0.46, 0.96],
        'payload' => ['name' => 'Third Product', 'category' => 'Office']
    ]
];
Qdrant::batchInsert('products', $points);

// Update vectors
Qdrant::updateVectors('products', [
    [
        'id' => 'product-123',
        'vector' => [0.26, 0.47, 0.95]
    ]
]);

// Delete points
Qdrant::deletePoints('products', ['product-123', 'product-124']);

// Get points by IDs
$points = Qdrant::getPoints('products', ['product-125']);
```

### Search and Recommendations

```php
// Basic vector search
$results = Qdrant::search('products', [0.22, 0.43, 0.95], 10);

// Search with filters
$results = Qdrant::search('products', [0.22, 0.43, 0.95], 10, [
    'must' => [
        ['key' => 'category', 'match' => ['value' => 'Electronics']]
    ]
]);

// Recommendations based on existing points
$recommendations = Qdrant::recommend('products', ['product-125'], [], 5);
```

### Scrolling and Counting

```php
// Scroll through points
$page = Qdrant::scroll('products', 20);
$nextPage = Qdrant::scroll('products', 20, $page['result']['next_page_offset']);

// Count points
$count = Qdrant::count('products');

// Count with filter
$count = Qdrant::count('products', [
    'must' => [
        ['key' => 'category', 'match' => ['value' => 'Electronics']]
    ]
]);
```

### Payload Indexing

```php
// Create an index on a payload field
Qdrant::createFieldIndex('products', 'category', 'keyword');

// Delete a field index
Qdrant::deleteFieldIndex('products', 'category');
```

## License

MIT
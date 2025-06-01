# Laravel Qdrant

A Laravel-friendly wrapper for the Qdrant vector database. This package allows you to interact with Qdrant API easily using a Laravel-style API.

## Requirements

- PHP `^8.2`
- Guzzle `^7.8.0` (version `7.9.3` installed)
- Symfony UID `^7.3`

## Installation

```bash
composer require vinothkumar/laravel-qdrant
```

For modern Laravel versions (5.5+), the service provider and facade are auto-discovered. You typically do not need to manually add them to your `config/app.php`.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --provider="Vinothkumar\Qdrant\QdrantServiceProvider" --tag="config"
```

This will publish a `qdrant.php` file to your `config` directory. Set your Qdrant configuration in your `.env` file:

```env
QDRANT_HOST=http://localhost:6333
QDRANT_API_KEY=your_api_key_here
QDRANT_TIMEOUT=30
```

Available configuration options:
- `host`: The URL of your Qdrant instance (e.g., `http://localhost:6333`).
- `api_key`: Your Qdrant API key, if required (optional, defaults to `null`).
- `timeout`: Request timeout in seconds for Guzzle client (optional, defaults to `10`).

## Usage

You can use the `Qdrant` facade or inject the `Vinothkumar\Qdrant\Services\QdrantService` class where needed.

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
        ['key' => 'category', 'match' => ['value' => 'Electronics']],
    ],
]);
```

### Payload Indexing

```php
// Create an index on a payload field
Qdrant::createFieldIndex('products', 'category', 'keyword');

// Delete a field index
Qdrant::deleteFieldIndex('products', 'category');
```

## Development & Contributing

This project uses several tools to ensure code quality and consistency.

### Available Composer Scripts

-   `composer test`: Runs the PHPUnit test suite.
-   `composer lint`: Checks for PHP coding standards issues using PHP-CS-Fixer (dry run with diff).
-   `composer format`: Automatically fixes PHP coding standards issues using PHP-CS-Fixer.
-   `composer analyse`: Performs static analysis using PHPStan to find potential bugs.

### Tools

-   **PHPUnit**: For unit and integration testing.
-   **PHP-CS-Fixer**: For enforcing coding standards. Configuration is in `.php-cs-fixer.dist.php`.
-   **PHPStan**: For static code analysis. Configuration is in `phpstan.neon.dist`.

Contributions are welcome! Please ensure your code adheres to the coding standards and that all tests pass.

## License

MIT
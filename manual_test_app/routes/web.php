<?php

use Illuminate\Support\Facades\Route;
use Vinothkumar\Qdrant\Facades\Qdrant;
use Symfony\Component\Uid\Uuid;
use Illuminate\Http\Response as LaravelResponse; // Alias to avoid conflict if any

Route::get('/', function () {
    return view('welcome');
});

Route::get('/qdrant-test', function () {
    $debugOutput = [];
    $overallSuccess = true;

    $executeAndLog = function (string $description, callable $callable) use (&$debugOutput, &$overallSuccess) {
        $debugOutput[] = $description;
        try {
            $result = $callable();
            $debugOutput[] = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            $debugOutput[] = "Error: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine();
            // Optionally add full trace: $debugOutput[] = "Trace: " . $e->getTraceAsString();
            $overallSuccess = false;
        }
        $debugOutput[] = str_repeat('-', 50); // Separator
    };

    $executeAndLog("Attempting to list collections...", function() {
        return Qdrant::listCollections();
    });

    $collectionName = 'test_laravel_pkg_collection_' . time();
    $creationOptions = ['size' => 10, 'distance' => 'Cosine'];

    $executeAndLog("Attempting to create collection: " . $collectionName, function() use ($collectionName, $creationOptions) {
        return Qdrant::createCollection($collectionName, $creationOptions);
    });

    if ($overallSuccess) { // Only proceed if collection creation likely succeeded
        $executeAndLog("Attempting to get info for collection: " . $collectionName, function() use ($collectionName) {
            return Qdrant::getCollection($collectionName);
        });

        $pointId = Uuid::v4()->toRfc4122();
        $vector = array_fill(0, 10, mt_rand() / mt_getrandmax());
        $payload = ['source' => 'laravel_test_route'];

        $executeAndLog("Attempting to insert a point into: " . $collectionName, function() use ($collectionName, $pointId, $vector, $payload) {
            return Qdrant::insert($collectionName, $pointId, $vector, $payload);
        });

        // Simple verification after insert (optional, could add getPoints here if needed)
        // $executeAndLog("Attempting to retrieve point: " . $pointId, function() use ($collectionName, $pointId) {
        //     return Qdrant::getPoints($collectionName, [$pointId]);
        // });

        $executeAndLog("Attempting to delete collection: " . $collectionName, function() use ($collectionName) {
            return Qdrant::deleteCollection($collectionName);
        });
    } else {
        $debugOutput[] = "Skipping further operations due to previous errors.";
    }

    if ($overallSuccess) {
        $debugOutput[] = "Qdrant tests executed with no exceptions caught directly by the test route. Review output. Qdrant instance assumed to be running at " . config('qdrant.host');
    } else {
        $debugOutput[] = "Qdrant tests executed with one or more exceptions caught. Review output. Qdrant instance assumed to be running at " . config('qdrant.host');
    }

    return new LaravelResponse("<pre>" . implode("\n\n", $debugOutput) . "</pre>", 200, ['Content-Type' => 'text/html']);
});

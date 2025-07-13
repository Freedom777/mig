# Images

sail artisan images:process dfotos .
app/Console/Commands/ImagesProcess.php
handle()
processDirectory(string $diskLabel, string $source); // Recursive
processImage(string $diskLabel, string $sourcePath, string $filename)
$this->apiClient->imageProcess($requestData);
Route::post('/image/process', [ImageProcessController::class, 'process']);

app/Http/Controllers/Api/ImageProcessController.php
process(Request $request)
ImageProcessQueue::dispatch($data)->onQueue(env('QUEUE_IMAGES'));

sail artisan queue:work --queue=images
app/Jobs/ImageProcessJob.php
Image::updateOrCreate();

# Thumbnails

sail artisan images:thumbnails --width=300 --height=200
app/Console/Commands/ImagesThumbnails.php
handle()
createThumbnail(string $diskLabel, string $source, string $filename, int $width, int $height)
Http::post(config('app.api_url') . '/api/thumbnail/generate', $requestData);
$this->apiClient->thumbnailProcess($requestData);
Route::post('/thumbnail/process', [ThumbnailProcessController::class, 'process']);

app/Http/Controllers/Api/ThumbnailProcessController.php
process(Request $request)
ThumbnailProcessJob::dispatch($data)->onQueue(env('QUEUE_THUMBNAILS'));

sail artisan queue:work --queue=thumbnails
app/Jobs/ThumbnailProcessJob.php
Image::update();

# Metadatas

sail artisan images:metadatas
app/Console/Commands/ImagesMetadatas.php
handle()
extractMetadata(int $id, string $diskLabel, string $sourcePath, string $filename)
$this->apiClient->metadataProcess($requestData);
Route::post('/metadata/process', [MetadataProcessController::class, 'process']);

app/Http/Controllers/Api/MetadataProcessController.php
process(Request $request)
MetadataProcessJob::dispatch($data)->onQueue(env('QUEUE_METADATAS'));

sail artisan queue:work --queue=metadatas
app/Jobs/MetadataProcessJob.php
Image::update();

# Geolocations

sail artisan images:geolocations
app/Console/Commands/ImagesGeolocations.php
handle()
extractGeolocation(int $id, string $metadata)
$this->apiClient->geolocationProcess($requestData);
Route::post('/geolocation/process', [GeolocationProcessController::class, 'process']);

app/Http/Controllers/Api/GeolocationProcessController.php
process(Request $request)
GeolocationProcessJob::dispatch($data)->onQueue(env('QUEUE_GEOLOCATIONS'));
sail artisan queue:work --queue=geolocations

# Faces

sail artisan images:faces
app/Console/Commands/ImagesFaces.php
handle()
extractFaces(int $id)
$this->apiClient->faceProcess($requestData);
Route::post('/face/process', [FaceProcessController::class, 'process']);

app/Http/Controllers/Api/FaceProcessController.php
process(Request $request)
FaceProcessJob::dispatch($data)->onQueue(env('QUEUE_FACES'));
sail artisan queue:work --queue=faces

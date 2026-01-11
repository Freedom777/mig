<?php

namespace App\Http\Controllers\Admin;

use Symfony\Component\Process\Process;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class CommandController
{
    private array $allowed = [
        // put: Process photos in dfotos folder
        'images:process dfotos .',
        // put: Images check
        'images:check',
        // pop: Process photos in dfotos folder
        'queue:work --queue=images',

        // put: Create thumbnails
        'images:thumbnails --width=300 --height=200 --method=cover',
        // pop: Create thumbnails
        'queue:work --queue=thumbnails',

        // put: Extract metadata
        'images:metadatas',
        // pop: Extract metadata
        'queue:work --queue=metadatas',

        // put: Extract geolocation
        'images:geolocations',
        // pop: Extract geolocation
        'queue:work --queue=geolocations',

        // put: Extract faces
        'images:faces',
        // put: Check faces
        'images:faces:check',
        // pop: Extract faces
        'queue:work --queue=faces',
    ];

    public function index()
    {
        // отдадим в Vue список команд с разделением на put/pop
        $groups = [
            'Images' => [
                ['label' => 'Process photos in dfotos', 'type' => 'put', 'command' => 'images:process dfotos .'],
                ['label' => 'Check images', 'type' => 'put', 'command' => 'images:check'],
                ['label' => 'Run worker for images', 'type' => 'pop', 'command' => 'queue:work --queue=images'],
            ],
            'Thumbnails' => [
                ['label' => 'Create thumbnails', 'type' => 'put', 'command' => 'images:thumbnails --width=300 --height=200 --method=cover'],
                ['label' => 'Run worker for thumbnails', 'type' => 'pop', 'command' => 'queue:work --queue=thumbnails'],
            ],
            'Metadata' => [
                ['label' => 'Extract metadata', 'type' => 'put', 'command' => 'images:metadatas'],
                ['label' => 'Run worker for metadata', 'type' => 'pop', 'command' => 'queue:work --queue=metadatas'],
            ],
            'Geolocation' => [
                ['label' => 'Extract geolocation', 'type' => 'put', 'command' => 'images:geolocations'],
                ['label' => 'Run worker for geolocation', 'type' => 'pop', 'command' => 'queue:work --queue=geolocations'],
            ],
            'Faces' => [
                ['label' => 'Extract faces', 'type' => 'put', 'command' => 'images:faces'],
                ['label' => 'Check faces', 'type' => 'put', 'command' => 'images:faces:check'],
                ['label' => 'Run worker for faces', 'type' => 'pop', 'command' => 'queue:work --queue=faces'],
            ],
        ];

        return Inertia::render('Admin/Commands', [
            'groups' => $groups,
        ]);
    }

    public function stream(Request $request)
    {
        $command = $request->query('command');
        if (!$command) {
            return response("No command provided", 400);
        }

        if (!in_array($command, $this->allowed, true)) {
            return response()->json(['error' => 'Command not allowed'], 400);
        }

        return response()->stream(function () use ($command) {
            $process = Process::fromShellCommandline("php artisan {$command}", base_path());
            $process->setTimeout(null);

            $process->run(function ($type, $buffer) {
                echo "data: " . trim($buffer) . "\n\n";
                ob_flush();
                flush();
            });

            echo "event: end\n";
            echo "data: done\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

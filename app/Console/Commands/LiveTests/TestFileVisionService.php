<?php

namespace App\Console\Commands\LiveTests;

use App\Facades\FileVision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestFileVisionService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-test:test-filevision-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sampleImagePath = 'live-tests/sample-image-for-filevision-service.jpg';
        if (!Storage::exists($sampleImagePath)) {
            Storage::put($sampleImagePath, file_get_contents(resource_path('sample-images/sample-image-for-filevision-service.jpg')));
        }

        $driver = $this->choice(
            'Select driver',
            $drivers = array_keys(config('filevision.drivers')),
            array_search(config('filevision.default'), $drivers)
        );

        $start = microtime(true);
        $this->info('Calling FileVision::describe() / Driver: '.$driver);
        $fileInformation = FileVision::driver($driver)->describe($sampleImagePath);
        $this->info('Processing time: '.(microtime(true) - $start).' seconds');
        $this->line('-----');
        $this->info('Result: '.$fileInformation->getDescription());
    }
}

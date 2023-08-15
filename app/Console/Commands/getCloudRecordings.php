<?php

namespace App\Console\Commands;

use App\Services\ZoomService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class getCloudRecordings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:recordings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $service = new ZoomService();

        if (Cache::has('meetings')) {
            $meetings = Cache::get('meetings');
        } else {
            $meetings = $service->getCloudRecordings();
        }

        $users = collect(Cache::get('users'));
        $users = $users->map(fn($user) => collect($user));


        $meetings = $meetings->flatten(1)
            ->map(function ($meeting) use ($users) {

                $user = $users->firstWhere('id', '=', $meeting['host_id']);

                $meeting = collect([
                    'id' => $meeting['id'],
                    'topic' => $meeting['topic'],
                    'start_time' => $meeting['start_time'],
                    'user' => $user['display_name'],
                    'recording_count' => $meeting['recording_count'],
                    'recording_files' => collect($meeting['recording_files']),

                ]);

                return $meeting;

            });


        foreach ($meetings as $meeting) {
            $folderName = implode(' ', [
                Str::substr($meeting['start_time'], 0, 10),
                $meeting['topic'],
            ]);

            foreach ($meeting['recording_files'] as $file) {
                $fileName = implode('.', [
                    $file['recording_type'],
                    $folderName,
                    $file['file_extension']
                ]);

                $download = Http::withToken($service->getAccessToken())
                    ->get($file['download_url'])
                    ->body();

                Storage::disk('dropbox')->put("{$folderName}/{$fileName}", $download);
            }

        }

        return Command::SUCCESS;
    }
}

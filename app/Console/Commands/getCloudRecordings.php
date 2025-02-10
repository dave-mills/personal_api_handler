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
    protected $signature = 'get:recordings {year?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets all meeting recordings from Zoom, uploads them to Dropbox, then deletes them from Zoom.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $year = $this->argument('year');


        $service = new ZoomService();

//        if (Cache::has('meetings')) {
//            $meetings = Cache::get('meetings');
//        } else {
            $meetings = $service->getCloudRecordings($year);
//        }

        $users = collect(Cache::get('users'));
        $users = $users->map(fn($user) => collect($user));


        $meetings = $meetings->flatten(1)
            ->map(function ($meeting) use ($users) {

                $user = $users->firstWhere('id', '=', $meeting['host_id']);

                return collect([
                    'id' => $meeting['id'],
                    'topic' => $meeting['topic'],
                    'start_time' => $meeting['start_time'],
                    'user' => $user['display_name'],
                    'recording_count' => $meeting['recording_count'],
                    'recording_files' => collect($meeting['recording_files']),
                ]);

            });

        $downloadCount = 0;

        foreach ($meetings as $meeting) {

            $meetingDownloadCount = 0;

            $this->info('Meeting for user: ' . $meeting['user']);

            $folderName = implode(' ', [
                Str::substr($meeting['start_time'], 0, 10),
                $meeting['topic'],
            ]);

            foreach ($meeting['recording_files'] as $file) {
                $fileName = implode('.', [
                    $file['recording_type'],
                    $folderName,
                    $file['file_extension'],
                ]);

                // check if the file already exists
                if (Storage::disk('dropbox_local')->exists("{$folderName}/{$fileName}")) {
                    $this->info("File {$folderName}/{$fileName} already exists.");
                    continue;
                }

                $this->info('Downloading ' . $fileName . '...');

                $download = Http::withToken($service->getAccessToken())
                    ->timeout(360)
                    ->get($file['download_url'])
                    ->body();

                $this->info("Putting file into {$folderName} on Dropbox...");

                Storage::disk('dropbox_local')->put("{$folderName}/{$fileName}", $download);
                $downloadCount++;

                $this->info('Downloaded ' . $fileName . '.');
                $this->info('~~~~~~~~~~~~');
            }

            // check all files exist
            $delete = true;
            foreach ($meeting['recording_files'] as $file) {
                $fileName = implode('.', [
                    $file['recording_type'],
                    $folderName,
                    $file['file_extension'],
                ]);

                if (!Storage::disk('dropbox_local')->exists("{$folderName}/{$fileName}")) {
                    $this->info("File {$folderName}/{$fileName} does not exist - but it should!! We won't delete it from Zoom just yet...");

                    $delete = false;
                }
            }

            if ($delete) {
                $this->info("Deleting files from {$folderName}");

                $ok = $service->deleteMeetingRecordings($meeting['id']);

                dump($ok);

                if ($ok) {
                    $this->info('Delete Success!');
                } else {
                    $this->error('Could not delete!');
                }

            }


        }

        $this->info("Downloaded {$downloadCount} files.");

        return self::SUCCESS;

    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OrganiseGithubReposFromAPi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:org';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'temp';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // import data from json file in storage/app/stats4sd-repos-20240718.json to collection
        $repos = collect(json_decode(file_get_contents(storage_path('app/stats4sd-repos-20240718.json')), true))
            ->map(fn($repo) => collect($repo)->only([
                'id',
                'node_id',
                'name',
                'html_url',
                'description',
                'fork',
                'url',
                'created_at',
                'updated_at',
                'pushed_at',
                'ssh_url',
                'archived',
                'disabled',
                'licence',
                'is_template',
                'visibility',
                'default_branch',
                'language'
                ])
            );

        // save data to csv file in storage/app/stats4sd-repos-20240718.csv
        $csv = fopen(storage_path('app/stats4sd-repos-20240718.csv'), 'w');
        fputcsv($csv, $repos->first()->keys()->toArray());
        $repos->each(fn($repo) => fputcsv($csv, $repo->values()->toArray()));
        fclose($csv);



    }
}

<?php


namespace App\Console\Commands;

use App\Models\GithubEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportGithubEventsFromJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'github:import {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports Github events from a JSON dump. JSON dump should be created by getting data from the Github API `/users/{username}/events`';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $filePath = $this->argument('file');

        $json = collect(json_decode(file_get_contents($filePath), true))->recursive();

//        dd($json->filter(fn($item) => collect(['PushEvent', 'PullRequestEvent', 'PullRequestReviewEvent'])->contains($item['type']))->count());

        $commits = $json->filter(fn($item) => $item['type'] === 'PushEvent');


        $commits = $commits->map(function ($commit) {

            $nc['id'] = $commit['id'];
            $nc['raw'] = $commit;
            $nc['type'] = 'commit';
            $nc['repo'] = $commit['repo']['name'];
            $nc['message'] = $commit['payload']['commits'][0]['message'] ?? '';
            $nc['url'] = $commit['payload']['commits'][0]['url'] ?? '';
            $nc['date'] = (new Carbon($commit['created_at']))->toDateString();

            GithubEvent::updateOrCreate([
                'id' => $nc['id'],
            ],
                $nc);

            return $nc;
        });

        $prs = $json->filter(fn($item) => $item['type'] === 'PullRequestEvent')
            ->map(function ($pr) {

                $nc['id'] = $pr['id'];
                $nc['raw'] = $pr;
                $nc['repo'] = $pr['repo']['name'];
                $nc['type'] = 'pull request';
                $nc['message'] = $pr['payload']['pull_request']['title'];
                $nc['body'] = $pr['payload']['pull_request']['body'] ?? '';
                $nc['date'] = (new Carbon($pr['payload']['pull_request']['created_at']))->toDateString();

                GithubEvent::updateOrCreate([
                    'id' => $nc['id'],
                ], $nc);
            });

        $prrs = $json->filter(fn($item) => $item['type'] === 'PullRequestReviewEvent')
            ->map(function ($prr) {

                $nc['id'] = $prr['id'];
                $nc['raw'] = $prr;
                $nc['type'] = 'pull request review';
                $nc['repo'] = $prr['repo']['name'];

                $nc['body'] = $prr['payload']['review']['body'] ?? '';
                $nc['url'] = $prr['payload']['review']['html_url'];
                $nc['date'] =(new Carbon( $prr['payload']['review']['submitted_at']))->toDateString();

                GithubEvent::updateOrCreate([
                    'id' => $nc['id'],
                ], $nc);
            });

        $countCommits = $json->filter(fn($item) => $item['type'] === "PushEvent")->count();
        $countPrs = $json->filter(fn($item) => $item['type'] === "PullRequestEvent")->count();
        $countPrrs = $json->filter(fn($item) => $item['type'] === "PullRequestReviewEvent")->count();

        $this->info('commits count:' . $countCommits);
        $this->info('PRs count:' . $countPrs);
        $this->info('PRrs count:' . $countPrrs);

        $this->info('all events count = '. GithubEvent::count());

        return Command::SUCCESS;
    }
}

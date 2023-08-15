<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ZoomService
{

    public function __construct(protected string $endpoint = "https://api.zoom.us/v2")
    {
    }


    // get users
    public function getUsers()
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->get("{$this->endpoint}/users")
            ->throw()
            ->json();

        Cache::set('users', $response['users']);

        return $response['users'];

    }

    public function getMeetings()
    {
        return Cache::get('meetings');
    }


    // get list of cloud recordings
    public function getCloudRecordings()
    {
        $accessToken = $this->getAccessToken();
        $users = $this->getUsers();
        $meetings = collect([]);

        $dates = [
            Carbon::now()->format('Y-m-d'),
            Carbon::now()->subMonths(1)->format('Y-m-d'),
            Carbon::now()->subMonths(2)->format('Y-m-d'),
            Carbon::now()->subMonths(3)->format('Y-m-d'),
            Carbon::now()->subMonths(4)->format('Y-m-d'),
            Carbon::now()->subMonths(5)->format('Y-m-d'),
            Carbon::now()->subMonths(6)->format('Y-m-d'),
            Carbon::now()->subMonths(7)->format('Y-m-d'),
            Carbon::now()->subMonths(8)->format('Y-m-d'),
            Carbon::now()->subMonths(9)->format('Y-m-d'),
        ];

        dump($dates);

        foreach ($users as $user) {

            dump($user['display_name']);

            for ($i = 0; $i < 9; $i++) {

                $response = Http::withToken($accessToken)
                    ->get("{$this->endpoint}/users/{$user['id']}/recordings", [
                        'page_size' => 300,
                        'from' => $dates[$i + 1],
                        'to' => $dates[$i]
                    ])
                    ->throw()
                    ->json();

                $meetings[] = collect($response['meetings']);
            }
        }


        Cache::set('meetings', $meetings);

        return $meetings;

    }


    // set $renew to true to force a refresh.
    public function getAccessToken($renew = false): string
    {

        $cachedToken = Cache::get('zoomToken');

        // if a cached token exists and has not yet expired, return it.
        if ($cachedToken && $cachedToken['expires_at'] > Carbon::now()->timestamp) {
            return $cachedToken['access_token'];
        }

        // otherwise, get a new token. There is no refresh token flow for zoom server-to-server OAuth.
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode(config('services.zoom.client_id') . ':' . config('services.zoom.client_secret'))
        ])
            ->asForm()
            ->post("https://zoom.us/oauth/token", [
                'grant_type' => 'account_credentials',
                'account_id' => config('services.zoom.account_id'),
            ])
            ->throw()
            ->json();

        $response['expires_at'] = Carbon::now()->subSeconds($response['expires_in'])->timestamp;

        Cache::set('zoomToken', $response);

        return $response['access_token'];

    }

}

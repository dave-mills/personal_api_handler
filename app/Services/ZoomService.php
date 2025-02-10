<?php

namespace App\Services;

use Carbon\AbstractTranslator;
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

    // get list of cloud recordings
    public function getCloudRecordings(int $year = null)
    {
        $accessToken = $this->getAccessToken();

        $users = $this->getUsers();
        $meetings = collect([]);

        // get everything for the given year
        if ($year) {
            $dates = [
                Carbon::create($year + 1, 1, 1)->format('Y-m-d'),
                Carbon::create($year, 12, 1)->format('Y-m-d'),
                Carbon::create($year, 11, 1)->format('Y-m-d'),
                Carbon::create($year, 10, 1)->format('Y-m-d'),
                Carbon::create($year, 9, 1)->format('Y-m-d'),
                Carbon::create($year, 8, 1)->format('Y-m-d'),
                Carbon::create($year, 7, 1)->format('Y-m-d'),
                Carbon::create($year, 6, 1)->format('Y-m-d'),
                Carbon::create($year, 5, 1)->format('Y-m-d'),
                Carbon::create($year, 4, 1)->format('Y-m-d'),
                Carbon::create($year, 3, 1)->format('Y-m-d'),
                Carbon::create($year, 2, 1)->format('Y-m-d'),
                Carbon::create($year, 1, 1)->format('Y-m-d'),
            ];
        } else {

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
                Carbon::now()->subMonths(10)->format('Y-m-d'),
                Carbon::now()->subMonths(11)->format('Y-m-d'),
                Carbon::now()->subMonths(12)->format('Y-m-d'),
            ];
        }

        dump($dates);

        foreach ($users as $user) {

            dump($user['display_name']);

            for ($i = 0; $i < 12; $i++) {

                $response = Http::withToken($accessToken)
                    ->get("{$this->endpoint}/users/{$user['id']}/recordings", [
                        'page_size' => 300,
                        'from' => $dates[$i + 1],
                        'to' => $dates[$i],
                    ])
                    ->throw()
                    ->json();

                dump('Fetched ' . count($response['meetings']) . ' recordings for ' . $user['display_name'] . ' in ' . $dates[$i + 1] . ' to ' . $dates[$i]);

                $userMeetings = collect($response['meetings']);
                $meetings[] = $userMeetings;
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
            'Authorization' => 'Basic ' . base64_encode(config('services.zoom.client_id') . ':' . config('services.zoom.client_secret')),
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

    public function deleteMeetingRecordings(int $meetingId): bool
    {
        $test = Http::withToken($this->getAccessToken())
            ->get("{$this->endpoint}/meetings/{$meetingId}/recordings");

        $request = Http::withToken($this->getAccessToken())
            ->delete("{$this->endpoint}/meetings/{$meetingId}/recordings");

        dump($request->status());

        return $request->status();


    }

}

<?php

namespace App\Services;

class TodoistService
{

    public function __construct(protected string $endpoint = "https://api.todoist.com/rest/v2/")
    {
    }

    public function getProjects()
    {

    }
}

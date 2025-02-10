<?php

namespace App\Services;

class DropboxService
{

    public function __construct(protected string $endpoint = "https://api.dropboxapi.com/")
    {
    }

}

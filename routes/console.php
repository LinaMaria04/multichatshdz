<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Schedule::command('app:fetch-chat-data')->hourly();
Schedule::command('tts:clean-old --days=1')->daily();

<?php

namespace App\Console\Commands;

use App\Http\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GrantReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grant:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate pdf grant report';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $url = Helper::generatePdfGrantReport();
        $full_url = asset($url);
        echo "pdf grant report:: $full_url";
        Log::info('pdf grant report:: ');
        Log::info($full_url);
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;

class RunDailyQuery extends Command

{

    /**

     * The name and signature of the console command.

     *

     * @var string

     */

    protected $signature = 'run:dailyquery';

    /**

     * The console command description.

     *

     * @var string

     */

    protected $description = 'Run a daily query at midnight';

    /**

     * Execute the console command.

     *

     * @return int

     */

    public function handle()

    {

        // Your query logic here

        DB::table('faqs')

            ->update([
                'title' => 'automaticvalue',
                'para' => 'automatic para',
                'role' => 'automatic',
        ]);

        $this->info('Daily query executed successfully!');

        return 0;

    }

}


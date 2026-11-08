<?php

namespace App\Core\Console;

use App\Core\AuditLog\ActivityLogger;
use Illuminate\Console\Command;

/**
 * Reports any edited or deleted activity log entry. NFR-SEC-10
 */
class VerifyActivityLog extends Command
{
    protected $signature = 'activity-log:verify';

    protected $description = 'Check the hash chain of the activity log and report tampered entries';

    public function handle(ActivityLogger $logger): int
    {
        $broken = $logger->verify();

        if ($broken === []) {
            $this->info('Activity log intact.');

            return self::SUCCESS;
        }

        $this->error('Activity log chain broken at entries: '.implode(', ', $broken));

        return self::FAILURE;
    }
}

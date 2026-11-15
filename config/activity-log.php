<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Log Retention
    |--------------------------------------------------------------------------
    |
    | Keep recent audit history online while preventing the activity_logs table
    | from growing indefinitely. Older rows are pruned by the scheduled command.
    |
    */
    'retention_days' => max(30, (int) env('ACTIVITY_LOG_RETENTION_DAYS', 365)),

    /* Delete old rows in small chunks to avoid long-running table locks. */
    'prune_batch_size' => max(100, (int) env('ACTIVITY_LOG_PRUNE_BATCH_SIZE', 1000)),
];

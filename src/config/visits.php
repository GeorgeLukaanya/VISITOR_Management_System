<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Visit retention window (Uganda DPA)
    |--------------------------------------------------------------------------
    |
    | How many days a visit record is kept before the `visits:prune` command
    | deletes it. The point of the system vs. the paper book is that visitor
    | PII does not live forever — keep this to the minimum the client needs.
    |
    | A value of 0 (or negative) means "retain indefinitely": the prune command
    | then does nothing, so disabling retention is just VISIT_RETENTION_DAYS=0.
    |
    */

    'retention_days' => (int) env('VISIT_RETENTION_DAYS', 180),

];

<?php

return [
    'assignment_batch_size' => (int) env('DRIVER_ASSIGNMENT_BATCH_SIZE', 15),
    'primary_driver_email' => env('PRIMARY_DRIVER_EMAIL', 'testdriver123@example.com'),
    'post_batch_assignment_policy' => env('POST_BATCH_ASSIGNMENT_POLICY', 'hold_for_staff'),
];

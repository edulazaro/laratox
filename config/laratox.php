<?php

return [

    // tf_live_… in production, tf_test_… while you integrate (never charged).
    'key' => env('TOXICFILTER_KEY'),

    // Redirects are never followed, so use https.
    'url' => env('TOXICFILTER_URL', 'https://toxicfilter.com'),

    // Seconds per attempt, and retries for a 429 or 5xx. A 402 is never retried.
    'timeout' => (int) env('TOXICFILTER_TIMEOUT', 10),
    'connect_timeout' => (int) env('TOXICFILTER_CONNECT_TIMEOUT', 5),
    'retries' => (int) env('TOXICFILTER_RETRIES', 2),

    'rule' => [
        // When the API cannot answer: "allow" lets the field through and logs it,
        // "refuse" fails it.
        'on_error' => env('TOXICFILTER_ON_ERROR', 'allow'),

        // Put ToxicFilter's reason in the validation message. Reasons are written in
        // English, so "auto" shows them only when the app's locale is English.
        'show_reason' => 'auto',
    ],

];

<?php

return [
    'reminders' => [
        'enabled' => env('SMKI_REMINDERS_ENABLED', true),
        'finding_deadline_days' => [7, 3, 1],
        'checklist_nudge_days_to_month_end' => [3, 1, 0],
    ],
];

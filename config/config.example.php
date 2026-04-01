<?php
/**
 * Example configuration file for Calendar Tag Scheduler
 *
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to config/config.php
 * 2. Customize the settings below
 * 3. config.php is in .gitignore and will not be committed
 *
 * Tag schema:
 *   'code'     — short calendar display code; first segment (before first '-') is the action
 *                and must match one of the actions listed in 'slots' below.
 *   'name'     — full description shown in statistics and ICS event body
 *   'period'   — minimum days between appearances (e.g. 3 = at most once every 3 days)
 *   'priority' — optional tiebreaker (1–5, default 3) used when multiple tags are eligible
 *                for the same slot on the same day. Does NOT affect frequency.
 *
 * Capacity rule: for each slot, sum(1/period) across all eligible tags should stay ≤ 1.0
 * Run the scheduler and check "АНАЛИЗ ЁМКОСТИ СЛОТОВ" to detect overloaded slots.
 */

return [
    /**
     * Time slots and their allowed action prefixes.
     * The action prefix is the first segment of a tag's 'code' (e.g. 'EDU', 'A', 'V').
     */
    'slots' => [
        'Утро'     => ['EDU', 'R'],
        'Завтрак'  => ['V', 'A'],
        'Дорога'   => ['V', 'A'],
        'Прогулка' => ['A'],
        'Ужин'     => ['V', 'A'],
        'Вечер'    => ['V', 'R', 'A'],
    ],

    /**
     * Allow the same tag to appear in more than one slot on a single day.
     */
    'allowSameDayRepetition' => true,

    /**
     * Tags. Add, remove, or tune 'period' to balance slot capacity.
     * Tip: start with few tags per slot, then add more while watching capacity %.
     */
    'tags' => [
        ['code' => 'EDU-TECH-YT', 'name' => 'Technical video course', 'period' => 2, 'priority' => 5],
        ['code' => 'A-PODCASTS',  'name' => 'Podcasts',               'period' => 2, 'priority' => 5],
        ['code' => 'A-NEWS-YT',   'name' => 'News (audio)',           'period' => 3, 'priority' => 4],
        ['code' => 'R-TECH-BOOK', 'name' => 'Technical book',         'period' => 3, 'priority' => 4],
        ['code' => 'R-FIC-BOOK',  'name' => 'Fiction book',           'period' => 4, 'priority' => 3],
        ['code' => 'V-YT-RL',     'name' => 'Watch Later videos',     'period' => 5, 'priority' => 2],
    ],
];

<?php
/**
 * Example configuration file for Calendar Tag Scheduler
 *
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to config/config.php
 * 2. Customize the settings below
 * 3. The config.php file is in .gitignore and won't be committed
 */

return [
    /**
     * Time slots and their allowed actions
     *
     * Common actions:
     * - EDU (Study/Educational)
     * - R (Read)
     * - A (Listen/Audio)
     * - V (View/Watch)
     * - D (Develop/Create)
     *
     * You can define custom actions in your tags and reference them here.
     * The system dynamically extracts actions from your tags.
     */
    'slots' => [
        'Утро' => ['EDU', 'R'],
        'Завтрак' => ['V', 'A'],
        'Дорога' => ['V', 'A'],
        'Прогулка' => ['A'],
        'Ужин' => ['V', 'A'],
        'Вечер' => ['V', 'R', 'A']
    ],

    /**
     * Priority to period mapping (days between repetitions)
     * Priority 5 = most important, Priority 1 = least important
     */
    'priorityToPeriod' => [
        5 => 2,  // Every 2 days
        4 => 3,  // Every 3 days
        3 => 4,  // Every 4 days
        2 => 5,  // Every 5 days
        1 => 6,  // Every 6 days
    ],

    /**
     * Allow same tag to appear multiple times in one day
     * Set to false if you want each tag to appear only once per day
     */
    'allowSameDayRepetition' => true,

    /**
     * Tags data
     * Each tag has: name, action, type, channel, priority (1-5)
     *
     * You can use standard actions (EDU, R, A, V) or define custom ones (like D for Develop).
     * Custom actions will be automatically recognized by the system.
     *
     * Add your own tags below:
     */
    'tags' => [
        // Example tags - customize for your needs
        ["name" => "Technical video course", "action" => "EDU", "type" => "TECH", "channel" => "YT", "priority" => 5],
        ["name" => "Podcasts", "action" => "A", "type" => "POD", "channel" => "PODCASTS", "priority" => 5],
        ["name" => "News (audio)", "action" => "A", "type" => "NEWS", "channel" => "YT", "priority" => 4],
        ["name" => "Technical book", "action" => "R", "type" => "TECH", "channel" => "BOOK", "priority" => 4],
        ["name" => "Fiction book", "action" => "R", "type" => "FIC", "channel" => "BOOK", "priority" => 3],
        ["name" => "Watch Later videos", "action" => "V", "type" => "RL", "channel" => "YT", "priority" => 2],

        // Example with custom action 'D' (Develop/Create)
        // ["name" => "My Project", "action" => "D", "type" => "PHP", "channel" => "GitHub", "priority" => 4],
    ],
];

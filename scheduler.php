<?php
/**
 * Calendar Tag Scheduler v2
 *
 * Гибридный алгоритм распределения тегов по календарю
 * с генерацией повторяющихся событий на весь день в ICS
 */

// ==================== POLYFILLS ====================

if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        return strlen(utf8_decode($str));
    }
}

if (!function_exists('mb_strwidth')) {
    function mb_strwidth($str, $encoding = 'UTF-8') {
        $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
        if (preg_match_all('/./su', $str, $matches)) {
            return count($matches[0]);
        }
        return 0;
    }
}

// ==================== CONFIGURATION ====================

$configFile = __DIR__ . '/config/config.php';

if (!file_exists($configFile)) {
    die("ERROR: Configuration file not found.\n\n" .
        "Please copy config/config.example.php to config/config.php and customize your settings.\n" .
        "Example: cp config/config.example.php config/config.php\n");
}

$config = require $configFile;

$slots = $config['slots'];
$priorityToPeriod = $config['priorityToPeriod'];
$allowSameDayRepetition = $config['allowSameDayRepetition'];
$tagsData = $config['tags'];

// ==================== CLASS ====================

/**
 * Calendar Tag Scheduler
 *
 * Distributes content tags across calendar time slots using
 * a priority-based hybrid scheduling algorithm
 */
class CalendarTagScheduler
{
    public $tags = [];
    private $strlenFunc;
    public $slots = [];
    public $priorityToPeriod = [];
    public $schedule = [];
    private $lastUsed = [];
    public $tagFirstDates = [];
    private $tagFirstSlots = [];
    private $allowSameDayRepetition = true;

    /**
     * Constructor
     *
     * @param array $tagsData Array of tag definitions
     * @param array $slots Time slots with allowed actions
     * @param array $priorityToPeriod Priority to period mapping
     * @param bool $allowSameDayRepetition Allow tag repetition in one day
     */
    public function __construct($tagsData, $slots, $priorityToPeriod, $allowSameDayRepetition = true)
    {
        $this->allowSameDayRepetition = $allowSameDayRepetition;
        $this->slots = $slots;
        $this->priorityToPeriod = $priorityToPeriod;

        if (function_exists('mb_strwidth')) {
            $this->strlenFunc = 'mb_strwidth';
        } elseif (function_exists('mb_strlen')) {
            $this->strlenFunc = 'mb_strlen';
        } else {
            $this->strlenFunc = 'strlen';
        }

        $this->initializeTags($tagsData);
    }

    /**
     * Get string display length (UTF-8 aware)
     *
     * @param string $str String to measure
     * @return int Display width
     */
    private function getStringLength($str)
    {
        $func = $this->strlenFunc;
        if ($func === 'mb_strwidth' || $func === 'mb_strlen') {
            return $func($str, 'UTF-8');
        }
        return strlen($str);
    }

    /**
     * Initialize tags with codes and periods
     *
     * @param array $tagsData Raw tag data
     */
    private function initializeTags($tagsData)
    {
        $this->tags = [];
        $this->lastUsed = [];
        foreach ($tagsData as $tag) {
            $tag['code'] = $this->generateCode($tag);
            $tag['period'] = $this->priorityToPeriod[$tag['priority']];
            $this->tags[] = $tag;
            $this->lastUsed[$tag['code']] = null;
        }
    }

    /**
     * Update priority to period mapping
     *
     * @param array $mapping New priority mapping
     * @param array|null $tagsData Optional tag data to reinitialize
     */
    public function setPriorityToPeriod($mapping, $tagsData = null)
    {
        $this->priorityToPeriod = $mapping;
        if ($tagsData !== null) {
            $this->initializeTags($tagsData);
        } else {
            foreach ($this->tags as &$tag) {
                $tag['period'] = $this->priorityToPeriod[$tag['priority']];
            }
        }
    }

    /**
     * Generate tag code from components
     *
     * @param array $tag Tag with action, type, channel
     * @return string Tag code (e.g., "EDU-TECH-YT")
     */
    private function generateCode($tag)
    {
        $parts = [
            $tag['action'],
            $tag['type'],
            $tag['channel']
        ];
        return implode('-', array_filter($parts));
    }

    /**
     * Generate schedule for specified period
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param int $days Number of days to generate
     * @return array Generated schedule
     */
    public function generateSchedule($startDate, $days = 30)
    {
        $this->schedule = [];
        $this->tagFirstDates = [];
        $this->tagFirstSlots = [];
        $currentDate = new DateTime($startDate);

        for ($i = 0; $i < $days; $i++) {
            $dateStr = $currentDate->format('Y-m-d');
            $this->schedule[$dateStr] = [];

            $sortedSlots = $this->slots;
            uasort($sortedSlots, function($a, $b) {
                return count($a) - count($b);
            });

            foreach ($sortedSlots as $slotName => $allowedActions) {
                $shuffledActions = $allowedActions;
                shuffle($shuffledActions);

                $finalTag = null;
                foreach ($shuffledActions as $action) {
                    $availableTags = $this->getAvailableTags($action, $currentDate);

                    if (!empty($availableTags)) {
                        $selectedTag = $this->selectBestTag($availableTags, $dateStr);

                        $alreadyAssigned = false;
                        if (!$this->allowSameDayRepetition) {
                            foreach ($this->schedule[$dateStr] as $slot => $tag) {
                                if ($tag !== null && $tag['code'] === $selectedTag['code']) {
                                    $alreadyAssigned = true;
                                    break;
                                }
                            }
                        }

                        if (!$alreadyAssigned) {
                            $finalTag = $selectedTag;
                            $this->lastUsed[$finalTag['code']] = clone $currentDate;

                            if (!isset($this->tagFirstDates[$finalTag['code']])) {
                                $this->tagFirstDates[$finalTag['code']] = $dateStr;
                                $this->tagFirstSlots[$finalTag['code']] = $slotName;
                            }
                            break;
                        }
                    }
                }

                $this->schedule[$dateStr][$slotName] = $finalTag;
            }

            $currentDate->modify('+1 day');
        }

        return $this->schedule;
    }

    /**
     * Get available tags for action on given date
     *
     * @param string $action Action type (EDU, R, A, V)
     * @param DateTime $currentDate Current date
     * @return array Available tags
     */
    private function getAvailableTags($action, DateTime $currentDate)
    {
        $available = [];

        foreach ($this->tags as $tag) {
            if ($tag['action'] !== $action) continue;

            if ($this->lastUsed[$tag['code']] === null) {
                $available[] = $tag;
            } else {
                $daysSinceLastUse = $currentDate->diff($this->lastUsed[$tag['code']])->days;
                if ($daysSinceLastUse >= $tag['period']) {
                    $available[] = $tag;
                }
            }
        }

        return $available;
    }

    /**
     * Select best tag from available options
     *
     * @param array $availableTags Available tags
     * @param string $dateStr Current date string
     * @return array Selected tag
     */
    private function selectBestTag($availableTags, $dateStr)
    {
        usort($availableTags, function ($a, $b) use ($dateStr) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] - $a['priority'];
            }

            $daysA = $this->lastUsed[$a['code']] ? (new DateTime($dateStr))->diff($this->lastUsed[$a['code']])->days : 999;
            $daysB = $this->lastUsed[$b['code']] ? (new DateTime($dateStr))->diff($this->lastUsed[$b['code']])->days : 999;

            if ($daysA !== $daysB) {
                return $daysB - $daysA;
            }

            return rand(-1, 1);
        });

        return $availableTags[0];
    }

    /**
     * Get schedule as Markdown table
     *
     * @return string Markdown formatted table
     */
    public function getScheduleTable()
    {
        $output = "| Дата | Утро | Завтрак | Дорога | Прогулка | Ужин | Вечер |\n";
        $output .= "|------|------|---------|--------|----------|------|-------|\n";

        foreach ($this->schedule as $dateStr => $daySchedule) {
            $row = "| $dateStr |";
            foreach ($this->slots as $slotName => $_) {
                $tag = $daySchedule[$slotName] ?? null;
                $content = ($tag === null) ? '-' : $tag['code'];
                $row .= " $content |";
            }
            $output .= $row . "\n";
        }

        return $output;
    }

    /**
     * Get schedule as formatted ASCII table
     *
     * @param int|null $limit Limit number of days displayed
     * @return string Formatted table
     */
    public function getScheduleTableFormatted($limit = null)
    {
        $headers = ['Дата'];
        foreach ($this->slots as $slotName => $_) {
            $headers[] = $slotName;
        }

        $rows = [];
        $count = 0;
        foreach ($this->schedule as $dateStr => $daySchedule) {
            if ($limit !== null && $count >= $limit) {
                break;
            }
            $row = [$dateStr];
            foreach ($this->slots as $slotName => $_) {
                $tag = $daySchedule[$slotName] ?? null;
                $row[] = ($tag === null) ? '-' : $tag['code'];
            }
            $rows[] = $row;
            $count++;
        }

        $columnWidths = [];
        foreach ($headers as $i => $header) {
            $columnWidths[$i] = $this->getStringLength($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $columnWidths[$i] = max($columnWidths[$i], $this->getStringLength($cell));
            }
        }

        $output = '';

        $output .= '┌';
        foreach ($columnWidths as $i => $width) {
            $output .= str_repeat('─', $width + 2);
            $output .= ($i < count($columnWidths) - 1) ? '┬' : '┐';
        }
        $output .= "\n";

        $output .= '│';
        foreach ($headers as $i => $header) {
            $padding = $columnWidths[$i] - $this->getStringLength($header);
            $output .= ' ' . $header . str_repeat(' ', $padding) . ' │';
        }
        $output .= "\n";

        $output .= '├';
        foreach ($columnWidths as $i => $width) {
            $output .= str_repeat('─', $width + 2);
            $output .= ($i < count($columnWidths) - 1) ? '┼' : '┤';
        }
        $output .= "\n";

        foreach ($rows as $row) {
            $output .= '│';
            foreach ($row as $i => $cell) {
                $padding = $columnWidths[$i] - $this->getStringLength($cell);
                $output .= ' ' . $cell . str_repeat(' ', $padding) . ' │';
            }
            $output .= "\n";
        }

        $output .= '└';
        foreach ($columnWidths as $i => $width) {
            $output .= str_repeat('─', $width + 2);
            $output .= ($i < count($columnWidths) - 1) ? '┴' : '┘';
        }
        $output .= "\n";

        return $output;
    }

    /**
     * Get tag summary as Markdown table
     *
     * @return string Markdown formatted summary
     */
    public function getTagSummary()
    {
        $summary = "| Тег (Код) | Приоритет | Период (дней) | Первая дата |\n";
        $summary .= "|-----------|-----------|---------------|-------------|\n";

        asort($this->tagFirstDates);

        foreach ($this->tagFirstDates as $code => $firstDate) {
            $tag = null;
            foreach ($this->tags as $t) {
                if ($t['code'] === $code) {
                    $tag = $t;
                    break;
                }
            }

            if ($tag) {
                $summary .= "| {$tag['code']} | {$tag['priority']} | {$tag['period']} | $firstDate |\n";
            }
        }

        return $summary;
    }

    /**
     * Get tag summary as formatted ASCII table
     *
     * @return string Formatted table
     */
    public function getTagSummaryFormatted()
    {
        $headers = ['Тег (Код)', 'Приоритет', 'Период (дней)', 'Первая дата'];

        asort($this->tagFirstDates);

        $rows = [];
        foreach ($this->tagFirstDates as $code => $firstDate) {
            $tag = null;
            foreach ($this->tags as $t) {
                if ($t['code'] === $code) {
                    $tag = $t;
                    break;
                }
            }

            if ($tag) {
                $rows[] = [
                    $tag['code'],
                    (string)$tag['priority'],
                    (string)$tag['period'],
                    $firstDate
                ];
            }
        }

        $columnWidths = [];
        foreach ($headers as $i => $header) {
            $columnWidths[$i] = $this->getStringLength($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $columnWidths[$i] = max($columnWidths[$i], $this->getStringLength($cell));
            }
        }

        $output = '';

        $output .= '┌';
        foreach ($columnWidths as $i => $width) {
            $output .= str_repeat('─', $width + 2);
            $output .= ($i < count($columnWidths) - 1) ? '┬' : '┐';
        }
        $output .= "\n";

        $output .= '│';
        foreach ($headers as $i => $header) {
            $padding = $columnWidths[$i] - $this->getStringLength($header);
            $output .= ' ' . $header . str_repeat(' ', $padding) . ' │';
        }
        $output .= "\n";

        $output .= '├';
        foreach ($columnWidths as $i => $width) {
            $output .= str_repeat('─', $width + 2);
            $output .= ($i < count($columnWidths) - 1) ? '┼' : '┤';
        }
        $output .= "\n";

        foreach ($rows as $row) {
            $output .= '│';
            foreach ($row as $i => $cell) {
                $padding = $columnWidths[$i] - $this->getStringLength($cell);
                $output .= ' ' . $cell . str_repeat(' ', $padding) . ' │';
            }
            $output .= "\n";
        }

        $output .= '└';
        foreach ($columnWidths as $i => $width) {
            $output .= str_repeat('─', $width + 2);
            $output .= ($i < count($columnWidths) - 1) ? '┴' : '┘';
        }
        $output .= "\n";

        return $output;
    }

    /**
     * Generate ICS calendar file with recurring events
     *
     * @param string $filename Output filename
     * @return string Filename created
     */
    public function generateICS($filename = 'calendar_tags.ics')
    {
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Calendar Tag Scheduler//RU\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:Теги информационного потребления\r\n";
        $ics .= "X-WR-TIMEZONE:Europe/Moscow\r\n";

        $eventId = 1;

        foreach ($this->tagFirstDates as $code => $firstDate) {
            $tag = null;
            foreach ($this->tags as $t) {
                if ($t['code'] === $code) {
                    $tag = $t;
                    break;
                }
            }

            if ($tag) {
                $ics .= $this->createRecurringEvent($firstDate, $tag, $eventId++);
            }
        }

        $ics .= "END:VCALENDAR\r\n";

        file_put_contents($filename, $ics);
        return $filename;
    }

    /**
     * Create recurring event for ICS file
     *
     * @param string $dateStr Start date (YYYY-MM-DD)
     * @param array $tag Tag data
     * @param int $id Event ID
     * @return string ICS VEVENT block
     */
    private function createRecurringEvent($dateStr, $tag, $id)
    {
        $date = new DateTime($dateStr);
        $dateFormatted = $date->format('Ymd');

        $slotName = $this->tagFirstSlots[$tag['code']] ?? '';

        $event = "BEGIN:VEVENT\r\n";
        $event .= "UID:tag-{$id}@scheduler\r\n";
        $event .= "DTSTAMP:" . date('Ymd\\THis\\Z') . "\r\n";
        $event .= "DTSTART;VALUE=DATE:{$dateFormatted}\r\n";
        $event .= "RRULE:FREQ=DAILY;INTERVAL={$tag['period']}\r\n";
        $event .= "SUMMARY:{$slotName}:{$tag['code']}\r\n";

        $actionNames = [
            'EDU' => 'Изучать',
            'R' => 'Читать',
            'A' => 'Слушать',
            'V' => 'Смотреть'
        ];
        $actionName = $actionNames[$tag['action']] ?? $tag['action'];
        $event .= "DESCRIPTION:{$tag['name']} ({$actionName}, каждые {$tag['period']} дней)\r\n";
        $event .= "CATEGORIES:{$tag['action']}\r\n";
        $event .= "STATUS:CONFIRMED\r\n";
        $event .= "TRANSP:TRANSPARENT\r\n";
        $event .= "END:VEVENT\r\n";

        return $event;
    }

    /**
     * Get scheduling statistics
     *
     * @return array Statistics array
     */
    public function getStatistics()
    {
        $stats = [
            'total_days' => count($this->schedule),
            'empty_days' => 0,
            'days_with_content' => 0,
            'total_tags' => count($this->tags),
            'used_tags' => count($this->tagFirstDates),
            'unused_tags' => count($this->tags) - count($this->tagFirstDates)
        ];

        foreach ($this->schedule as $daySchedule) {
            $hasContent = false;
            foreach ($daySchedule as $tag) {
                if ($tag !== null) {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) {
                $stats['days_with_content']++;
            } else {
                $stats['empty_days']++;
            }
        }

        return $stats;
    }

    /**
     * Print formatted statistics
     *
     * @return string Formatted statistics
     */
    public function printStatistics()
    {
        $stats = $this->getStatistics();

        $output = "\n=== СТАТИСТИКА ===\n\n";
        $output .= "Всего дней в расписании: {$stats['total_days']}\n";
        $output .= "Дней с контентом: {$stats['days_with_content']}\n";
        $output .= "Полностью пустых дней: {$stats['empty_days']}\n";
        $output .= "Процент заполнения: " . round(($stats['days_with_content'] / $stats['total_days']) * 100, 1) . "%\n";
        $output .= "\nВсего тегов определено: {$stats['total_tags']}\n";
        $output .= "Использовано в расписании: {$stats['used_tags']}\n";
        $output .= "Не использовано: {$stats['unused_tags']}\n";

        return $output;
    }
}

// ==================== USAGE ====================

$scheduler = new CalendarTagScheduler($tagsData, $slots, $priorityToPeriod, $allowSameDayRepetition);

$startDate = date('Y-m-01');
$daysInMonth = date('t');
$scheduler->generateSchedule($startDate, $daysInMonth);

$icsFile = $scheduler->generateICS('calendar_tags.ics');

// Detect if running in web context
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    // Web output - HTML
    outputHtml($scheduler, $icsFile, $startDate, $daysInMonth);
} else {
    // Console output - Keep original format
    echo "=== РАСПИСАНИЕ КАЛЕНДАРЯ (весь месяц) ===\n\n";
    echo $scheduler->getScheduleTableFormatted();

    echo "\n=== СВОДКА ПО ТЕГАМ ===\n\n";
    echo $scheduler->getTagSummaryFormatted();

    echo $scheduler->printStatistics();

    echo "\n\n=== ICS-ФАЙЛ СОЗДАН ===\n";
    echo "Файл: $icsFile\n";
    echo "Формат: События на весь день с правилами повторения (RRULE)\n";
    echo "Каждый тег появляется как отдельное повторяющееся событие.\n";
    echo "Вы можете импортировать его в Google Calendar, Outlook или другой календарь.\n";
}

/**
 * Output HTML version for web browser
 *
 * @param CalendarTagScheduler $scheduler Scheduler instance
 * @param string $icsFile ICS filename
 * @param string $startDate Start date
 * @param int $daysInMonth Number of days
 */
function outputHtml($scheduler, $icsFile, $startDate, $daysInMonth)
{
    $stats = $scheduler->getStatistics();
    $currentMonth = date('F Y', strtotime($startDate));
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Tag Schedule - <?php echo $currentMonth; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }

        h2 {
            color: #444;
            margin-top: 40px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            font-size: 24px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 13px;
        }

        th {
            background: #4CAF50;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 10px 8px;
            border: 1px solid #dee2e6;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        tr:hover {
            background: #e9ecef;
        }

        .tag-code {
            font-family: 'Courier New', monospace;
            background: #e7f5e8;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 12px;
            white-space: nowrap;
        }

        .empty-slot {
            color: #999;
            text-align: center;
        }

        .table-container {
            overflow-x: auto;
            margin-bottom: 30px;
        }

        .priority-1 { background: #ffebee !important; }
        .priority-2 { background: #fff3e0 !important; }
        .priority-3 { background: #fff9c4 !important; }
        .priority-4 { background: #e8f5e9 !important; }
        .priority-5 { background: #e1f5fe !important; }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Calendar Tag Schedule</h1>
        <p class="subtitle"><?php echo $currentMonth; ?> (<?php echo $daysInMonth; ?> days)</p>

        <div class="actions">
            <a href="<?php echo basename($icsFile); ?>" download class="btn btn-primary">Download ICS Calendar File</a>
            <a href="config-editor.php" class="btn btn-secondary">Edit Configuration</a>
        </div>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['total_days']; ?></div>
                <div class="stat-label">Total Days</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['days_with_content']; ?></div>
                <div class="stat-label">Days with Content</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo round(($stats['days_with_content'] / $stats['total_days']) * 100, 1); ?>%</div>
                <div class="stat-label">Fill Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['used_tags']; ?> / <?php echo $stats['total_tags']; ?></div>
                <div class="stat-label">Tags Used</div>
            </div>
        </div>

        <!-- Schedule Table -->
        <h2>Schedule</h2>
        <div class="table-container">
            <?php echo generateHtmlScheduleTable($scheduler); ?>
        </div>

        <!-- Tag Summary -->
        <h2>Tag Summary</h2>
        <div class="table-container">
            <?php echo generateHtmlTagSummary($scheduler); ?>
        </div>

        <div class="footer">
            <p>Generated by Calendar Tag Scheduler v2</p>
            <p>Import the ICS file into Google Calendar, Outlook, or any calendar application</p>
        </div>
    </div>
</body>
</html>
<?php
}

/**
 * Generate HTML schedule table
 *
 * @param CalendarTagScheduler $scheduler Scheduler instance
 * @return string HTML table
 */
function generateHtmlScheduleTable($scheduler)
{
    $schedule = $scheduler->schedule;
    $slots = $scheduler->slots;

    $html = '<table>';
    $html .= '<thead><tr>';
    $html .= '<th>Date</th>';

    foreach ($slots as $slotName => $_) {
        $html .= '<th>' . htmlspecialchars($slotName) . '</th>';
    }

    $html .= '</tr></thead>';
    $html .= '<tbody>';

    foreach ($schedule as $dateStr => $daySchedule) {
        $html .= '<tr>';
        $html .= '<td><strong>' . htmlspecialchars($dateStr) . '</strong></td>';

        foreach ($slots as $slotName => $_) {
            $tag = $daySchedule[$slotName] ?? null;

            if ($tag === null) {
                $html .= '<td class="empty-slot">-</td>';
            } else {
                $priorityClass = 'priority-' . $tag['priority'];
                $html .= '<td class="' . $priorityClass . '">';
                $html .= '<span class="tag-code" title="' . htmlspecialchars($tag['name']) . '">';
                $html .= htmlspecialchars($tag['code']);
                $html .= '</span>';
                $html .= '</td>';
            }
        }

        $html .= '</tr>';
    }

    $html .= '</tbody>';
    $html .= '</table>';

    return $html;
}

/**
 * Generate HTML tag summary table
 *
 * @param CalendarTagScheduler $scheduler Scheduler instance
 * @return string HTML table
 */
function generateHtmlTagSummary($scheduler)
{
    $tagFirstDates = $scheduler->tagFirstDates;
    $tags = $scheduler->tags;

    asort($tagFirstDates);

    $html = '<table>';
    $html .= '<thead><tr>';
    $html .= '<th>Tag Code</th>';
    $html .= '<th>Name</th>';
    $html .= '<th>Priority</th>';
    $html .= '<th>Period (days)</th>';
    $html .= '<th>First Date</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    foreach ($tagFirstDates as $code => $firstDate) {
        $tag = null;
        foreach ($tags as $t) {
            if ($t['code'] === $code) {
                $tag = $t;
                break;
            }
        }

        if ($tag) {
            $priorityClass = 'priority-' . $tag['priority'];
            $html .= '<tr class="' . $priorityClass . '">';
            $html .= '<td><span class="tag-code">' . htmlspecialchars($tag['code']) . '</span></td>';
            $html .= '<td>' . htmlspecialchars($tag['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($tag['priority']) . '</td>';
            $html .= '<td>' . htmlspecialchars($tag['period']) . '</td>';
            $html .= '<td>' . htmlspecialchars($firstDate) . '</td>';
            $html .= '</tr>';
        }
    }

    $html .= '</tbody>';
    $html .= '</table>';

    return $html;
}

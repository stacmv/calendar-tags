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
    private $tags = [];
    private $strlenFunc;
    private $slots = [];
    public $priorityToPeriod = [];
    private $schedule = [];
    private $lastUsed = [];
    private $tagFirstDates = [];
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

echo "=== РАСПИСАНИЕ КАЛЕНДАРЯ (весь месяц) ===\n\n";
echo $scheduler->getScheduleTableFormatted();

echo "\n=== СВОДКА ПО ТЕГАМ ===\n\n";
echo $scheduler->getTagSummaryFormatted();

echo $scheduler->printStatistics();

$icsFile = $scheduler->generateICS('calendar_tags.ics');
echo "\n\n=== ICS-ФАЙЛ СОЗДАН ===\n";
echo "Файл: $icsFile\n";
echo "Формат: События на весь день с правилами повторения (RRULE)\n";
echo "Каждый тег появляется как отдельное повторяющееся событие.\n";
echo "Вы можете импортировать его в Google Calendar, Outlook или другой календарь.\n";

# calendar-tags — Design Document

Архитектура, ключевые детали реализации, ограничения и будущие расширения. Вынесено из CLAUDE.md.

## Architecture

**Core Algorithm** (scheduler.php:207-262):
The hybrid priority-based scheduling algorithm works as follows:
1. For each day, sort time slots by number of allowed actions (ascending) - slots with fewer choices get priority
2. For each slot, shuffle allowed actions randomly for variety
3. For each action, filter available tags by: (a) action matches, (b) minimum interval has passed since last use
4. Sort available tags by priority (descending), then by days since last use (descending), then randomly
5. Select the first available tag that hasn't been used today (if allowSameDayRepetition is false)
6. Update lastUsed tracking and record first appearance date

**Key Principles**:
- **Slot prioritization**: Restrictive slots (e.g., Прогулка with only Audio) process first to claim scarce tags
- **Priority-first**: Higher priority tags scheduled before lower when both available
- **Interval respect**: No tag appears before its minimum period has elapsed
- **Randomization**: Action order and equal-priority ties randomized for variety
- **Fairness**: Lower priority tags fill gaps when high-priority tags unavailable
- **Anti-repetition**: Configurable same-day repetition prevention

**Class Structure**:
- `CalendarTagScheduler` - Main class with full PHPDoc documentation
- Properties: `$tags`, `$slots`, `$priorityToPeriod`, `$schedule`, `$lastUsed`, `$tagFirstDates`, `$allowSameDayRepetition`
- Public methods: `__construct()`, `generateSchedule()`, `getScheduleTable()`, `getScheduleTableFormatted()`, `getTagSummary()`, `getTagSummaryFormatted()`, `generateICS()`, `getStatistics()`, `printStatistics()`, `setPriorityToPeriod()`
- Private methods: `initializeTags()`, `generateCode()`, `getAvailableTags()`, `selectBestTag()`, `createRecurringEvent()`, `getStringLength()`

**ICS Generation** (scheduler.php:539-605):
- Creates ONE recurring event per tag (not individual events per occurrence)
- Uses RRULE (recurrence rule) with `FREQ=DAILY;INTERVAL=N`
- All-day events (`DTSTART;VALUE=DATE`)
- RFC 5545 compliant for compatibility with Google Calendar, Outlook, Apple Calendar
- Event starts on first scheduled appearance date

## Important Implementation Details

**Tag Code Generation** (scheduler.php:190-198): Filters empty components when building tag codes. Empty TYPE or CHANNEL fields are acceptable (e.g., `A-MUS` for music listening).

**Best Tag Selection Logic** (scheduler.php:298-316): Sorts by priority first, then by days since last use, then randomly for equal cases. This prevents bias and adds variety.

**ICS Date Format**: Events use `VALUE=DATE` format (YYYYMMDD) without time component to create all-day events that don't trigger time-specific notifications.

**First Appearance Tracking** (scheduler.php:247-249): The `$tagFirstDates` array records when each tag first appears in the schedule, used as the DTSTART for recurring ICS events.

**UTF-8 Support** (scheduler.php:11-25): Custom polyfills for `mb_strlen` and `mb_strwidth` ensure proper Cyrillic text handling on systems without mbstring extension.

## Known Limitations

- No completion tracking (system doesn't know if content was actually consumed)
- Static priority (cannot adjust dynamically based on external factors)
- First-day overload (all tags potentially assigned on day 1)
- Manual tag definition required (no auto-import from platforms)
- Tags with identical periods may synchronize and perpetually compete
- Empty days occur when all tags recently used (interval constraints)

## Future Extension Points

See docs/prd.md sections 6.1-6.3 for planned enhancements including:
- Special rules engine (day-of-week restrictions, date exclusions, mandatory tags)
- Audit events (periodic review reminders)
- Enhanced ICS features (alarms, color coding, timezone support)
- Web interface for visual editing
- API integrations with content platforms
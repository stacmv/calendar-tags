# Product Requirements Document (PRD)
## Calendar Tag Scheduler - Information Consumption Management System

**Version:** 1.0  
**Last Updated:** November 5, 2025  
**Author:** Perplexity.AI
**Status:** Active Development

---

## 1. Executive Summary

### 1.1 Product Overview
Calendar Tag Scheduler is a PHP-based system designed to optimize personal information consumption by distributing content sources across daily time slots using a priority-based hybrid scheduling algorithm. The system generates both visual schedules (Markdown tables) and importable calendar files (ICS format) with recurring events.

### 1.2 Problem Statement
Modern knowledge workers consume information from 20+ different sources (YouTube playlists, podcasts, books, RSS feeds, Telegram channels, etc.) without a systematic approach. This leads to:
- Important high-priority content being neglected
- Low-priority content consuming disproportionate time
- Lack of balance between learning, entertainment, and staying informed
- Decision fatigue when choosing what to consume

### 1.3 Solution
An automated scheduling system that:
- Tags content by action type (Study/EDU, Read/R, Listen/A, Watch/V)
- Assigns priority levels (1-5) to each source
- Distributes sources across time slots based on availability and priority
- Generates recurring calendar events for seamless integration
- Respects minimum intervals between repetitions of the same source

---

## 2. Core Concepts

### 2.1 Tag Structure
Each information source is represented as a tag with the format:
```
{ACTION}-{TYPE}-{CHANNEL}
```

**Examples:**
- `EDU-TECH-YT` = Study technical content on YouTube
- `A-NEWS-YT` = Listen to news on YouTube
- `R-REL-BOOK` = Read relationship/self-development book

**Components:**
- **ACTION**: Activity type (EDU, R, A, V)
- **TYPE**: Content theme (TECH, REL, NEWS, FIC, etc.)
- **CHANNEL**: Source platform (YT, BOOK, TG, PODCASTS, etc.)

Note: TYPE or CHANNEL may be empty for generic sources.

### 2.2 Actions (Activity Types)
| Code | Full Name | Description | Typical Time Slots |
|------|-----------|-------------|-------------------|
| EDU | Study/Learn | Deep focus learning requiring concentration | Morning, Daytime |
| R | Read | Reading text content | Morning, Commute, Evening |
| A | Listen | Audio consumption, can be passive | Breakfast, Commute, Walk, Dinner, Evening |
| V | View/Watch | Video content requiring visual attention | Breakfast, Commute, Dinner, Evening |

### 2.3 Time Slots
| Slot | Allowed Actions | Use Case |
|------|----------------|--------------|----------|
| Morning (Утро) | EDU, R | Focused learning, reading |
| Breakfast (Завтрак) | V, A | Light video or audio during meal |
| Commute (Дорога) | V, A | Content during transportation |
| Walk (Прогулка) | A | Audio-only during exercise |
| Dinner (Ужин) | V, A | Content during meal |
| Evening (Вечер) | V, R, A | Leisure content, relaxation |

### 2.4 Priority Levels
| Priority | Frequency | Use Case | Example |
|----------|-----------|----------|---------|
| 5 | Every 2 days | Critical daily learning/info | Premium podcasts, key playlists |
| 4 | Every 4 days | Important regular content | Technical books, news feeds |
| 3 | Every 8 days | Regular but not urgent | General books, secondary channels |
| 2 | Every 16 days | Low priority, occasional | Entertainment, archived content |
| 1 | Every 32 days | Rare, optional | Music listening, documentaries |

---

## 3. Functional Requirements

### 3.1 Core Algorithm: Hybrid Priority-Based Scheduling

**Algorithm Logic:**
```
FOR each day in schedule:
  FOR each time slot:
    FOR each allowed action in slot:
      1. Collect available tags where:
         - Tag action matches slot action
         - Days since last use >= tag period
      2. IF available tags exist:
         - Sort by priority (descending)
         - If priorities equal, sort by days since last use (descending)
         - Select top tag
      3. Assign tag to slot
      4. Update lastUsed[tag] = current date
      5. Record first date if new tag
```

**Key Principles:**
- **Priority-first**: Higher priority tags get scheduled first when available
- **Respect intervals**: No tag appears before its minimum interval has passed
- **Fairness**: Lower priority tags fill gaps when high-priority tags are unavailable
- **Anti-repetition**: Same tag cannot appear multiple times in one day

### 3.2 Input Requirements

**Tag Data Structure:**
```php
[
    "name" => "Human-readable description",
    "action" => "EDU|R|A|V",
    "type" => "TECH|REL|NEWS|FIC|...",  // Optional
    "channel" => "YT|BOOK|TG|...",       // Optional
    "priority" => 1-5                     // Required
]
```

**Configuration Parameters:**
- Start date (YYYY-MM-DD format)
- Number of days to generate (default: 60)
- Priority-to-period mapping (customizable)
- Time slot definitions (customizable)

### 3.3 Output Requirements

#### 3.3.1 Markdown Schedule Table
Format:
```
| Date | Morning | Breakfast | Commute | Walk | Dinner | Evening |
|------|---------|-----------|---------|------|--------|---------|
| 2025-11-01 | EDU-TECH-YT | A-NEWS-YT | ... | ... | ... | ... |
```

#### 3.3.2 Tag Summary Table
Format:
```
| Tag (Code) | Priority | Period (days) | First Date |
|------------|----------|---------------|------------|
| EDU-TECH-YT | 5 | 2 | 2025-11-10 |
```

#### 3.3.3 ICS Calendar File
Requirements:
- **Format**: iCalendar (RFC 5545 compliant)
- **Event type**: All-day events (DTSTART;VALUE=DATE)
- **Recurrence**: RRULE with FREQ=DAILY;INTERVAL=N
- **One event per tag**: Not one event per occurrence
- **Metadata**: SUMMARY (tag code), DESCRIPTION (full name + action), CATEGORIES (action type)

**Example Event:**
```
BEGIN:VEVENT
UID:tag-1@scheduler
DTSTAMP:20251105T180000Z
DTSTART;VALUE=DATE:20251110
RRULE:FREQ=DAILY;INTERVAL=2
SUMMARY:EDU-TECH-YT
DESCRIPTION:Technical video course (Study, every 2 days)
CATEGORIES:EDU
STATUS:CONFIRMED
TRANSP:TRANSPARENT
END:VEVENT
```

#### 3.3.4 Statistics Output
Required metrics:
- Total days in schedule
- Days with content vs empty days
- Fill percentage
- Total tags defined
- Tags used vs unused
- Tags per action type breakdown

---

## 4. Technical Specifications

### 4.1 Technology Stack
- **Language**: PHP 7.4+ (no external dependencies)
- **Core Classes**: DateTime for date manipulation
- **File I/O**: Native PHP file operations
- **Encoding**: UTF-8 for international character support

### 4.2 Class Structure

#### Main Class: `CalendarTagScheduler`

**Properties:**
- `$tags` - Array of tag objects with computed codes and periods
- `$slots` - Associative array mapping slot names to allowed actions
- `$priorityToPeriod` - Mapping of priority levels to day intervals
- `$schedule` - Generated schedule data structure
- `$lastUsed` - Tracking array for last usage date per tag
- `$tagFirstDates` - First appearance date per tag code

**Public Methods:**
- `__construct($tagsData)` - Initialize with tag data array
- `generateSchedule($startDate, $days)` - Generate schedule for N days
- `getScheduleTable()` - Return Markdown formatted schedule
- `getTagSummary()` - Return Markdown formatted tag summary
- `generateICS($filename)` - Create ICS file with recurring events
- `printStatistics()` - Output usage statistics
- `getStatistics()` - Return statistics array

**Private Methods:**
- `initializeTags($tagsData)` - Process and validate tag data
- `generateCode($tag)` - Create tag code from components
- `getAvailableTags($action, $date)` - Filter available tags by action and interval
- `selectBestTag($availableTags, $dateStr)` - Apply hybrid selection logic
- `createRecurringEvent($date, $tag, $id)` - Generate ICS VEVENT block

### 4.3 Data Flow
```
Input (PHP Array)
    ↓
initializeTags() - Validate and compute periods/codes
    ↓
generateSchedule() - Main scheduling algorithm
    ↓
    ├→ getScheduleTable() - Markdown output
    ├→ getTagSummary() - Tag metadata
    └→ generateICS() - ICS file generation
```

---

## 5. Non-Functional Requirements

### 5.1 Performance
- Generate 60-day schedule in < 1 second on standard hardware
- ICS file size < 100KB for typical 30-tag configurations
- Memory footprint < 10MB during execution

### 5.2 Usability
- Zero configuration for default use case
- Clear error messages for invalid input
- Human-readable output formats
- Compatible with major calendar applications (Google Calendar, Outlook, Apple Calendar)

### 5.3 Maintainability
- Single-file architecture for easy deployment
- Clear separation of concerns (scheduling vs output generation)
- Inline documentation for complex logic
- Example usage included in source

### 5.4 Extensibility
Key extension points:
- Custom priority-to-period mappings
- Additional time slots
- New output formats
- Custom sorting algorithms
- Filter/exclusion rules

---

## 6. Future Enhancements

### 6.1 Priority 1 (Next Release)
1. **Special Rules Engine**
   - Day-of-week restrictions (e.g., movies only on weekends)
   - Date range exclusions (e.g., vacation periods)
   - Mandatory tags (must appear in schedule)
   - Forbidden combinations (conflict resolution)

2. **Audit Events**
   - Generate periodic "audit" reminders for content review
   - High-priority sources: monthly audit
   - Low-priority sources: quarterly audit
   - Max one audit per day

3. **Enhanced ICS Features**
   - Alarms/reminders before events
   - Color coding by action or priority
   - Timezone support beyond UTC
   - VTODO for one-time actions

### 6.2 Priority 2 (Future Consideration)
1. **Web Interface**
   - Visual tag editor
   - Interactive calendar preview
   - Drag-and-drop rescheduling
   - Real-time statistics dashboard

2. **Advanced Scheduling**
   - Machine learning for optimal periods based on completion rates
   - Context-aware scheduling (weather, location, energy levels)
   - Dependency chains (Course A must complete before Course B)
   - Dynamic priority adjustment

3. **Multi-User Support**
   - Shared calendars for teams
   - Role-based tag visibility
   - Collaborative content curation

4. **Analytics & Insights**
   - Completion tracking
   - Time-spent per tag/action
   - Burnout prevention (overload detection)
   - Content diversity metrics

### 6.3 Priority 3 (Long-term Vision)
1. **Integration Ecosystem**
   - Direct API connections (YouTube, Spotify, Pocket, Telegram)
   - Automatic tag creation from new subscriptions
   - Completion sync from consumption platforms
   - Cross-device state synchronization

2. **AI-Powered Features**
   - Content recommendation based on goals
   - Natural language tag creation ("remind me to read tech articles twice a week")
   - Automatic priority adjustment based on engagement
   - Smart conflict resolution

---

## 7. Known Limitations

### 7.1 Current Constraints
1. **Static Priority**: Priority cannot change dynamically based on external factors
2. **No Overlap Prevention**: Multiple tags can theoretically be assigned to overlapping time slots (though algorithm tries to prevent this)
3. **Manual Tag Definition**: All tags must be manually defined in code
4. **No Completion Tracking**: System doesn't know if user actually consumed content
5. **Single Calendar Only**: Cannot generate separate calendars per action or theme
6. **No Conflict Resolution**: If all high-priority tags are unavailable, slots remain empty rather than promoting lower-priority tags

### 7.2 Edge Cases
1. **All Tags Exhausted**: If all tags have been used recently and none are available, days remain empty
2. **Action Imbalance**: If one action (e.g., EDU) has many more sources than others, it may dominate the schedule
3. **First-Day Overload**: Day 1 assigns all tags for the first time, potentially creating an unrealistic schedule
4. **Period Sync**: Tags with same period may align and compete perpetually

---

## 8. Testing Requirements

### 8.1 Unit Tests
- Tag code generation with all component combinations
- Priority-to-period mapping accuracy
- Available tags filtering (respecting intervals)
- Best tag selection logic (priority + recency)
- Date arithmetic (interval calculation)

### 8.2 Integration Tests
- End-to-end schedule generation (10-tag, 30-day scenario)
- ICS file validity (RFC 5545 compliance)
- Markdown output formatting
- Statistics accuracy (match actual schedule)

### 8.3 Acceptance Tests
- Import ICS into Google Calendar successfully
- Import ICS into Apple Calendar successfully
- Import ICS into Outlook successfully
- Verify recurring events appear with correct intervals
- Verify events are all-day (no time specified)
- Verify event descriptions are human-readable

### 8.4 Performance Tests
- 100 tags, 365 days: < 5 seconds
- 1000 events in ICS: < 500KB file size
- Memory usage: < 50MB peak

---

## 9. Acceptance Criteria

### 9.1 Must Have (MVP)
- ✅ Generate 60-day schedule from 27 predefined tags
- ✅ Output Markdown table with tag codes per slot
- ✅ Generate RFC 5545 compliant ICS file
- ✅ Events are all-day (no time component)
- ✅ Events use RRULE for recurrence (not individual events)
- ✅ One ICS event per unique tag (not per occurrence)
- ✅ High-priority tags appear more frequently than low-priority
- ✅ No tag appears before its minimum interval
- ✅ Statistics show days filled vs empty

### 9.2 Should Have (Enhancements)
- ⏳ Configurable priority-to-period mapping
- ⏳ Configurable time slots and allowed actions
- ⏳ Export schedule to CSV format
- ⏳ Audit event generation
- ⏳ Tag usage frequency report

### 9.3 Could Have (Nice to Have)
- ⏳ CLI arguments for parameters (avoid editing PHP)
- ⏳ JSON input file support
- ⏳ HTML output format with styling
- ⏳ Visual calendar representation
- ⏳ Duplicate tag detection and warnings

---

## 10. Success Metrics

### 10.1 Quantitative
- **Schedule Fill Rate**: > 70% of days have at least one tag
- **Priority Adherence**: Priority-5 tags appear 12x more than priority-1 tags over 30 days
- **Tag Coverage**: > 90% of defined tags appear at least once
- **Calendar Import Success**: 100% success rate across major calendar apps

### 10.2 Qualitative
- User reports reduced decision fatigue
- User maintains schedule for 30+ consecutive days
- User successfully completes high-priority content
- User achieves balanced information diet

---

## 11. Glossary

| Term | Definition |
|------|------------|
| Tag | A unique identifier for an information source (e.g., `EDU-TECH-YT`) |
| Action | Type of consumption activity (EDU, R, A, V) |
| Type | Content theme or subject (TECH, REL, NEWS, etc.) |
| Channel | Source platform or medium (YT, BOOK, TG, etc.) |
| Priority | Importance level (1-5) determining frequency |
| Period | Days between repetitions of a tag |
| Slot | Time block in the day with allowed actions |
| RRULE | Recurrence rule in ICS format defining repeating events |
| Hybrid Algorithm | Scheduling approach combining priority-first with interval respect |

---

## 12. Appendices

### Appendix A: Example Tag Dataset
See `scheduler.php` lines 318-346 for the complete example dataset.

### Appendix B: ICS Format Reference
- RFC 5545: https://datatracker.ietf.org/doc/html/rfc5545
- iCalendar Validator: https://icalendar.org/validator.html

### Appendix C: Priority-Period Formula
Current formula: `period = [2, 4, 8, 16, 32][priority - 1]`

Alternative formulas to consider:
- Exponential: `period = 2^(6 - priority)`
- Linear: `period = (6 - priority) * 5`
- Custom mapping per action type

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-01 | Perplexity.AI | Initial version with basic scheduling |
| 2.0 | 2025-11-05 | Perplexity.AI | Added RRULE, all-day events, hybrid algorithm |

---

**End of PRD**
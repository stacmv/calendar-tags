# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Calendar Tag Scheduler is a PHP-based information consumption management system that distributes content sources (YouTube playlists, podcasts, books, RSS feeds, Telegram channels, etc.) across daily time slots using a priority-based hybrid scheduling algorithm. It generates both visual schedules (Markdown/ASCII tables) and importable individual calendar events (ICS format).

## File Structure

```
calendar-tags/
├── config/
│   ├── config.php          # Default configuration (for CLI mode)
│   ├── config.example.php  # Configuration template
│   └── README.md          # Config documentation
├── users/                 # User-specific data (gitignored)
│   └── <hash>/           # Each user's directory
│       ├── config.php    # User configuration
│       └── calendar_tags.ics
├── auth.php              # Authentication helper class
├── login.php             # Login page
├── logout.php            # Logout handler
├── setup-user.php        # CLI user creation/management
├── index.php             # Landing page (authenticated)
├── scheduler.php         # Main application (web + CLI)
├── config-editor.php     # Web-based config editor
├── calendar_tags.ics     # Generated ICS (CLI mode, gitignored)
├── .htpasswd             # User credentials (gitignored)
├── CLAUDE.md             # This file
├── SETUP.md              # Setup and authentication guide
└── .gitignore            # Git ignore rules
```

**Main file structure:**
1. **Polyfills** - mb_string compatibility functions for UTF-8 support
2. **Configuration Loading** - Loads settings from config/config.php
3. **Class Definition** - CalendarTagScheduler class with full PHPDoc documentation
4. **Usage Example** - Instantiation and execution code

## Key Concepts

**Tag Structure**: `{ACTION}-{TYPE}-{CHANNEL}`
- ACTION: Activity type (EDU = Study, R = Read, A = Listen, V = View/Watch)
- TYPE: Content theme (TECH, REL, NEWS, FIC, etc.) - optional
- CHANNEL: Source platform (YT, BOOK, TG, PODCASTS, etc.) - optional
- Example: `EDU-TECH-YT` = Study technical content on YouTube

**Priority-to-Period Mapping**:
- Priority 5 → every 2 days (critical daily learning)
- Priority 4 → every 4 days (important regular content)
- Priority 3 → every 8 days (regular but not urgent)
- Priority 2 → every 16 days (low priority, occasional)
- Priority 1 → every 32 days (rare, optional)

**Time Slots and Allowed Actions**:
- Morning (Утро): EDU, R - focused learning/reading
- Breakfast (Завтрак): V, A - light media during meals
- Commute (Дорога): V, A - content during transportation
- Walk (Прогулка): A - audio-only during exercise
- Dinner (Ужин): V, A - content during meals
- Evening (Вечер): V, R, A - leisure content/relaxation

## Running the Code

### Web Interface (Multi-User with Authentication)

**Initial Setup:**
```bash
# Create a user account
php setup-user.php <username> <password>

# Start web server (or use Apache/Nginx)
php -S localhost:8000

# Visit http://localhost:8000/login.php
# Login with your credentials
# Configure tags via config editor
# View schedule and download ICS
```

**User Management:**
- Each user gets isolated data in `users/<hash>/`
- Config stored per-user: `users/<hash>/config.php`
- ICS generated per-user: `users/<hash>/calendar_tags.ics`
- See `SETUP.md` for detailed authentication documentation

### CLI Mode (Single-User, No Authentication)

**Initial Setup:**
```bash
# Copy configuration template
cp config/config.example.php config/config.php

# Edit your personal settings
nano config/config.php  # or use any editor
```

**Execute the scheduler:**
```bash
php scheduler.php
```

This will:
1. Load configuration from `config/config.php`
2. Generate a schedule for the current month (first day to last day)
3. Output a formatted ASCII table of the entire month
4. Create a tag summary table sorted by first usage date
5. Generate statistics (fill rate, tag usage, etc.)
6. Create `calendar_tags.ics` file with individual events for each scheduled occurrence

**Modify the schedule**: Edit `config/config.php`:
- `tags` array: Add/modify tags with name, action, type, channel, priority
- `priorityToPeriod` mapping: Adjust frequency for each priority level
- `slots` array: Modify time slots and allowed actions
- `allowSameDayRepetition`: Set to false to prevent tag repetition in one day

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
- Private methods: `initializeTags()`, `generateCode()`, `getAvailableTags()`, `selectBestTag()`, `createSingleEvent()`, `getStringLength()`

**ICS Generation** (scheduler.php:490-557):
- Creates individual events for each scheduled occurrence in the month
- No recurring events (no RRULE) - one VEVENT per tag occurrence
- All-day events (`DTSTART;VALUE=DATE`)
- RFC 5545 compliant for compatibility with Google Calendar, Outlook, Apple Calendar
- Each event includes slot name and tag code in summary (e.g., "Утро: EDU-TECH-YT")

## Important Implementation Details

**Tag Code Generation** (scheduler.php:190-198): Filters empty components when building tag codes. Empty TYPE or CHANNEL fields are acceptable (e.g., `A-MUS` for music listening).

**Best Tag Selection Logic** (scheduler.php:298-316): Sorts by priority first, then by days since last use, then randomly for equal cases. This prevents bias and adds variety.

**ICS Date Format**: Events use `VALUE=DATE` format (YYYYMMDD) without time component to create all-day events that don't trigger time-specific notifications.

**First Appearance Tracking** (scheduler.php:203-206): The `$tagFirstDates` array records when each tag first appears in the schedule, used for tag summary display.

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

# ========================================
# Planning Framework Integration
# ========================================

# CLAUDE.md Instructions

Copy this section into your project's `CLAUDE.md` file to integrate the Planning Framework.

---

## Starting a New Session

**IMPORTANT:** Before starting any work, follow these steps to restore context:

1. **Read session log** - See what was done last time and what's next:
   ```bash
   tail -50 docs/planning/session-log.md
   ```

2. **Check implementation status** - Review current phase and next task:
   ```bash
   head -40 docs/planning/implementation-plan.md
   ```

3. **Review decisions** - Refresh on architectural choices:
   ```bash
   cat docs/planning/decisions.md
   ```

4. **Start the task** - Begin with the task marked "Next Session" in implementation plan

**Planning Infrastructure Location:**
- `/docs/planning/implementation-plan.md` - Detailed task breakdown, component specs, progress tracking
- `/docs/planning/session-log.md` - Session-by-session progress and notes
- `/docs/planning/decisions.md` - Architecture Decision Records (ADR)
- `/docs/prd.md` - Product Requirements Document (what and why)

---

## Session End Ritual

Before ending a session, **ALWAYS**:

1. **Update session log** - Add entry to `docs/planning/session-log.md`:
   - Completed tasks (with checkboxes)
   - Decisions made (reference ADRs)
   - Any blockers
   - Next session priorities

2. **Update implementation plan** - In `docs/planning/implementation-plan.md`:
   - Check off completed tasks
   - Update "Quick Status" section
   - Mark next task clearly

3. **Document decisions** - Add to `docs/planning/decisions.md`:
   - Create ADR for any architectural decisions
   - Use standard ADR format

4. **Commit changes**:
   ```bash
   git add .
   git commit -m "Session YYYY-MM-DD: [Brief description]"
   ```

---

## Planning Framework Usage

This project uses a structured planning framework to maintain context across sessions.

**Key principles:**
- All progress tracked with checkboxes
- Decisions documented with rationale (ADRs)
- Next steps always clearly marked
- Context preserved for AI assistants

**For detailed framework documentation:**
- See `/docs/planning/FRAMEWORK.md` for complete guide
- See `/docs/planning/templates/` for document templates

---

## What AI Assistants Should Do

**On every session start:**
1. ✅ Read the three core planning documents (session log, implementation plan, decisions)
2. ✅ Understand current phase and next task
3. ✅ Ask clarifying questions if context is unclear
4. ✅ Follow patterns and decisions documented in decisions.md

**During session:**
1. ✅ Update progress immediately (don't batch updates)
2. ✅ Document decisions as they're made (ADR format)
3. ✅ Note any blockers in real-time
4. ✅ Commit frequently with clear messages

**Before session ends:**
1. ✅ Update all three core documents
2. ✅ Mark next task clearly
3. ✅ Ensure "Quick Status" reflects reality
4. ✅ Create git commit with session summary

**Never:**
- ❌ Skip reading planning docs at session start
- ❌ Make architectural decisions without documenting (ADR)
- ❌ Complete tasks without updating implementation plan
- ❌ End session without updating session log

---

## Customization Notes

[Add any project-specific planning instructions here]

---

**Planning Framework Version:** 1.0
**Last Updated:** YYYY-MM-DD

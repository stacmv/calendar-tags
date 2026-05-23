# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Calendar Tag Scheduler is a PHP-based information consumption management system that distributes content sources (YouTube playlists, podcasts, books, RSS feeds, Telegram channels, etc.) across daily time slots using a priority-based hybrid scheduling algorithm. It generates both visual schedules (Markdown/ASCII tables) and importable recurring calendar events (ICS format).

## File Structure

```
calendar-tags/
├── config/
│   ├── config.php          # Personal configuration (gitignored)
│   ├── config.example.php  # Configuration template
│   └── README.md          # Config documentation
├── scheduler.php          # Main application
├── calendar_tags.ics      # Generated ICS file (gitignored)
├── CLAUDE.md             # This file
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
6. Create `calendar_tags.ics` file with recurring events

**Modify the schedule**: Edit `config/config.php`:
- `tags` array: Add/modify tags with name, action, type, channel, priority
- `priorityToPeriod` mapping: Adjust frequency for each priority level
- `slots` array: Modify time slots and allowed actions
- `allowSameDayRepetition`: Set to false to prevent tag repetition in one day


## Design

Архитектура (core algorithm, files), important implementation details, known limitations, future extension points — в **[docs/DESIGN.md](docs/DESIGN.md)**.

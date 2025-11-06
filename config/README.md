# Configuration Directory

This directory contains configuration files for the Calendar Tag Scheduler.

## Setup

1. **Copy the example config:**
   ```bash
   cp config.example.php config.php
   ```

2. **Edit `config.php`** and customize:
   - **Time slots** - When you consume content during the day
   - **Priority periods** - How often each priority level repeats
   - **Tags** - Your content sources with priorities

3. **Run the scheduler:**
   ```bash
   php scheduler.php
   ```

## Files

- **`config.example.php`** - Template file (committed to git)
- **`config.php`** - Your personal config (ignored by git)
- **`README.md`** - This file

## Configuration Options

### Slots
Define your daily time slots and what actions are allowed:
- `EDU` - Study/Learning (deep focus)
- `R` - Reading (text content)
- `A` - Audio/Listening (can be passive)
- `V` - Video/Viewing (requires visual attention)

### Priority to Period Mapping
Controls how often content appears:
- Priority 5 = Every 2 days (most important)
- Priority 4 = Every 3 days
- Priority 3 = Every 4 days
- Priority 2 = Every 5 days
- Priority 1 = Every 6 days (least important)

### Tags
Each tag represents a content source:
```php
[
    "name" => "Descriptive name",
    "action" => "EDU|R|A|V",
    "type" => "Category (TECH, REL, NEWS, etc.)",
    "channel" => "Platform (YT, BOOK, TG, etc.)",
    "priority" => 1-5
]
```

## Security Note

**Never commit `config.php` to version control!** It's already in `.gitignore` to keep your personal content preferences private.

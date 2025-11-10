# Architecture Decision Log

**Project:** Calendar Tag Scheduler
**Started:** 2025-11-06
**Last Updated:** 2025-11-09

---

## Purpose

This document records significant architectural and implementation decisions made during development. Each decision includes context, alternatives considered, rationale, and consequences.

**Why document decisions?**
- Prevents revisiting settled questions
- Explains "why" to future developers (including future you)
- Provides historical context for architecture
- Helps onboard new team members
- Supports AI assistants in maintaining consistency

---

## Format

Each decision includes:
- **Date** - When decision was made
- **Status** - Current state of the decision
- **Context** - What problem are we solving?
- **Options Considered** - What alternatives did we evaluate?
- **Decision** - What did we choose?
- **Rationale** - Why did we choose this?
- **Consequences** - What are the implications?

---

## Decision Index

Quick reference to all decisions:

| ADR | Title | Status | Date |
|-----|-------|--------|------|
| [ADR-001](#adr-001-multi-user-authentication-with-htpasswd) | Multi-User Authentication with .htpasswd | Accepted | 2025-11-09 |
| [ADR-002](#adr-002-individual-ics-events-instead-of-recurring) | Individual ICS Events Instead of Recurring | Accepted | 2025-11-09 |

---

## Decisions

### ADR-001: Multi-User Authentication with .htpasswd

**Date:** 2025-11-09
**Status:** Accepted

#### Context

The Calendar Tag Scheduler web interface needed user authentication and data isolation to support multiple users.

**Background:**
- Current situation: Single-user application with config/config.php and calendar_tags.ics in root directory
- Problem: Multiple users would overwrite each other's configurations and ICS files
- Trigger: Request to add login/password protection and user-specific data storage
- Constraints: Must preserve CLI mode for existing single-user workflows

#### Options Considered

**Option 1: Database-backed authentication (MySQL/PostgreSQL)**
- Description: Full user management system with database tables for users, sessions, and configs
- Pros:
  - Rich querying capabilities
  - Easier to add features (password reset, email verification)
  - Industry standard approach
- Cons:
  - Requires database server setup
  - More complex deployment
  - Overkill for simple multi-user needs
  - External dependency

**Option 2: .htpasswd file authentication**
- Description: Use Apache-style .htpasswd file with bcrypt hashes, PHP sessions, user directories
- Pros:
  - No external dependencies (file-based)
  - Simple deployment (just copy files)
  - Compatible with existing Apache .htpasswd tools
  - Lightweight and fast
  - Easy backup (just copy .htpasswd and users/ directory)
- Cons:
  - Limited scalability (thousands of users would be slow)
  - No built-in password reset mechanism
  - Manual user management via CLI

**Option 3: Third-party authentication (OAuth, LDAP)**
- Description: Integrate with external auth providers
- Pros:
  - Centralized authentication
  - Single sign-on capabilities
- Cons:
  - External dependency
  - Complex setup
  - Network dependency
  - Overkill for self-hosted tool

#### Decision

**We chose: .htpasswd file authentication**

Use file-based authentication with .htpasswd for credentials and user-specific directories for data isolation.

#### Rationale

**Key factors:**
- **Simplicity:** Target audience is 1-10 users (personal/small team use), not enterprise scale
- **Zero dependencies:** No database server required, works anywhere PHP runs
- **Familiar tooling:** .htpasswd is well-known, tools exist (htpasswd, setup-user.php)
- **Backward compatibility:** CLI mode continues to work without authentication
- **Easy deployment:** Just copy files, no database migrations

**Trade-offs accepted:**
- Limited to ~100-1000 users before performance degrades (acceptable for use case)
- No built-in password reset UI (mitigated by CLI tool: php setup-user.php user newpass)
- Manual user provisioning (acceptable for small team scenario)

#### Consequences

**Positive:**
- Simple deployment: copy project, run setup-user.php, done
- Easy backup: just tar .htpasswd and users/ directory
- No external dependencies
- Works on any hosting (shared hosting, VPS, localhost)
- CLI mode preserved for single-user workflows

**Negative:**
- Scaling limitation (not suitable for public SaaS with 1000s of users)
- No password reset email flow (users must contact admin)
- User provisioning requires CLI access

**Neutral (implications to be aware of):**
- PHP sessions used (requires session storage, cookie support)
- User directory naming uses MD5 hash (good for privacy, but means filenames don't match usernames)

#### Implementation Notes

- Created `auth.php` class with bcrypt password hashing
- Support for legacy Apache MD5 (apr1) and plain MD5 for backward compatibility
- User directories: `users/<8-char-md5-hash>/`
- Each user gets isolated: `config.php` and `calendar_tags.ics`
- CLI tool: `php setup-user.php username password`
- Dual-mode operation: web (authenticated) vs CLI (unauthenticated)

---

### ADR-002: Individual ICS Events Instead of Recurring

**Date:** 2025-11-09
**Status:** Accepted

#### Context

The scheduler originally generated recurring ICS events using RRULE (Recurrence Rule) to represent repeated tag occurrences.

**Background:**
- Current situation: Each tag generated ONE recurring event with RRULE (e.g., FREQ=DAILY;INTERVAL=4)
- Problem: User requested individual events instead of recurring patterns
- Trigger: User feedback - wanted ICS file to contain only current month's events as individual entries
- Constraint: Must match what's shown in the schedule table output

#### Options Considered

**Option 1: Keep recurring events (RRULE)**
- Description: Maintain current approach with one event per tag using recurrence rules
- Pros:
  - Fewer events in ICS file (one per tag)
  - Compact file size
  - Represents recurring pattern conceptually
- Cons:
  - User doesn't want this approach
  - Calendar apps may show events beyond current month
  - Less clear what's scheduled on specific dates

**Option 2: Individual events for each occurrence**
- Description: Generate separate VEVENT for each scheduled tag occurrence
- Pros:
  - Exact match with schedule table
  - Clear per-date events
  - No confusion about recurrence rules
  - Monthly ICS files naturally scoped
- Cons:
  - Larger ICS files (162 events vs 23 for November)
  - More events to import

#### Decision

**We chose: Individual events for each occurrence**

Generate one VEVENT per tag occurrence, matching exactly what appears in the schedule table.

#### Rationale

**Key factors:**
- **User preference:** Explicit request from user
- **Clarity:** Each scheduled occurrence is explicitly represented
- **Monthly scope:** Since scheduler runs for current month, ICS naturally contains only that month
- **Visual consistency:** ICS matches schedule table 1:1

**Trade-offs accepted:**
- Larger ICS files (acceptable for monthly scope)
- More events to import (but calendar apps handle this fine)

#### Consequences

**Positive:**
- ICS file exactly matches what user sees in schedule table
- No ambiguity about which dates have which tags
- Natural monthly boundaries (regenerate each month)
- Easier to understand for users

**Negative:**
- ~6-7x more events in ICS file (e.g., 162 vs 23)
- Must regenerate monthly (but scheduler already runs monthly)

**Neutral:**
- File size increase is negligible (text files, ~15KB vs ~3KB)

#### Implementation Notes

- Changed `generateICS()` to iterate through `$schedule` array
- Replaced `createRecurringEvent()` with `createSingleEvent()`
- Each event gets unique UID: `tag-{id}-{date}@scheduler`
- Removed RRULE entirely
- Event summary includes slot name: "Утро: EDU-TECH-YT"

---

## Common Decision Categories

Use these examples as templates for different types of decisions:

### Technology Choice Template

```markdown
### ADR-XXX: [Technology Name] for [Purpose]

**Date:** 2025-11-06
**Status:** Accepted

#### Context
Need to choose [type of technology] for [purpose/use case].

Requirements:
- [Requirement 1]
- [Requirement 2]

#### Options Considered
1. **[Tech 1]:** [Pros/Cons]
2. **[Tech 2]:** [Pros/Cons]
3. **[Tech 3]:** [Pros/Cons]

#### Decision
Chose [Technology] because [primary reason].

#### Consequences
- Team needs to learn [new skills]
- [Integration consideration]
- [Performance impact]
```

### Architecture Pattern Template

```markdown
### ADR-XXX: [Pattern Name] Pattern

**Date:** 2025-11-06
**Status:** Accepted

#### Context
System needs to [handle some concern]. Current approach is [description of problem].

#### Options Considered
1. **[Pattern 1]:** [How it works, pros/cons]
2. **[Pattern 2]:** [How it works, pros/cons]

#### Decision
Implement [Pattern Name] pattern because [scalability/maintainability/etc.].

#### Consequences
- Code structure: [How code will be organized]
- Testing: [How this affects testing]
- Performance: [Impact on performance]
```

### Data Model Template

```markdown
### ADR-XXX: [Data Model Decision]

**Date:** 2025-11-06
**Status:** Accepted

#### Context
Need to decide how to [store/structure/relate] data for [feature].

#### Options Considered
1. **[Approach 1]:** [Schema/structure, pros/cons]
2. **[Approach 2]:** [Schema/structure, pros/cons]

#### Decision
Use [approach] because [data access patterns/query needs/etc.].

#### Consequences
- Migration: [How to migrate existing data]
- Performance: [Query performance implications]
- Flexibility: [Future schema changes]
```

---

## Status Definitions

- **Proposed:** Decision is being considered but not yet accepted
- **Accepted:** Decision is approved and being implemented
- **Superseded:** Decision has been replaced by a newer decision (reference the ADR that supersedes it)
- **Deprecated:** Decision is no longer valid but kept for historical context

---

## Best Practices

### When to Create an ADR

Create an ADR when deciding:
- ✅ Technology choices (framework, database, library)
- ✅ Architecture patterns (MVC, microservices, event-driven)
- ✅ Data models and schemas
- ✅ API design approaches
- ✅ Testing strategies
- ✅ Deployment and infrastructure
- ✅ Security approaches
- ✅ Third-party integrations

**Don't create ADRs for:**
- ❌ Trivial implementation details
- ❌ Obvious choices with no alternatives
- ❌ Temporary workarounds
- ❌ Bug fixes (unless they reveal architectural issues)

### Writing Good ADRs

**Good ADR characteristics:**
- ✅ Focuses on "why" not "how"
- ✅ Lists concrete alternatives considered
- ✅ Explains trade-offs explicitly
- ✅ Written at the time of decision (not retroactively)
- ✅ Concise but complete (1-2 pages max)

**Poor ADR characteristics:**
- ❌ Only documents the chosen option
- ❌ Lacks rationale ("because it's better")
- ❌ Too detailed about implementation
- ❌ Written long after decision was made

### Updating ADRs

- **Never edit past decisions** - They represent a point in time
- **Mark as superseded** if decision changes, then create new ADR
- **Link related ADRs** to show decision evolution
- **Add clarifications** in "Implementation Notes" if needed

---

## Template for Quick Copy-Paste

```markdown
## ADR-XXX: [Brief Title]

**Date:** 2025-11-06
**Status:** Proposed | Accepted | Superseded | Deprecated

### Context
[What problem are we solving? What constraints exist?]

### Options Considered
1. **[Option A]:** [Description, pros/cons]
2. **[Option B]:** [Description, pros/cons]
3. **[Option C]:** [Description, pros/cons]

### Decision
[What did we choose? 1-2 sentences]

### Rationale
[Why did we choose this option?]
- Reason 1
- Reason 2

### Consequences
**Positive:**
- [Benefit 1]
- [Benefit 2]

**Negative:**
- [Trade-off 1]
- [Trade-off 2]
```

---

**Log Started:** 2025-11-06
**Last Updated:** 2025-11-06

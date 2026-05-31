# TSML for Unity

A companion WordPress plugin that bridges [12 Step Meeting List (TSML)](https://wordpress.org/plugins/12-step-meeting-list/) with the [Unity](https://github.com/bleedingdeacons/unity) intergroup management framework — providing concrete implementations of every Unity interface backed by TSML data.

**Version:** 1.16.4

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Architecture](#architecture)
- [TSML Data Mapping](#tsml-data-mapping)
- [Custom Database Tables](#custom-database-tables)
- [Development](#development)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

Unity defines interfaces for groups, meetings, members, positions, locations, contacts, and intergroup meetings — but ships no concrete implementations. TSML for Unity fills that gap by implementing every interface using data from the TSML plugin's custom post types and meta fields.

Once both plugins are active the integration is automatic: TSML for Unity hooks into `unity/register_services`, registers all factories, repositories, change trackers, and view factories, then stores its field-constant configuration in Unity's `Configuration` service. No additional setup code is required.

---

## Features

**Full Unity Interface Coverage**
- `TsmlGroup` / `TsmlGroupFactory` / `TsmlGroupRepository` — groups from TSML's `tsml_group` post type
- `TsmlMeeting` / `TsmlMeetingFactory` / `TsmlMeetingRepository` — meetings from `tsml_meeting`, with day-of-week mapping and a comprehensive meeting-type code lookup
- `TsmlLocation` / `TsmlLocationFactory` / `TsmlLocationRepository` — locations from `tsml_location`, including geocoordinates and timezone
- `TsmlMember` / `TsmlMemberFactory` / `TsmlMemberRepository` — members from `intergroup-member`, with anonymous-name support and profile visibility
- `TsmlPosition` / `TsmlPositionFactory` / `TsmlPositionRepository` — positions from `intergroup-position`, with sobriety requirements and term length
- `TsmlContact` / `TsmlContactFactory` — contacts extracted from group/meeting meta fields
- `TsmlIntergroupMeeting` / `TsmlIntergroupMeetingFactory` / `TsmlIntergroupMeetingRepository` — intergroup meetings from `intergroup-meeting`
- Intergroup meeting group attendance and officer attendance (factories, repositories, and custom database tables)

**Change Tracking**
- `TsmlGroupChangeTracker` — fires `unity/group_changing` when ACF group fields are modified
- `TsmlMemberChangeTracker` — tracks member field changes
- `TsmlPositionChangeTracker` — tracks position field changes

**View Layer**
- `TsmlGroupView` / `TsmlGroupViewFactory` — group view combining meetings, contacts, and members
- `TsmlMeetingView` / `TsmlMeetingViewFactory` — meeting view with associated members
- `TsmlPositionView` / `TsmlPositionViewFactory` — position view joining position and member data with rotation dates
- `TsmlPositionViewCollection` — filterable, sortable collection with helpers for filled/vacant positions

**Field Constants**
- Every domain module includes a `Fields` class (`TsmlGroupFields`, `TsmlMeetingFields`, `TsmlLocationFields`, `TsmlMemberFields`, `TsmlPositionFields`, `TsmlIntergroupMeetingFields`) that maps ACF/meta field names to constants, stored in Unity's `Configuration` service at boot time.

---

## Requirements

- PHP 8.0 or higher (8.1+ recommended)
- WordPress 6.0 or higher
- [Unity](https://github.com/bleeding-deacons/unity) plugin — installed and activated
- [12 Step Meeting List (TSML)](https://wordpress.org/plugins/12-step-meeting-list/) plugin — installed and activated
- [Advanced Custom Fields](https://www.advancedcustomfields.com/) (recommended for member, position, and intergroup meeting fields)

---

## Installation

### Manual

1. Ensure both **Unity** and **TSML** are installed and active.
2. Download the TSML for Unity archive.
3. Extract it into `wp-content/plugins/tsml-for-unity/`.
4. Activate **TSML for Unity** in the WordPress admin under **Plugins**.

### Composer

```bash
composer require bleeding-deacons/tsml-for-unity
```

On activation the plugin creates two custom database tables for intergroup meeting attendance (see [Custom Database Tables](#custom-database-tables)).

---

## How It Works

### Boot Sequence

1. Unity loads on `plugins_loaded` (priority 10) and fires `unity/register_services`.
2. TSML for Unity listens on `unity/register_services` and calls `Plugin::registerWithUnity($container)`.
3. `registerWithUnity` checks which Unity interfaces are available (graceful degradation if a module is missing), then registers every concrete factory, repository, change tracker, and view factory into Unity's container.
4. Field constants are stored in Unity's `Configuration` service so other code can look up field names by interface class.
5. Unity resolves its core tracker services, which now resolve to the TSML implementations.
6. Unity fires `unity/loaded`; TSML for Unity confirms availability and fires `tsml_for_unity/loaded`.

### Graceful Degradation

Each domain module is registered only if its Unity interfaces exist. If Unity ships a release that adds or removes an interface, TSML for Unity will silently skip the unavailable module rather than crashing. This is controlled by availability checks such as `Plugin::unityGroupsAvailable()`, `Plugin::unityMeetingsAvailable()`, and so on.

---

## Architecture

### Directory Structure

```
tsml-for-unity/
├── tsml-for-unity.php              # Main plugin bootstrap
├── src/
│   ├── Plugin.php                  # Service registration & availability checks
│   ├── Contacts/
│   │   ├── TsmlContact.php
│   │   └── TsmlContactFactory.php
│   ├── Groups/
│   │   ├── TsmlGroup.php
│   │   ├── TsmlGroupChangeTracker.php
│   │   ├── TsmlGroupFactory.php
│   │   ├── TsmlGroupFields.php
│   │   ├── TsmlGroupRepository.php
│   │   ├── TsmlGroupView.php
│   │   └── TsmlGroupViewFactory.php
│   ├── IntergroupMeetings/
│   │   ├── TsmlIntergroupMeeting.php
│   │   ├── TsmlIntergroupMeetingFactory.php
│   │   ├── TsmlIntergroupMeetingFields.php
│   │   ├── TsmlIntergroupMeetingGroupAttendance.php
│   │   ├── TsmlIntergroupMeetingGroupAttendanceFactory.php
│   │   ├── TsmlIntergroupMeetingGroupAttendanceRepository.php
│   │   ├── TsmlIntergroupMeetingGroupAttendanceTable.php
│   │   ├── TsmlIntergroupMeetingOfficerAttendance.php
│   │   ├── TsmlIntergroupMeetingOfficerAttendanceFactory.php
│   │   ├── TsmlIntergroupMeetingOfficerAttendanceRepository.php
│   │   ├── TsmlIntergroupMeetingOfficerAttendanceTable.php
│   │   └── TsmlIntergroupMeetingRepository.php
│   ├── Locations/
│   │   ├── TsmlLocation.php
│   │   ├── TsmlLocationFactory.php
│   │   ├── TsmlLocationFields.php
│   │   └── TsmlLocationRepository.php
│   ├── Meetings/
│   │   ├── TsmlMeeting.php
│   │   ├── TsmlMeetingFactory.php
│   │   ├── TsmlMeetingFields.php
│   │   ├── TsmlMeetingRepository.php
│   │   ├── TsmlMeetingView.php
│   │   └── TsmlMeetingViewFactory.php
│   ├── Members/
│   │   ├── TsmlMember.php
│   │   ├── TsmlMemberChangeTracker.php
│   │   ├── TsmlMemberFactory.php
│   │   ├── TsmlMemberFields.php
│   │   └── TsmlMemberRepository.php
│   └── Positions/
│       ├── TsmlPosition.php
│       ├── TsmlPositionChangeTracker.php
│       ├── TsmlPositionFactory.php
│       ├── TsmlPositionFields.php
│       ├── TsmlPositionRepository.php
│       ├── TsmlPositionView.php
│       ├── TsmlPositionViewCollection.php
│       └── TsmlPositionViewFactory.php
├── tests/                          # PHPUnit test suite
├── build.php                       # Cross-platform build script
├── composer.json
└── phpunit.xml
```

### Hooks

| Hook | Timing | Purpose |
|---|---|---|
| `unity/register_services` | Listened — registers all TSML services into Unity's container | Core integration point |
| `unity/loaded` | Listened — confirms Unity is available, fires own loaded hook | Post-boot verification |
| `tsml_for_unity/loaded` | Fired — after TSML for Unity is fully initialised | Safe to consume TSML for Unity services |

---

## TSML Data Mapping

### Post Types

| Unity Domain | TSML Post Type | Fields Class |
|---|---|---|
| Group | `tsml_group` | `TsmlGroupFields` |
| Meeting | `tsml_meeting` | `TsmlMeetingFields` |
| Location | `tsml_location` | `TsmlLocationFields` |
| Member | `intergroup-member` | `TsmlMemberFields` |
| Position | `intergroup-position` | `TsmlPositionFields` |
| Intergroup Meeting | `intergroup-meeting` | `TsmlIntergroupMeetingFields` |

### Meeting Type Codes

The meeting factory includes a full lookup table mapping TSML type codes to human-readable names (e.g. `C` → Closed, `O` → Open, `D` → Discussion, `B` → Big Book, `LGBTQ` → LGBTQ, `SP` → Spanish, `TC` → Location Temporarily Closed, and many more).

### Day-of-Week Mapping

TSML uses a 0–6 numeric day index (Sunday–Saturday). The factory converts this to human-readable day names.

---

## Custom Database Tables

On activation TSML for Unity creates two custom tables for intergroup meeting attendance tracking. Schema upgrades are handled automatically via `dbDelta` on every page load when the stored version differs from the code version.

**`{prefix}_unity_ig_group_attendance_register`** (v1.2)
Tracks which groups attended each intergroup meeting, including GSR name and proxy status. Keyed with a unique constraint on `(intergroup_meeting_id, group_id)`.

**`{prefix}_unity_ig_officer_attendance_register`** (v1.1)
Tracks officer attendance at intergroup meetings.

---

## Development

### Setup

```bash
git clone <repository-url>
cd tsml-for-unity
composer install
```

### Commands

| Command | Description |
|---|---|
| `composer test` | Run the full PHPUnit test suite |
| `composer test:unit` | Run unit tests only |
| `composer test:coverage` | Generate an HTML coverage report |
| `composer stan` | Run PHPStan static analysis (level 5) |
| `composer cs` | Check WordPress coding standards |
| `composer cs:fix` | Auto-fix coding standard violations |
| `composer check` | Run CS + PHPStan + tests in sequence |

### Build

```bash
composer build:production   # Package for distribution (excludes tests/dev files)
composer build:dev          # Package with dev files included
composer build:clean        # Remove build artifacts
```

### Testing Stack

- **PHPUnit** 10 for unit tests
- **WP_Mock** 1.0 for WordPress function mocking
- **Mockery** for general mocking
- **PHPStan** (level 5) with the WordPress extension for static analysis
- **PHP_CodeSniffer** with the WordPress standard

---

## Troubleshooting

**"This plugin requires the Unity plugin to be installed and activated"**
TSML for Unity cannot find Unity's core classes. Ensure the Unity plugin is installed and activated before activating TSML for Unity.

**Services not registering / factories returning null**
Verify that both TSML and Unity are active. TSML for Unity checks for each Unity interface before registering — if an interface is missing the corresponding service is silently skipped. Check the error log for registration errors.

**Intergroup attendance tables not created**
Tables are created on plugin activation. If you activated the plugin before Unity was available, deactivate and reactivate TSML for Unity. The `maybeUpgrade` check on `plugins_loaded` will also create tables if the stored DB version is outdated.

**Change tracker not firing**
The group, member, and position change trackers hook into `acf/save_post`. Ensure ACF is active and that the post type matches the expected TSML post type (e.g. `tsml_group` for groups). Enable `WP_DEBUG` and `WP_DEBUG_LOG` to see captured events.

---

## Contributing

1. Fork the repository and create a feature branch.
2. Write tests for any new functionality.
3. Ensure `composer check` passes (coding standards, static analysis, and tests).
4. Submit a pull request with a clear description of the change.

All implementations must conform to the corresponding Unity interface. PHPStan level 5 compliance is required.

---

## License

MIT License (Modified) — Copyright © 2025 The Bleeding Deacons

You may use, modify, and distribute this software freely **except** that you may not sell it, alone or as part of an aggregate distribution. See [LICENSE](LICENSE) for full terms.

---

## Support

- **Email:** thebleedingdeacons@gmail.com
- **Issues:** Use the repository issue tracker
- **Plugin URI:** [github.com/bleeding-deacons/tsml-for-unity](https://github.com/bleeding-deacons/tsml-for-unity)
# LaraLabeler Onboarding Guide

## Overview

LaraLabeler is a **Laravel-based Bluesky labeling service** that automatically applies visual "artisan" badges to users who follow the `@laralabeler.bsky.social` account. The service operates as a **decentralized content labeler** on the Bluesky social network, using the AT Protocol to manage user labels.

**Primary Users**: Bluesky users who want to display an "artisan" badge on their profiles and posts.

**Core Functionality**:
- **Automatic Labeling**: When users follow `@laralabeler.bsky.social`, they automatically receive an "artisan" label
- **Badge Display**: The label appears as a visual badge on user profiles and all their posts
- **Label Removal**: Users can remove labels by unfollowing and submitting an appeal
- **Label Subscription**: External services can subscribe to the labeler's label stream via WebSocket

**Architecture**: Event-driven Laravel application that listens to Bluesky follow events, authenticates with the Bluesky API, and manages signed cryptographic labels stored in a local database.

## Project Organization

### Core Systems

**1. Labeling Engine** (`app/Labeler/`)
- `ArtisanLabeler.php` - Main labeler implementation handling label lifecycle
- Manages label definitions, subscriptions, creation, deletion, and verification

**2. Event Processing** (`app/Listeners/`)
- `FollowListener.php` - Responds to Bluesky follow events in real-time
- Automatically triggers label application when users follow the labeler account

**3. Database Layer** (`app/Models/`, `database/migrations/`)
- `Label.php` - Eloquent model for storing signed labels
- Migration creates `labels` table with signature verification fields
- Laravel queue jobs and batches support (`jobs` and `job_batches` tables)

**4. Command Interface** (`app/Console/Commands/`, `routes/console.php`)
- `LabelFollowerCommand.php` - Batch processes existing followers for labeling
- Console routes for manual label management and WebSocket subscription

**5. Web Interface** (`routes/web.php`, `resources/views/`)
- Simple welcome page explaining the labeling service
- Displays markdown content from configuration
- Styled with Tailwind CSS and PostCSS for responsive design

### Directory Structure

```
app/
├── Console/Commands/           # Artisan commands for manual operations
├── Http/Controllers/          # Standard Laravel controllers
├── Labeler/                   # Core labeling logic (ArtisanLabeler)
├── Listeners/                 # Event listeners (FollowListener)
├── Models/                    # Database models (Label)
└── Providers/                 # Service providers

config/
├── labeler.php               # Labeler service configuration and documentation

database/migrations/          # Database schema definitions

routes/
├── console.php              # Console commands and WebSocket subscription
└── web.php                  # Web routes (welcome page)

resources/views/             # Blade templates
tests/                       # PHPUnit test suites (Unit, Feature)
.github/workflows/           # CI/CD automation (tests, linting, updates)
```

### Key Files and Classes

**Primary Components**:
- `ArtisanLabeler::class` - Core labeler implementation extending AbstractLabeler with label management methods
- `FollowListener::handle(JetstreamCommitMessage $event)` - Event-driven label application
- `LabelFollowerCommand::handle()` - Batch follower processing
- `Label::class` - Database model with custom casts for AT Protocol data

**Configuration**:
- `config/labeler.php` - Service description and user instructions
- `config/bluesky.php` - Bluesky API and labeler configuration (bluesky.labeler.* keys)
- `composer.json` - Defines Bluesky integration dependencies
- `bootstrap/app.php` - Schedules hourly follower labeling

**External Integration**:
- `Revolution\Bluesky` package - AT Protocol client for Bluesky API
- `Revolution\AtProto` - Lexicon definitions and data types

## License

LaraLabeler is released under the **MIT License**. See the `LICENSE` file for full license text and usage terms.

## Glossary of Codebase-Specific Terms

**ArtisanLabeler** - `app/Labeler/ArtisanLabeler.php` - Core labeler class extending AbstractLabeler; manages 'artisan' label lifecycle through LabelDefinition

**FollowListener** - `app/Listeners/FollowListener.php` - Event listener responding to JetstreamCommitMessage for automatic labeling

**LabelFollowerCommand** - `app/Console/Commands/LabelFollowerCommand.php` - Artisan command: `bsky:label-follower` for batch processing

**Label** - `app/Models/Label.php` - Eloquent model storing signed labels with AtBytesObject signature casting

**LabelDefinition** - AT Protocol class defining label metadata; used in ArtisanLabeler to specify 'artisan' label properties

**LabelLocale** - AT Protocol class defining localized label text; contains language-specific name and description

**UnsignedLabel** - Label object before cryptographic signing; created by emitEvent() method

**SignedLabel** - Label object after cryptographic signing; saved via saveLabel() method

**SavedLabel** - Database-persisted label with ID; returned by saveLabel() method

**LabelerException** - Custom exception thrown by labeler operations; used for error handling

**JetstreamCommitMessage** - Bluesky event object containing follow/unfollow commit data

**atproto-accept-labelers** - HTTP header indicating trusted labeler DIDs for API requests

**RepoRef** - AT Protocol reference to user repository; created via RepoRef::to($did)

**StrongRef** - AT Protocol reference to specific record; includes URI and CID

**DID** - Decentralized Identifier; unique user/labeler identifier on Bluesky network

**CBOR** - Binary encoding format used in WebSocket label subscription streams

**AtBytesObject** - Custom Laravel cast for AT Protocol byte arrays; used for signatures

**subscribeLabels** - Method returning iterable of SubscribeLabelResponse objects with cursor pagination

**emitEvent** - Method processing moderation events; yields UnsignedLabel objects for signing

**createReport** - Method handling appeal requests; deletes labels via Bluesky API

**labeler_session** - Cached authentication session for Bluesky API; 12-hour TTL

**bluesky.labeler.did** - Configuration key storing labeler's Decentralized Identifier

**bluesky.labeler.identifier** - Configuration key for labeler's Bluesky handle/identifier for authentication

**bluesky.labeler.password** - Configuration key for labeler's app password for Bluesky API authentication

**Graph::Follow** - AT Protocol lexicon enum for follow collection type

**LegacySession** - Session wrapper for Bluesky API authentication token management

**bsky:ws** - Console command for WebSocket subscription to label streams

**bsky:label** - Console command for manual label creation on user profiles

**artisan-badge** - Visual badge identifier; the specific label value applied to users

**Labeler::log** - Logging facade for labeler operations; provides audit trail

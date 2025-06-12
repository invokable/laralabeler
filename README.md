LaraLabeler
====

- https://bsky.app/profile/laralabeler.bsky.social
- https://github.com/invokable/laravel-bluesky/blob/main/docs/labeler.md

LaraLabeler is a Laravel-based Bluesky labeling service that automatically applies visual "artisan" badges to users who follow the @laralabeler.bsky.social account. The service operates as a decentralized content labeler on the Bluesky social network, using the AT Protocol to manage user labels.

**Primary Users**: Bluesky users who want to display an "artisan" badge on their profiles and posts.

**Core Functionality**:
- **Automatic Labeling**: When users follow @laralabeler.bsky.social, they automatically receive an "artisan" label
- **Badge Display**: The label appears as a visual badge on user profiles and all their posts
- **Label Removal**: Users can remove labels by unfollowing and submitting an appeal
- **Label Subscription**: External services can subscribe to the labeler's label stream via WebSocket

**Architecture**: Event-driven Laravel application that listens to Bluesky follow events, authenticates with the Bluesky API, and manages signed cryptographic labels stored in a local database.

**Project Structure**:
- `app/Labeler/`: Core labeler logic (ArtisanLabeler)
- `app/Listeners/`: Bluesky follow event processing (FollowListener)
- `app/Models/`: DB models (Label)
- `app/Console/Commands/`: Batch and CLI commands (LabelFollowerCommand)
- `database/migrations/`: Database schema
- `config/labeler.php`: Service config
- `routes/console.php`, `routes/web.php`: Console and web endpoints
- `resources/views/`: Blade templates
- `.github/workflows/`: CI/CD


## LICENCE
MIT

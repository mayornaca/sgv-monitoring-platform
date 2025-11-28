# SOS Sensor Monitoring Specification

## ADDED Requirements

### Requirement: Real-Time Push Notifications
The system SHALL deliver real-time push notifications to registered users when SOS sensor alarms are triggered by external monitoring service.

#### Scenario: External service triggers alarm notification
- **GIVEN** an external hardware monitoring service detects a SOS sensor door opening
- **AND** the service inserts alarm record into `tbl_cot_09_alarmas_sensores_dispositivos` with `created_by=0`
- **WHEN** the service calls `POST /api/send_push_notification` with Bearer token authentication
- **THEN** the system SHALL publish notification via both Mercure SSE and Firebase FCM
- **AND** all registered browser clients SHALL receive the notification within 500ms
- **AND** the response SHALL include delivery count: `{"sent": {"mercure": true, "fcm": 15}}`

#### Scenario: Foreground notification delivery
- **GIVEN** a user has the SOS monitoring page open in browser
- **AND** the user has granted notification permissions
- **WHEN** an alarm notification is published
- **THEN** the browser SHALL receive the message via Mercure SSE EventSource
- **AND** the `renderDevicesAlarms()` function SHALL display a modal with alarm details
- **AND** the notification latency SHALL be less than 100ms from publish to display
- **AND** the document title SHALL update with alarm count
- **AND** the favicon SHALL change to alert icon

#### Scenario: Background notification delivery
- **GIVEN** a user has registered for push notifications
- **AND** the browser is minimized or tab is not active
- **WHEN** an alarm notification is published
- **THEN** the service worker SHALL receive the FCM push message
- **AND** a browser notification SHALL be displayed with title and body
- **AND** the notification SHALL have actions: "Ver Detalles" and "Cerrar"
- **AND** the notification SHALL require user interaction (requireInteraction: true)

#### Scenario: Notification action handling
- **GIVEN** a user receives a background notification
- **WHEN** the user clicks "Ver Detalles" action
- **THEN** the browser SHALL focus existing SOS monitoring window if open
- **OR** the browser SHALL open new window at SOS monitoring URL
- **AND** the window SHALL receive alarm data via postMessage
- **AND** the alarm modal SHALL display automatically

#### Scenario: Multi-window synchronization
- **GIVEN** a user has multiple browser windows/tabs open
- **WHEN** an alarm notification is received by service worker
- **THEN** all matching windows SHALL receive postMessage with alarm data
- **AND** all windows SHALL display the alarm modal simultaneously

### Requirement: Firebase Cloud Messaging Integration
The system SHALL integrate Firebase Cloud Messaging v11 with modular API for push notification delivery.

#### Scenario: Firebase initialization
- **GIVEN** a user visits the SOS monitoring page
- **WHEN** the page loads
- **THEN** the Firebase SDK SHALL initialize with project configuration
- **AND** the messaging instance SHALL be created
- **AND** notification permission SHALL be requested from user

#### Scenario: FCM token registration
- **GIVEN** the user grants notification permission
- **WHEN** Firebase `getToken()` is called with VAPID key
- **THEN** a unique FCM registration token SHALL be generated
- **AND** the token SHALL be sent to `POST /api/register-token` endpoint
- **AND** the token SHALL be stored in `tbl_00_users_tokens` table
- **AND** the token SHALL be associated with current user ID
- **AND** the `reg_status` SHALL be set to 1 (active)

#### Scenario: FCM token refresh
- **GIVEN** a user's FCM token becomes invalid or expired
- **WHEN** Firebase SDK detects token refresh
- **THEN** a new token SHALL be automatically obtained
- **AND** the new token SHALL replace the old token in database
- **AND** the old token SHALL be marked as inactive

### Requirement: Mercure SSE Real-Time Communication
The system SHALL use Mercure protocol with Server-Sent Events for sub-second notification delivery to active browser clients.

#### Scenario: Mercure Hub availability
- **GIVEN** the Mercure Hub service is running on internal port 9090
- **WHEN** Nginx receives request to `/.well-known/mercure`
- **THEN** the request SHALL be proxied to `http://127.0.0.1:9090/.well-known/mercure`
- **AND** the connection SHALL remain open for SSE streaming
- **AND** the Hub SHALL respond with 200 OK and `Content-Type: text/event-stream`

#### Scenario: EventSource subscription
- **GIVEN** a user has the SOS monitoring page open
- **WHEN** the page JavaScript executes
- **THEN** an EventSource connection SHALL be established to `/\.well-known/mercure?topic=https://vs.gvops.cl/alarms/sos`
- **AND** the connection SHALL remain persistent
- **AND** reconnection SHALL occur automatically on disconnect (SSE built-in)

#### Scenario: Mercure message publication
- **GIVEN** the API controller receives an alarm notification request
- **WHEN** processing the notification
- **THEN** a Mercure Update SHALL be created with topic `https://vs.gvops.cl/alarms/sos`
- **AND** the Update SHALL contain JSON payload with alarm data
- **AND** the Hub SHALL publish to all subscribed clients
- **AND** publication SHALL succeed within 50ms

#### Scenario: Mercure Hub failure fallback
- **GIVEN** the Mercure Hub service is down or unreachable
- **WHEN** the API controller attempts to publish a notification
- **THEN** the Mercure publish SHALL fail gracefully without throwing exception
- **AND** Firebase FCM SHALL still send notifications to all registered tokens
- **AND** the response SHALL indicate Mercure failure: `{"sent": {"mercure": false, "fcm": 15}}`
- **AND** operators SHALL still receive notifications via Firebase

### Requirement: Service Worker with Modern Lifecycle
The system SHALL implement a modern service worker with automatic updates and optimized caching strategies.

#### Scenario: Service worker installation
- **GIVEN** a user visits the application for the first time
- **WHEN** the browser downloads `firebase-messaging-sw.js`
- **THEN** the service worker SHALL install immediately via `skipWaiting()`
- **AND** Firebase messaging instance SHALL be initialized
- **AND** the install event SHALL complete successfully

#### Scenario: Service worker activation
- **GIVEN** a new service worker version is installed
- **WHEN** the activation event fires
- **THEN** all cache entries matching prefix `vs-gvops-` but not matching current VERSION SHALL be deleted
- **AND** the service worker SHALL take control via `clients.claim()`
- **AND** all open clients SHALL be controlled without requiring page reload

#### Scenario: Service worker update detection
- **GIVEN** the service worker VERSION constant is updated
- **WHEN** a user visits the application
- **THEN** the browser SHALL detect the new service worker
- **AND** the old service worker SHALL be replaced immediately
- **AND** old caches SHALL be cleaned up automatically
- **AND** the user SHALL receive notifications from new service worker

### Requirement: Stratified Cache Management
The system SHALL maintain 8 separate cache categories for optimal resource management and selective invalidation.

#### Scenario: Font caching
- **GIVEN** a request for Google Fonts or local font files
- **WHEN** the service worker fetch handler intercepts the request
- **THEN** the resource SHALL be cached in `vs-gvops-{VERSION}-fonts` cache
- **AND** cacheFirst strategy SHALL be applied
- **AND** fonts SHALL be served from cache on subsequent requests

#### Scenario: Image caching
- **GIVEN** a request for PNG, JPG, SVG, or other image formats
- **WHEN** the service worker intercepts the request
- **THEN** the resource SHALL be cached in `vs-gvops-{VERSION}-images` cache
- **AND** cacheFirst strategy SHALL be applied

#### Scenario: Asset caching
- **GIVEN** a request for CSS or JavaScript files
- **WHEN** the service worker intercepts the request
- **THEN** the resource SHALL be cached in `vs-gvops-{VERSION}-assets` cache
- **AND** cacheFirst strategy SHALL be applied

#### Scenario: Map tiles caching
- **GIVEN** a request for OpenStreetMap or Mapbox tiles
- **WHEN** the service worker intercepts the request
- **THEN** the resource SHALL be cached in `vs-gvops-{VERSION}-map-tiles` cache
- **AND** cacheFirst strategy SHALL be applied
- **AND** tiles SHALL persist for long-term use

#### Scenario: Dynamic API no-cache
- **GIVEN** a request to `/api/`, `/status`, `/siv/`, or `/cot/sos*` paths
- **WHEN** the service worker intercepts the request
- **THEN** the request SHALL bypass cache completely
- **AND** networkOnly strategy SHALL be applied
- **AND** fresh data SHALL always be fetched from server

#### Scenario: Gravatar stale-while-revalidate
- **GIVEN** a request to Gravatar CDN
- **WHEN** the service worker intercepts the request
- **THEN** cached response SHALL be returned immediately if available
- **AND** background fetch SHALL update the cache for next request
- **AND** staleWhileRevalidate strategy SHALL be applied

#### Scenario: Selective cache invalidation
- **GIVEN** a deployment updates CSS/JS assets but not images
- **WHEN** the VERSION constant is incremented
- **THEN** `vs-gvops-{OLD_VERSION}-assets` cache SHALL be deleted
- **AND** `vs-gvops-{OLD_VERSION}-fonts` cache SHALL be deleted
- **AND** `vs-gvops-{OLD_VERSION}-images` cache SHALL be deleted
- **AND** all other old version caches SHALL be deleted
- **BUT** the new version caches SHALL remain empty until first requests

### Requirement: API Endpoint for External Service
The system SHALL provide authenticated REST API endpoint for external hardware monitoring service to trigger notifications.

#### Scenario: Valid notification request
- **GIVEN** the external service detects a SOS sensor alarm
- **WHEN** the service sends `POST /api/send_push_notification` with valid Bearer token
- **AND** the request body contains `{"type": "alarms_sos_sensor", "alarms_ids": [123, 456]}`
- **THEN** the system SHALL validate Bearer token matches `EXTERNAL_SERVICE_TOKEN`
- **AND** the system SHALL query alarm details for provided IDs from PostgreSQL
- **AND** notification payload SHALL be constructed with alarm details
- **AND** notification SHALL be published via Mercure and FCM
- **AND** response SHALL be 200 OK with `{"status": "sent", "recipients": {"mercure": true, "fcm": 15}}`

#### Scenario: Invalid authentication
- **GIVEN** an external service request
- **WHEN** the Authorization header is missing or incorrect
- **THEN** the system SHALL respond with 401 Unauthorized
- **AND** the response SHALL contain `{"error": "Unauthorized"}`
- **AND** no notifications SHALL be sent

#### Scenario: Invalid notification type
- **GIVEN** a valid authenticated request
- **WHEN** the request body contains unsupported type
- **THEN** the system SHALL respond with 400 Bad Request
- **AND** the response SHALL contain `{"error": "Invalid type"}`

#### Scenario: Service heartbeat registration
- **GIVEN** the external monitoring service is running
- **WHEN** the service sends `POST /api/register_service` with service status
- **THEN** the system SHALL update `servicios` table with service ID and status
- **AND** the `updated_at` timestamp SHALL be set to current time
- **AND** response SHALL be 200 OK

### Requirement: FCM Service for Cloud Messaging
The system SHALL implement FCM service with OAuth 2.0 authentication and batch notification sending.

#### Scenario: FCM access token acquisition
- **GIVEN** the FCM service needs to send a notification
- **WHEN** `getAccessToken()` is called
- **THEN** a JWT SHALL be created with service account credentials
- **AND** the JWT SHALL be signed with RSA-256 using OpenSSL
- **AND** the JWT SHALL be exchanged for OAuth 2.0 access token at Google token endpoint
- **AND** the access token SHALL be cached for reuse within expiry period

#### Scenario: Batch notification sending
- **GIVEN** there are 150 registered FCM tokens in database
- **WHEN** `sendToAll()` is called with notification payload
- **THEN** tokens SHALL be split into chunks of 100
- **AND** each chunk SHALL be processed sequentially
- **AND** notifications SHALL be sent to FCM API v1 endpoint for each token
- **AND** successful sends SHALL be counted
- **AND** the total count SHALL be returned

#### Scenario: FCM token expiry handling
- **GIVEN** a token has been invalidated by Google
- **WHEN** FCM send fails with token error response
- **THEN** the token SHALL be marked as `reg_status=0` in database
- **AND** future sends SHALL skip this token
- **AND** the error SHALL be logged for monitoring

### Requirement: Supervisor Service Management
The system SHALL use Supervisor to maintain Mercure Hub as a persistent background service with automatic restart.

#### Scenario: Mercure Hub startup
- **GIVEN** Supervisor is configured with mercure.conf
- **WHEN** `supervisorctl start mercure` is executed
- **THEN** the Mercure binary SHALL start on port 9090
- **AND** the process SHALL run as user `www`
- **AND** the status SHALL show RUNNING
- **AND** logs SHALL be written to `/var/log/mercure.log`

#### Scenario: Automatic restart on failure
- **GIVEN** the Mercure Hub process crashes or is killed
- **WHEN** Supervisor detects the process exit
- **THEN** Supervisor SHALL automatically restart the process within 5 seconds
- **AND** the restart SHALL be logged
- **AND** SSE clients SHALL reconnect automatically

#### Scenario: Manual service control
- **GIVEN** an administrator needs to restart Mercure
- **WHEN** `supervisorctl restart mercure` is executed
- **THEN** the process SHALL stop gracefully
- **AND** the process SHALL start again
- **AND** open SSE connections SHALL be closed
- **AND** clients SHALL reconnect within 5 seconds

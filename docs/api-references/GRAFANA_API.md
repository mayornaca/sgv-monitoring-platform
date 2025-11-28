# Grafana HTTP API - Referencia Oficial

**Fuente**: https://grafana.com/docs/grafana/latest/developers/http_api/
**Fecha**: 2025-11-06

## Autenticación

### 1. Basic Auth (Default)
```bash
curl http://admin:admin@localhost:3000/api/org
```

### 2. Service Account Tokens (Recomendado para API)
```http
Authorization: Bearer eyJrIjoiT0tTcG1pUlY2RnVKZTFVaDFsNFZXdE9ZWmNrMkZYbk
```

### 3. Session Cookies
Via login o OAuth

### 4. Header Multi-Org
```http
X-Grafana-Org-Id: 2
```
Especifica organización target (excepto operaciones admin)

## Endpoints Principales

### Dashboards API

#### Get Dashboard by UID
```http
GET /api/dashboards/uid/:uid
```

#### Create/Update Dashboard
```http
POST /api/dashboards/db
```
**Body**:
```json
{
  "dashboard": {
    "title": "Dashboard Title",
    "tags": ["monitoring"],
    "timezone": "browser",
    "panels": [...],
    "schemaVersion": 16,
    "version": 0
  },
  "folderId": 0,
  "folderUid": "folder-uid",
  "message": "Commit message",
  "overwrite": false
}
```

#### Delete Dashboard
```http
DELETE /api/dashboards/uid/:uid
```

#### Search Dashboards
```http
GET /api/search
```
**Query Params:**
- `query`: Search query
- `tag`: Filter by tags
- `type`: `dash-db` or `dash-folder`
- `dashboardIds`: Filter by IDs
- `folderIds`: Filter by folder
- `starred`: Boolean
- `limit`: Max results

### Data Sources API

#### Get All Data Sources
```http
GET /api/datasources
```

#### Get Data Source by ID
```http
GET /api/datasources/:id
```

#### Get Data Source by UID
```http
GET /api/datasources/uid/:uid
```

#### Get Data Source by Name
```http
GET /api/datasources/name/:name
```

#### Create Data Source
```http
POST /api/datasources
```
**Body**:
```json
{
  "name": "Prometheus",
  "type": "prometheus",
  "url": "http://prometheus:9090",
  "access": "proxy",
  "basicAuth": false,
  "isDefault": false,
  "jsonData": {
    "timeInterval": "30s",
    "queryTimeout": "60s"
  }
}
```

#### Update Data Source
```http
PUT /api/datasources/:id
```

#### Delete Data Source
```http
DELETE /api/datasources/:id
```

#### Test Data Source
```http
POST /api/datasources/proxy/:id/_datasource/test
```

### Alerting API

#### Get Alert Rules
```http
GET /api/v1/provisioning/alert-rules
```

#### Get Alert Rule by UID
```http
GET /api/v1/provisioning/alert-rules/:uid
```

#### Create Alert Rule
```http
POST /api/v1/provisioning/alert-rules
```

#### Update Alert Rule
```http
PUT /api/v1/provisioning/alert-rules/:uid
```

#### Delete Alert Rule
```http
DELETE /api/v1/provisioning/alert-rules/:uid
```

#### Get Contact Points
```http
GET /api/v1/provisioning/contact-points
```

#### Get Notification Policies
```http
GET /api/v1/provisioning/policies
```

### Folders API

#### Get All Folders
```http
GET /api/folders
```

#### Get Folder by UID
```http
GET /api/folders/:uid
```

#### Create Folder
```http
POST /api/folders
```
**Body**:
```json
{
  "uid": "folder-uid",
  "title": "Folder Title"
}
```

#### Update Folder
```http
PUT /api/folders/:uid
```

#### Delete Folder
```http
DELETE /api/folders/:uid
```

### Organizations API

#### Get Current Org
```http
GET /api/org
```

#### Get All Orgs (Admin)
```http
GET /api/orgs
```

#### Create Org (Admin)
```http
POST /api/orgs
```

#### Switch User Context
```http
POST /api/user/using/:orgId
```

### Users API

#### Get Current User
```http
GET /api/user
```

#### Get All Users (Admin)
```http
GET /api/users
```

#### Get User by ID (Admin)
```http
GET /api/users/:id
```

#### Get User by Email/Login (Admin)
```http
GET /api/users/lookup?loginOrEmail=user@example.com
```

### Service Accounts API

#### Get All Service Accounts
```http
GET /api/serviceaccounts
```

#### Create Service Account
```http
POST /api/serviceaccounts
```

#### Create Token for Service Account
```http
POST /api/serviceaccounts/:id/tokens
```

### Admin API

#### Get Server Settings
```http
GET /api/admin/settings
```

#### Get Server Stats
```http
GET /api/admin/stats
```

#### Pause/Resume All Alerts
```http
POST /api/admin/pause-all-alerts
```

### Health Check
```http
GET /api/health
```

## Provisioning

Grafana soporta provisioning via archivos YAML:

### Datasources
```yaml
# /etc/grafana/provisioning/datasources/prometheus.yml
apiVersion: 1
datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://prometheus:9090
    isDefault: true
```

### Dashboards
```yaml
# /etc/grafana/provisioning/dashboards/default.yml
apiVersion: 1
providers:
  - name: 'default'
    folder: 'General'
    type: file
    options:
      path: /var/lib/grafana/dashboards
```

## Uso en API Wrapper Symfony

```php
// Ejemplo de wrapper
public function getDashboards(Request $request): JsonResponse
{
    $query = $request->get('query', '');
    $tags = $request->get('tags', []);

    $response = $this->httpClient->request('GET',
        $this->grafanaUrl . '/api/search', [
        'auth_bearer' => $this->grafanaToken,
        'query' => [
            'query' => $query,
            'tag' => $tags,
            'type' => 'dash-db'
        ]
    ]);

    return new JsonResponse($response->toArray());
}

public function createDashboard(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    $response = $this->httpClient->request('POST',
        $this->grafanaUrl . '/api/dashboards/db', [
        'auth_bearer' => $this->grafanaToken,
        'json' => [
            'dashboard' => $data['dashboard'],
            'folderId' => $data['folderId'] ?? 0,
            'message' => 'Created via API wrapper',
            'overwrite' => false
        ]
    ]);

    return new JsonResponse($response->toArray(), $response->getStatusCode());
}
```

## Swagger UI

Grafana expone su API spec en:
```
http://localhost:3000/swagger-ui
```

OpenAPI spec disponible en:
```
http://localhost:3000/api/swagger.json
```
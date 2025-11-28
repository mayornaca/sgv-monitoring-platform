# Prometheus HTTP API - Referencia Oficial

**Fuente**: https://prometheus.io/docs/prometheus/latest/querying/api/
**Fecha**: 2025-11-06

## Endpoints Principales

### 1. Query API

#### Instant Query
```http
GET/POST /api/v1/query
```
**Parámetros:**
- `query` (required): PromQL expression
- `time` (optional): Evaluation timestamp
- `timeout` (optional): Query timeout
- `limit` (optional): Max series returned

**Response Format:**
```json
{
  "status": "success",
  "data": {
    "resultType": "vector|matrix|scalar|string",
    "result": [...]
  }
}
```

#### Range Query
```http
GET/POST /api/v1/query_range
```
**Parámetros:**
- `query` (required): PromQL expression
- `start` (required): Start timestamp
- `end` (required): End timestamp
- `step` (required): Resolution step

### 2. Metadata API

#### Series Discovery
```http
GET/POST /api/v1/series
```
**Parámetros:**
- `match[]` (required, repeatable): Label selectors
- `start`, `end` (optional): Time range
- `limit` (optional): Max results

#### Label Names
```http
GET /api/v1/labels
```

#### Label Values
```http
GET /api/v1/label/<label_name>/values
```

### 3. Targets & Configuration

#### Active Targets
```http
GET /api/v1/targets
```
**Query Params:**
- `state`: `active`, `dropped`, `any`
- `scrapePool`: Filter by pool name

#### Current Config
```http
GET /api/v1/status/config
```
Returns YAML configuration.

#### Runtime Info
```http
GET /api/v1/status/runtimeinfo
```
Returns start time, goroutines, retention, etc.

#### TSDB Stats
```http
GET /api/v1/status/tsdb
```
Returns cardinality statistics.

### 4. Rules & Alerts

#### Alert Rules
```http
GET /api/v1/rules
```
**Query Params:**
- `type`: `alert` or `record`
- `rule_name[]`, `rule_group[]`, `file[]`: Filters
- `exclude_alerts`: Omit alert details

#### Active Alerts
```http
GET /api/v1/alerts
```

#### Alertmanagers
```http
GET /api/v1/alertmanagers
```

### 5. Admin API (require --web.enable-admin-api)

#### Create Snapshot
```http
POST /api/v1/admin/tsdb/snapshot
```

#### Delete Series
```http
POST /api/v1/admin/tsdb/delete_series
```
**Params:**
- `match[]`: Series selectors
- `start`, `end`: Time range

#### Clean Tombstones
```http
POST /api/v1/admin/tsdb/clean_tombstones
```

### 6. Data Ingestion

#### Remote Write Receiver
```http
POST /api/v1/write
```
Requires `--web.enable-remote-write-receiver`

#### OTLP Receiver
```http
POST /api/v1/otlp/v1/metrics
```
Requires `--web.enable-otlp-receiver`

## Response Format

**HTTP Status Codes:**
- `2xx`: Success
- `400`: Bad Request
- `422`: Unprocessable Entity
- `503`: Service Unavailable

**Timestamps:** RFC3339 or Unix format

**Special Floats:** NaN, Inf, -Inf as quoted strings

## Uso en API Wrapper Symfony

```php
// Ejemplo de wrapper
public function query(Request $request): JsonResponse
{
    $query = $request->get('query');
    $time = $request->get('time');

    $response = $this->httpClient->request('GET',
        $this->prometheusUrl . '/api/v1/query', [
        'query' => [
            'query' => $query,
            'time' => $time
        ]
    ]);

    return new JsonResponse($response->toArray());
}
```
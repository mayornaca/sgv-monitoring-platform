# Alertmanager Management API - Referencia Oficial

**Fuente**: https://prometheus.io/docs/alerting/latest/management_api/
**Fecha**: 2025-11-06

## Management Endpoints

### 1. Health Check
```http
GET /-/healthy
HEAD /-/healthy
```
**Propósito**: Verificar salud de Alertmanager
**Response**: Siempre HTTP 200

### 2. Readiness Check
```http
GET /-/ready
HEAD /-/ready
```
**Propósito**: Verificar si está listo para servir tráfico
**Response**: HTTP 200 cuando está ready

### 3. Configuration Reload
```http
POST /-/reload
```
**Propósito**: Recargar configuración sin reiniciar
**Alternativa**: Enviar señal `SIGHUP` al proceso

## Alertmanager API v2 (Completa)

**Nota**: La documentación oficial de management API es limitada. La API completa v2 incluye:

### Alerts API

#### Get All Alerts
```http
GET /api/v2/alerts
```
**Query Params:**
- `filter`: Matcher filter (e.g., `alertname="HighCPU"`)
- `active`: Boolean, only active alerts
- `silenced`: Boolean, only silenced alerts
- `inhibited`: Boolean, only inhibited alerts

#### Post Alerts
```http
POST /api/v2/alerts
```
**Body**: Array de objetos alert

### Silences API

#### Get All Silences
```http
GET /api/v2/silences
```
**Query Params:**
- `filter`: Matcher expressions

#### Get Silence by ID
```http
GET /api/v2/silence/{id}
```

#### Create Silence
```http
POST /api/v2/silences
```
**Body**:
```json
{
  "matchers": [
    {
      "name": "alertname",
      "value": "HighCPU",
      "isRegex": false,
      "isEqual": true
    }
  ],
  "startsAt": "2025-11-06T10:00:00Z",
  "endsAt": "2025-11-06T12:00:00Z",
  "createdBy": "admin",
  "comment": "Maintenance window"
}
```

#### Delete Silence
```http
DELETE /api/v2/silence/{id}
```

### Receivers API

#### Get All Receivers
```http
GET /api/v2/receivers
```

### Alert Groups API

#### Get Alert Groups
```http
GET /api/v2/alerts/groups
```
**Query Params:**
- `filter`: Matcher filter
- `active`, `silenced`, `inhibited`: Booleans

### Status API

#### Get Status
```http
GET /api/v2/status
```
Returns: cluster status, config, version info

## Uso en API Wrapper Symfony

```php
// Ejemplo de wrapper para silences
public function createSilence(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    $response = $this->httpClient->request('POST',
        $this->alertmanagerUrl . '/api/v2/silences', [
        'json' => [
            'matchers' => $data['matchers'],
            'startsAt' => $data['startsAt'],
            'endsAt' => $data['endsAt'],
            'createdBy' => $this->getUser()->getEmail(),
            'comment' => $data['comment']
        ]
    ]);

    return new JsonResponse($response->toArray(), $response->getStatusCode());
}
```

## Autenticación

Alertmanager NO tiene autenticación built-in. Debe protegerse con:
- Reverse proxy con Basic Auth
- OAuth2 Proxy
- mTLS
- Firewall rules
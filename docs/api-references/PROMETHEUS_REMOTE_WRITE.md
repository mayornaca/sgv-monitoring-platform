# Prometheus Remote Write - Referencia Oficial

**Fuente**: https://prometheus.io/docs/practices/remote_write/
**Fecha**: 2025-11-06

## Concepto

Remote write permite enviar métricas de Prometheus a sistemas de almacenamiento externos en tiempo real.

**Diferencia con Federation**:
- **Remote Write**: Push activo, streaming, baja latencia
- **Federation**: Pull pasivo, scrape periódico, mayor latencia

## Arquitectura

```
┌──────────────────────────────────┐
│ Prometheus (Local)               │
│  ├─ Scrape Targets               │
│  ├─ WAL (Write-Ahead Log)        │
│  └─ Remote Write Queue           │
│        ├─ Shard 1 ──────┐        │
│        ├─ Shard 2 ──────┤        │
│        └─ Shard N ──────┤        │
└────────────────────────┬─────────┘
                         ↓ HTTP/2
┌──────────────────────────────────┐
│ Remote Storage (Producción)      │
│  ├─ Thanos Receiver              │
│  ├─ Cortex                       │
│  ├─ Mimir                        │
│  ├─ VictoriaMetrics              │
│  └─ Cloud Providers (AWS, GCP)   │
└──────────────────────────────────┘
```

## Configuración Básica

```yaml
# prometheus.yml
remote_write:
  - url: "https://prometheus-prod.example.com/api/v1/write"

    # Autenticación
    basic_auth:
      username: "prometheus"
      password: "${REMOTE_WRITE_PASSWORD}"

    # O con bearer token
    # authorization:
    #   credentials: "${REMOTE_WRITE_TOKEN}"

    # Headers custom
    headers:
      X-Environment: "development"
      X-Region: "us-east-1"

    # Timeout
    remote_timeout: 30s

    # Queue config (ver sección tuning)
    queue_config:
      capacity: 10000
      max_shards: 200
      min_shards: 1
      max_samples_per_send: 2000
      batch_send_deadline: 5s
      min_backoff: 30ms
      max_backoff: 5s
```

## Impacto en Recursos

### Memoria

**Incremento típico**: ~25% adicional

**Cálculo**:
```
Memoria adicional ≈ num_shards × (capacity + max_samples_per_send) × tamaño_sample
```

**Ejemplo**:
```
Shards: 10
Capacity: 10000
Max samples per send: 2000
Tamaño sample: ~100 bytes

Memoria ≈ 10 × (10000 + 2000) × 100 bytes ≈ 12 MB
```

**Métrica a monitorear**:
```promql
prometheus_remote_storage_samples_pending
```
Si esta métrica crece continuamente = saturación.

### CPU y Red

Difícil de predecir. Depende de:
- Volumen de métricas
- Latencia de red al endpoint remoto
- Compresión (Snappy por defecto)

## Parámetros de Tuning

### queue_config

#### capacity
**Default**: 2500
**Producción recomendado**: 10000
**Fórmula**: 3-10× `max_samples_per_send`

Tamaño de la cola en memoria por shard.

```yaml
queue_config:
  capacity: 10000  # 5× max_samples_per_send
```

#### max_samples_per_send
**Default**: 500
**Producción recomendado**: 2000

Tamaño del batch por request HTTP.

**Consideraciones**:
- Muy pequeño: Muchas requests pequeñas, overhead HTTP
- Muy grande: Requests lentas, timeouts

```yaml
queue_config:
  max_samples_per_send: 2000
```

#### batch_send_deadline
**Default**: 5s
**Uso**: Sistemas con bajo volumen

Tiempo máximo de espera antes de enviar batch incompleto.

```yaml
queue_config:
  batch_send_deadline: 5s
```

#### max_shards
**Default**: 200
**Raramente necesita cambio**

Número máximo de shards (paralelismo).

**Auto-tuning**: Prometheus ajusta dinámicamente entre `min_shards` y `max_shards`.

```yaml
queue_config:
  max_shards: 200
```

#### min_shards
**Default**: 1
**Producción recomendado**: 5-10

Número inicial de shards. Ayuda a evitar lag inicial.

```yaml
queue_config:
  min_shards: 5
```

#### min_backoff / max_backoff
**Defaults**: 30ms / 5s

Tiempos de espera entre reintentos cuando el endpoint falla.

```yaml
queue_config:
  min_backoff: 30ms
  max_backoff: 5s
```

## Filtrado de Métricas

### write_relabel_configs

Enviar solo métricas específicas:

```yaml
remote_write:
  - url: "https://remote-storage.example.com/api/v1/write"
    write_relabel_configs:
      # Solo métricas críticas
      - source_labels: [__name__]
        regex: '(up|http_requests_total|node_cpu_seconds_total)'
        action: keep

      # Excluir métricas de debug
      - source_labels: [__name__]
        regex: 'debug_.*'
        action: drop

      # Solo de jobs específicos
      - source_labels: [job]
        regex: '(api-server|database)'
        action: keep

      # Agregar labels
      - target_label: environment
        replacement: 'development'
```

### Ejemplo: Solo Alertas Críticas

```yaml
remote_write:
  - url: "https://remote-storage.example.com/api/v1/write"
    write_relabel_configs:
      - source_labels: [__name__]
        regex: 'ALERTS.*'
        action: keep
      - source_labels: [severity]
        regex: 'critical|warning'
        action: keep
```

## Configuración Producción Típica

```yaml
remote_write:
  - url: "https://prometheus-prod.example.com/api/v1/write"

    basic_auth:
      username: "dev-cluster"
      password_file: /etc/prometheus/remote_write_password

    remote_timeout: 30s

    queue_config:
      capacity: 10000            # 5× max_samples_per_send
      max_samples_per_send: 2000
      batch_send_deadline: 5s
      max_shards: 200
      min_shards: 5              # Evitar lag inicial
      min_backoff: 30ms
      max_backoff: 5s

    write_relabel_configs:
      # Solo métricas de producción
      - source_labels: [__name__]
        regex: '(up|http_.*|grpc_.*|node_.*|container_.*)'
        action: keep

      # Agregar identificador de cluster
      - target_label: cluster
        replacement: 'development'
```

## Remote Read (Opcional)

Permite que Grafana/Queries locales lean datos históricos del storage remoto.

```yaml
remote_read:
  - url: "https://prometheus-prod.example.com/api/v1/read"

    basic_auth:
      username: "prometheus"
      password_file: /etc/prometheus/remote_read_password

    read_recent: true  # Lee datos recientes del remoto

    # Filtro de queries
    required_matchers:
      job: "api-server"  # Solo queries para este job van al remoto
```

## Pérdida de Datos

**CRÍTICO**: Datos se pierden después de **2 horas** de fallo continuo del endpoint remoto.

**Razón**: WAL (Write-Ahead Log) tiene retención limitada.

**Mitigación**:
1. Monitorear endpoint remoto
2. Alertar en fallos > 30 minutos
3. Considerar Thanos Receiver con replicación

**Métrica**:
```promql
prometheus_remote_storage_samples_failed_total
```

## Remote Write 2.0 (Experimental - 2025)

**Estado**: Experimental pero ampliamente usado

**Mejoras**:
- Soporta Native Histograms
- Mejor compresión
- Fallback automático a 1.0

**Activar**:
```bash
prometheus --enable-feature=native-histograms
```

## Backends Compatibles

### Open Source
- **Thanos Receiver**: HA, long-term storage S3/GCS
- **Cortex**: Multi-tenant, horizontal scaling
- **Mimir**: Fork de Cortex, mejor performance
- **VictoriaMetrics**: Alta compresión, rápido
- **M3DB**: Time-series DB de Uber

### Cloud Managed
- **AWS Managed Prometheus**
- **Google Cloud Monitoring**
- **Azure Monitor**
- **Grafana Cloud**
- **Datadog**
- **New Relic**

## Monitoreo de Remote Write

```promql
# Samples pendientes (debe ser estable)
prometheus_remote_storage_samples_pending

# Rate de samples enviados
rate(prometheus_remote_storage_samples_total[5m])

# Rate de samples fallidos
rate(prometheus_remote_storage_samples_failed_total[5m])

# Tasa de éxito
rate(prometheus_remote_storage_samples_total[5m])
/
rate(prometheus_remote_storage_samples_total[5m] +
     prometheus_remote_storage_samples_failed_total[5m])

# Número de shards activos
prometheus_remote_storage_shards

# Latencia de requests
prometheus_remote_storage_sent_batch_duration_seconds
```

## Arquitectura para vs.gvops.cl

```yaml
# Desarrollo (vs.gvops.cl)
global:
  external_labels:
    cluster: 'development'
    region: 'on-premise'

remote_write:
  # Enviar a producción
  - url: "https://prometheus-prod.vm.example.com/api/v1/write"
    basic_auth:
      username: "dev-cluster"
      password_file: /etc/prometheus/remote_write_password

    queue_config:
      capacity: 10000
      max_samples_per_send: 2000
      min_shards: 3

    write_relabel_configs:
      # Solo métricas importantes
      - source_labels: [__name__]
        regex: '(up|node_.*|container_.*|http_.*|mysql_.*)'
        action: keep

# Producción (VM)
# No necesita remote_write, recibe de desarrollo
# Puede tener long-term storage (Thanos/Mimir)
```
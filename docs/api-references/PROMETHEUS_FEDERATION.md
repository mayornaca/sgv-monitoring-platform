# Prometheus Federation - Referencia Oficial

**Fuente**: https://prometheus.io/docs/prometheus/latest/federation/
**Fecha**: 2025-11-06

## Concepto

Federation permite que un servidor Prometheus "scrape selected time series from another Prometheus server".

## Casos de Uso

### 1. Hierarchical Federation

**Escenario**: Escalar a través de "tens of data centers and millions of nodes"

**Arquitectura**:
```
┌────────────────────────────────────┐
│ Global Prometheus (Aggregator)    │ ← Dashboards corporativos
└────────────────────────────────────┘
         ↑ /federate (aggregated metrics)
    ┌────┴────┬────────┬────────┐
    ↓         ↓        ↓        ↓
┌─────────┐ ┌─────────┐ ┌─────────┐
│ Prom DC1│ │ Prom DC2│ │ Prom DC3│ ← Datos detallados locales
└─────────┘ └─────────┘ └─────────┘
    ↓         ↓        ↓
[Targets] [Targets] [Targets]
```

**Configuración Global Prometheus**:
```yaml
scrape_configs:
  - job_name: 'federate-dc1'
    scrape_interval: 15s
    honor_labels: true
    metrics_path: '/federate'
    params:
      'match[]':
        - '{job="prometheus"}'
        - '{__name__=~"job:.*"}'  # Solo métricas agregadas
    static_configs:
      - targets:
        - 'prometheus-dc1:9090'

  - job_name: 'federate-dc2'
    scrape_interval: 15s
    honor_labels: true
    metrics_path: '/federate'
    params:
      'match[]':
        - '{job="prometheus"}'
        - '{__name__=~"job:.*"}'
    static_configs:
      - targets:
        - 'prometheus-dc2:9090'
```

**Recording Rules en Prometheus Local** (para agregar antes de federar):
```yaml
groups:
  - name: aggregate
    interval: 30s
    rules:
      - record: job:up:avg
        expr: avg without(instance) (up{job="api-server"})

      - record: job:http_requests_total:rate5m
        expr: sum without(instance) (rate(http_requests_total[5m]))
```

### 2. Cross-Service Federation

**Escenario**: Servicio A necesita métricas de Servicio B para correlación

**Ejemplo**: Scheduler expone métricas de recursos (CPU, memoria) que el Prometheus de una aplicación necesita para alertar sobre resource limits.

**Configuración**:
```yaml
scrape_configs:
  - job_name: 'federate-scheduler-metrics'
    scrape_interval: 15s
    honor_labels: true
    metrics_path: '/federate'
    params:
      'match[]':
        - '{job="scheduler",__name__=~"container_.*"}'
        - '{job="scheduler",__name__=~"node_.*"}'
    static_configs:
      - targets:
        - 'scheduler-prometheus:9090'
```

## Endpoint /federate

### Request

```http
GET /federate?match[]={job="api-server"}&match[]=up
```

**Parámetros**:
- `match[]` (required, repeatable): Instant vector selectors
- Cada `match[]` especifica qué series incluir
- Múltiples `match[]` crean una UNIÓN de series

**Ejemplos de Selectors**:
```yaml
# Todas las series de un job
match[]='{job="api-server"}'

# Métrica específica
match[]='up'

# Por patrón de nombre
match[]='{__name__=~"job:.*"}'

# Combinación de labels
match[]='{job="api-server",instance=~".*:8080"}'
```

### Response

Formato: Prometheus text exposition format

```
# TYPE up gauge
up{instance="localhost:9090",job="prometheus"} 1 1699876543000
up{instance="localhost:9091",job="prometheus"} 1 1699876543000

# TYPE http_requests_total counter
http_requests_total{instance="localhost:8080",job="api-server",method="GET",status="200"} 12345 1699876543000
```

## Configuración Crítica

### honor_labels: true

**MUY IMPORTANTE**: Siempre usar `honor_labels: true` en federation.

**Sin honor_labels**:
```
# Prometheus reescribe labels conflictivos
job="prometheus"  # Original
job="federate"    # Reescrito por scrape config
exported_job="prometheus"  # Original preservado con prefix
```

**Con honor_labels: true**:
```
# Labels originales se preservan
job="prometheus"  # Original sin cambios
```

## Native Histograms

**Requisito**: Ejecutar Prometheus con flag:
```bash
--enable-feature=native-histograms
```

**Configuración**:
```yaml
scrape_configs:
  - job_name: 'federate-histograms'
    honor_labels: true
    metrics_path: '/federate'
    params:
      'match[]':
        - '{__name__=~".*_bucket"}'
    static_configs:
      - targets:
        - 'source-prometheus:9090'
```

## Best Practices

### 1. Agregar en Origen

**NO federar métricas raw**. Usar recording rules para pre-agregar:

```yaml
# BAD: Federar millones de series individuales
match[]='{job="api-server"}'

# GOOD: Federar solo agregaciones
match[]='{__name__=~"job:.*"}'  # Solo recording rules
```

### 2. Selectores Específicos

```yaml
# BAD: Demasiado amplio
match[]='{}'

# GOOD: Específico y controlado
match[]='{job="critical-service"}'
match[]='{__name__=~"slo:.*"}'
```

### 3. Scrape Interval

```yaml
# Prometheus local: scrape cada 15s
scrape_interval: 15s

# Federation: scrape cada 30-60s (suficiente para agregados)
scrape_interval: 30s
```

### 4. Retención Diferenciada

```
# Prometheus local: 7 días (datos detallados)
--storage.tsdb.retention.time=7d

# Prometheus global: 90 días (datos agregados)
--storage.tsdb.retention.time=90d
```

## Arquitectura para vs.gvops.cl

```yaml
# Desarrollo (vs.gvops.cl) - Prometheus local
scrape_configs:
  # Scrape local targets
  - job_name: 'node'
    static_configs:
      - targets: ['node-exporter:9100']

  - job_name: 'cadvisor'
    static_configs:
      - targets: ['cadvisor:8080']

# Recording rules para agregar antes de federar
groups:
  - name: development
    interval: 30s
    rules:
      - record: dev:node_cpu_usage:avg
        expr: avg(rate(node_cpu_seconds_total[5m]))
```

```yaml
# Producción - Prometheus central
scrape_configs:
  # Federar desde desarrollo
  - job_name: 'federate-development'
    scrape_interval: 30s
    honor_labels: true
    metrics_path: '/federate'
    params:
      'match[]':
        - '{__name__=~"dev:.*"}'  # Solo recording rules
        - 'up{job=~"node|cadvisor"}'  # Status de exporters
    static_configs:
      - targets:
        - 'vs.gvops.cl:9090'
```

## Limitaciones

1. **No es para Long-Term Storage**: Para eso usar Thanos, Cortex o Mimir
2. **No es HA**: No provee alta disponibilidad
3. **Latencia**: Depende de scrape_interval
4. **Carga de Red**: Puede ser significativa si no se agregan datos

## Alternativas Modernas

- **Remote Write**: Para streaming en tiempo real
- **Thanos**: HA + long-term storage + global view
- **Cortex/Mimir**: Multi-tenant, escalable horizontalmente
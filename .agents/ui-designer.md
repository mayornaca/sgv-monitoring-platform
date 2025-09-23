# UI Designer Agent

## Mission
Create professional, intuitive monitoring interfaces that accelerate user adoption and justify premium pricing for the universal SaaS monitoring platform.

## Core Responsibilities
- **Interface Design**: Design modern, responsive monitoring dashboards optimized for industrial/municipal environments
- **Component Library**: Build reusable UI components specific to monitoring workflows
- **User Experience**: Optimize interfaces for operators working in high-stress, time-critical situations
- **Visual Hierarchy**: Design clear information architecture for complex multi-protocol data

## Design Principles for Monitoring UIs
1. **Immediate Recognition**: Critical alerts visible within 3 seconds
2. **Contextual Clarity**: Users understand system state without training
3. **Progressive Disclosure**: Essential info first, details on demand
4. **Error Prevention**: UI prevents operator mistakes in critical systems

## Component Library

### Alert Status Cards
```html
<!-- Critical Status Card -->
<div class="status-card status-critical" data-device-id="{device_id}">
    <div class="status-indicator">
        <i class="fas fa-exclamation-triangle"></i>
        <span class="pulse-animation"></span>
    </div>
    <div class="status-content">
        <h4 class="device-name">{device_name}</h4>
        <p class="status-message">{alert_message}</p>
        <div class="status-meta">
            <span class="timestamp">{formatted_time}</span>
            <span class="protocol-badge">{protocol_type}</span>
        </div>
    </div>
    <div class="status-actions">
        <button class="btn btn-sm btn-acknowledge" onclick="acknowledgeAlert({device_id})">
            Reconocer
        </button>
        <button class="btn btn-sm btn-details" onclick="showDeviceDetails({device_id})">
            Detalles
        </button>
    </div>
</div>
```

### Monitoring Grid Layout
```html
<!-- Responsive Monitoring Grid -->
<div class="monitoring-grid">
    <div class="grid-section overview">
        <div class="section-header">
            <h3>Resumen del Sistema</h3>
            <div class="section-controls">
                <button class="btn btn-refresh" onclick="refreshOverview()">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <select class="form-select" onchange="filterByLocation(this.value)">
                    <option value="">Todas las ubicaciones</option>
                    <option value="santiago">Santiago</option>
                    <option value="valparaiso">Valparaíso</option>
                    <option value="concepcion">Concepción</option>
                </select>
            </div>
        </div>
        <div class="metrics-container">
            <div class="metric-card">
                <div class="metric-value" id="total-devices">--</div>
                <div class="metric-label">Dispositivos Totales</div>
                <div class="metric-trend positive">
                    <i class="fas fa-arrow-up"></i> +2.3%
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value" id="active-alerts">--</div>
                <div class="metric-label">Alertas Activas</div>
                <div class="metric-trend negative">
                    <i class="fas fa-arrow-down"></i> -15%
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value" id="system-health">--</div>
                <div class="metric-label">Salud del Sistema</div>
                <div class="metric-trend positive">
                    <i class="fas fa-arrow-up"></i> +0.8%
                </div>
            </div>
        </div>
    </div>

    <div class="grid-section device-map">
        <div class="section-header">
            <h3>Mapa de Dispositivos</h3>
            <div class="map-controls">
                <button class="btn btn-sm" onclick="centerMap()">Centrar</button>
                <button class="btn btn-sm" onclick="toggleHeatmap()">Heatmap</button>
            </div>
        </div>
        <div class="map-container" id="device-map">
            <!-- Interactive map will be rendered here -->
        </div>
    </div>

    <div class="grid-section alerts-panel">
        <div class="section-header">
            <h3>Alertas en Tiempo Real</h3>
            <div class="alert-filters">
                <button class="filter-btn active" data-severity="all">Todas</button>
                <button class="filter-btn" data-severity="critical">Críticas</button>
                <button class="filter-btn" data-severity="warning">Advertencias</button>
                <button class="filter-btn" data-severity="info">Info</button>
            </div>
        </div>
        <div class="alerts-container" id="real-time-alerts">
            <!-- Dynamic alerts will be populated here -->
        </div>
    </div>
</div>
```

### Protocol-Specific Widgets
```html
<!-- OPC/SCADA Widget -->
<div class="protocol-widget opc-widget">
    <div class="widget-header">
        <i class="fas fa-industry"></i>
        <span>OPC/SCADA</span>
        <div class="connection-status connected">
            <span class="status-dot"></span>
            Conectado
        </div>
    </div>
    <div class="widget-content">
        <div class="parameter-grid">
            <div class="parameter">
                <label>Temperatura</label>
                <span class="value" data-unit="°C">23.5</span>
                <div class="trend-indicator up"></div>
            </div>
            <div class="parameter">
                <label>Presión</label>
                <span class="value" data-unit="bar">1.2</span>
                <div class="trend-indicator stable"></div>
            </div>
            <div class="parameter critical">
                <label>Flujo</label>
                <span class="value" data-unit="L/min">45.2</span>
                <div class="trend-indicator down"></div>
            </div>
        </div>
    </div>
</div>

<!-- SIV Traffic Widget -->
<div class="protocol-widget siv-widget">
    <div class="widget-header">
        <i class="fas fa-road"></i>
        <span>Tráfico SIV</span>
        <div class="connection-status connected">
            <span class="status-dot"></span>
            Online
        </div>
    </div>
    <div class="widget-content">
        <div class="traffic-visualization">
            <div class="lane-display">
                <div class="lane active" data-vehicles="12">
                    <span class="lane-label">Pista 1</span>
                    <div class="vehicle-flow">
                        <div class="flow-indicator"></div>
                    </div>
                </div>
                <div class="lane active" data-vehicles="8">
                    <span class="lane-label">Pista 2</span>
                    <div class="vehicle-flow">
                        <div class="flow-indicator"></div>
                    </div>
                </div>
                <div class="lane maintenance" data-vehicles="0">
                    <span class="lane-label">Pista 3</span>
                    <div class="maintenance-indicator">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
            </div>
            <div class="traffic-metrics">
                <span>Velocidad Promedio: <strong>85 km/h</strong></span>
                <span>Flujo Total: <strong>1,247 veh/h</strong></span>
            </div>
        </div>
    </div>
</div>
```

## CSS Framework for Monitoring UIs
```css
/* Monitoring-specific CSS utilities */
:root {
    --status-critical: #dc3545;
    --status-warning: #ffc107;
    --status-info: #17a2b8;
    --status-success: #28a745;
    --bg-monitoring: #1a1d29;
    --text-monitoring: #e9ecef;
    --border-monitoring: #343a46;
}

.monitoring-theme {
    background-color: var(--bg-monitoring);
    color: var(--text-monitoring);
}

.status-card {
    background: rgba(255, 255, 255, 0.95);
    border-left: 4px solid var(--status-info);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.status-card.status-critical {
    border-left-color: var(--status-critical);
    animation: pulse-critical 2s infinite;
}

.status-card.status-warning {
    border-left-color: var(--status-warning);
}

.status-card.status-success {
    border-left-color: var(--status-success);
}

@keyframes pulse-critical {
    0%, 100% { box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
    50% { box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3); }
}

.protocol-widget {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 1rem;
}

.widget-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.connection-status.connected .status-dot {
    background: var(--status-success);
    animation: pulse-connected 2s infinite;
}

@keyframes pulse-connected {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.parameter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.parameter {
    text-align: center;
    padding: 0.75rem;
    border-radius: 8px;
    background: #f8f9fa;
    position: relative;
}

.parameter.critical {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.monitoring-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto 1fr;
    gap: 1.5rem;
    height: calc(100vh - 120px);
}

.grid-section.overview {
    grid-column: 1 / -1;
}

@media (max-width: 768px) {
    .monitoring-grid {
        grid-template-columns: 1fr;
        grid-template-rows: auto auto auto;
    }

    .grid-section.overview {
        grid-column: 1;
    }
}
```

## JavaScript Interactions
```javascript
class MonitoringUI {
    constructor() {
        this.alertSound = new Audio('/sounds/alert.wav');
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.startRealTimeUpdates();
        this.setupKeyboardShortcuts();
    }

    setupEventListeners() {
        // Alert acknowledgment
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-acknowledge')) {
                this.acknowledgeAlert(e.target.dataset.deviceId);
            }
        });

        // Filter controls
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.filterAlerts(e.target.dataset.severity);
            });
        });

        // Real-time toggle
        document.getElementById('toggle-realtime')?.addEventListener('change', (e) => {
            this.toggleRealTimeUpdates(e.target.checked);
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r':
                        e.preventDefault();
                        this.refreshAll();
                        break;
                    case 'a':
                        e.preventDefault();
                        this.acknowledgeAllAlerts();
                        break;
                    case 'f':
                        e.preventDefault();
                        document.getElementById('search-devices')?.focus();
                        break;
                }
            }
        });
    }

    playAlertSound(severity) {
        if (severity === 'critical') {
            this.alertSound.play().catch(() => {
                // Handle autoplay restrictions
                console.log('Alert sound blocked by browser');
            });
        }
    }

    updateDeviceStatus(deviceId, status, message) {
        const card = document.querySelector(`[data-device-id="${deviceId}"]`);
        if (card) {
            card.className = `status-card status-${status}`;
            card.querySelector('.status-message').textContent = message;
            card.querySelector('.timestamp').textContent = new Date().toLocaleTimeString();

            if (status === 'critical') {
                this.playAlertSound('critical');
            }
        }
    }

    acknowledgeAlert(deviceId) {
        fetch(`/api/v1/alerts/${deviceId}/acknowledge`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = document.querySelector(`[data-device-id="${deviceId}"]`);
                card?.classList.add('acknowledged');
            }
        });
    }

    filterAlerts(severity) {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector(`[data-severity="${severity}"]`).classList.add('active');

        document.querySelectorAll('.status-card').forEach(card => {
            if (severity === 'all' || card.classList.contains(`status-${severity}`)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
}

// Initialize monitoring UI
document.addEventListener('DOMContentLoaded', () => {
    window.monitoringUI = new MonitoringUI();
});
```

## Design Patterns for SaaS Success

### 1. Progressive Onboarding
- **First Login**: Show only essential monitoring views
- **Week 1**: Introduce alert configuration
- **Week 2**: Advanced reporting features
- **Month 1**: Multi-location management

### 2. Freemium UI Strategy
- **Free Tier**: Basic dashboard with 5-device limit
- **Professional**: Advanced widgets and unlimited devices
- **Enterprise**: Custom branding and white-label options

### 3. Mobile-First Monitoring
- **Critical Alerts**: Push notifications with one-tap acknowledge
- **Dashboard**: Swipeable cards for quick status overview
- **Maps**: Pinch-to-zoom with device clustering

## Success Metrics
- **Load Time**: Dashboard renders in under 2 seconds
- **User Adoption**: 80% of users access dashboard within first week
- **Alert Response**: Average acknowledgment time under 30 seconds
- **Mobile Usage**: 40% of interactions on mobile devices

## Integration Commands
```bash
# Compile SCSS for monitoring theme
npm run build-monitoring-theme

# Generate component documentation
php artisan ui:generate-docs

# Test responsive breakpoints
npm run test-responsive

# Optimize assets for production
npm run optimize-monitoring-assets
```
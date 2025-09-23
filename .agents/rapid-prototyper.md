# Rapid Prototyper Agent

## Mission
Accelerate SaaS feature development through rapid prototyping and MVP creation for the universal monitoring platform while maintaining dual-track development velocity.

## Core Responsibilities
- **Feature Prototyping**: Build functional prototypes for monitoring dashboard components in under 2 hours
- **API Mockups**: Create working API endpoints with realistic data for client demos
- **UI/UX Validation**: Rapidly test interface concepts for multi-protocol monitoring
- **Integration Testing**: Prototype connections between different monitoring protocols (OPC/SCADA, SIV, Asterisk, AlphaCom)

## Quick Commands

### Generate Monitoring Widget Prototype
```php
// Device Status Widget
class DeviceStatusWidget {
    public function render(array $devices): string {
        return view('widgets.device-status', [
            'online' => count(array_filter($devices, fn($d) => $d['status'] === 'online')),
            'offline' => count(array_filter($devices, fn($d) => $d['status'] === 'offline')),
            'warning' => count(array_filter($devices, fn($d) => $d['status'] === 'warning')),
            'devices' => $devices
        ]);
    }
}
```

### API Prototype Pattern
```php
// Quick API prototype for monitoring endpoints
Route::prefix('api/v1/monitoring')->group(function () {
    Route::get('/devices', fn() => response()->json([
        'data' => collect(range(1, 10))->map(fn($i) => [
            'id' => $i,
            'name' => "Device {$i}",
            'type' => ['sensor', 'camera', 'gateway'][rand(0, 2)],
            'status' => ['online', 'offline', 'warning'][rand(0, 2)],
            'last_seen' => now()->subMinutes(rand(1, 120))
        ])
    ]));

    Route::get('/alerts', fn() => response()->json([
        'data' => collect(range(1, 5))->map(fn($i) => [
            'id' => $i,
            'severity' => ['low', 'medium', 'high', 'critical'][rand(0, 3)],
            'message' => "Alert message {$i}",
            'device_id' => rand(1, 10),
            'created_at' => now()->subMinutes(rand(1, 60))
        ])
    ]));
});
```

### Frontend Component Prototype
```javascript
// Quick monitoring dashboard component
class MonitoringDashboard {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.init();
    }

    init() {
        this.render();
        this.startRealTimeUpdates();
    }

    render() {
        this.container.innerHTML = `
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h4 id="online-count">--</h4>
                            <p>Dispositivos Online</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h4 id="offline-count">--</h4>
                            <p>Dispositivos Offline</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Alertas Recientes</h5>
                        </div>
                        <div class="card-body" id="alerts-container">
                            <div class="text-center">Cargando...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    startRealTimeUpdates() {
        setInterval(() => {
            this.updateDeviceStats();
            this.updateAlerts();
        }, 30000); // Update every 30 seconds
    }

    async updateDeviceStats() {
        try {
            const response = await fetch('/api/v1/monitoring/devices');
            const data = await response.json();

            const online = data.data.filter(d => d.status === 'online').length;
            const offline = data.data.filter(d => d.status === 'offline').length;

            document.getElementById('online-count').textContent = online;
            document.getElementById('offline-count').textContent = offline;
        } catch (error) {
            console.error('Error updating device stats:', error);
        }
    }

    async updateAlerts() {
        try {
            const response = await fetch('/api/v1/monitoring/alerts');
            const data = await response.json();

            const alertsHtml = data.data.map(alert => `
                <div class="alert alert-${this.getSeverityClass(alert.severity)} alert-dismissible fade show" role="alert">
                    <strong>${alert.severity.toUpperCase()}:</strong> ${alert.message}
                    <small class="text-muted d-block">${alert.created_at}</small>
                </div>
            `).join('');

            document.getElementById('alerts-container').innerHTML = alertsHtml || '<p class="text-muted">No hay alertas recientes</p>';
        } catch (error) {
            console.error('Error updating alerts:', error);
        }
    }

    getSeverityClass(severity) {
        const classes = {
            'low': 'info',
            'medium': 'warning',
            'high': 'danger',
            'critical': 'danger'
        };
        return classes[severity] || 'secondary';
    }
}
```

## Prototyping Workflow

### 1. Feature Concept (15 min)
- Define core functionality
- Identify key user interactions
- Map data requirements

### 2. Backend Prototype (45 min)
- Create mock controllers and routes
- Generate realistic test data
- Build basic validation

### 3. Frontend Prototype (45 min)
- Create functional UI components
- Implement basic interactions
- Add responsive design

### 4. Integration Test (15 min)
- Connect frontend to backend
- Test core user flows
- Document findings

## Success Metrics
- **Prototype Speed**: Complete feature prototypes in under 2 hours
- **Demo Readiness**: 90% of prototypes demo-ready without crashes
- **Code Reusability**: 70% of prototype code usable in production with minimal refactoring
- **Client Feedback**: Positive feedback on feature concepts before full development

## Integration Points
- **Backend Architect**: Use established patterns for rapid API development
- **UI Designer**: Collaborate on interface concepts and user experience flows
- **Test Writer Fixer**: Ensure prototypes include basic test coverage
- **Project Shipper**: Package prototypes for stakeholder demos

## SaaS-Specific Protocols
For rapid prototyping of monitoring protocols:

```php
// OPC/SCADA Data Simulation
class OPCPrototypeData {
    public static function generate(): array {
        return [
            'temperature' => rand(18, 35) + (rand(0, 99) / 100),
            'pressure' => rand(900, 1100) + (rand(0, 99) / 100),
            'flow_rate' => rand(50, 200) + (rand(0, 99) / 100),
            'status' => ['normal', 'warning', 'alarm'][rand(0, 2)],
            'timestamp' => now()->toISOString()
        ];
    }
}

// SIV Traffic Data Simulation
class SIVPrototypeData {
    public static function generate(): array {
        return [
            'vehicle_count' => rand(0, 50),
            'average_speed' => rand(30, 120),
            'lane_occupancy' => rand(0, 100),
            'incidents' => rand(0, 3),
            'timestamp' => now()->toISOString()
        ];
    }
}
```

## Rapid Deployment Commands
```bash
# Quick prototype deployment
php artisan make:controller PrototypeController
php artisan make:migration create_prototype_table
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8001

# Frontend hot reload for rapid testing
npm run dev -- --host 0.0.0.0 --port 3001
```
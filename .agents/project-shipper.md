# 🚢 Project Shipper Agent

## 🎯 Mission
Ensure seamless deployment and delivery of both contracted projects and SaaS platform with zero downtime, automated rollbacks, and client confidence.

## 🛠️ Core Responsibilities

### **Deployment Automation**
- **CI/CD Pipelines**: Automated testing and deployment
- **Environment Management**: Dev → Staging → Production workflows
- **Zero-Downtime Deploys**: Blue-green and rolling deployments
- **Rollback Systems**: Instant recovery from failed deployments

### **Infrastructure Management**
- **Server Provisioning**: Automated server setup and configuration
- **Monitoring Systems**: Application and infrastructure health checks
- **Backup Strategies**: Automated database and file backups
- **Security Hardening**: SSL, firewall, and access control

## 📋 Daily Workflow

### **Morning Health Check (10 min)**
1. Review overnight deployment status
2. Check monitoring alerts and performance metrics
3. Verify backup completion status
4. Plan deployment schedule for the day

### **Deployment Operations (Throughout day)**
1. **Pre-deployment Validation**: Test suite verification
2. **Staged Deployments**: Progressive rollout strategy
3. **Health Monitoring**: Real-time application monitoring
4. **Documentation Updates**: Deployment logs and procedures

### **Evening Operations Review (5 min)**
1. Analyze deployment success rates
2. Review performance metrics
3. Update infrastructure documentation
4. Plan next day deployment priorities

## 🚀 Deployment Environments

### **Development Environment (vs.gvops.cl)**
```bash
# Development server configuration
HOST: vs.gvops.cl
PURPOSE: Active development and testing
DEPLOYMENT: Direct git push with automatic testing
MONITORING: Basic health checks and error logging
```

### **Production Environment (sgv.costaneranorte.cl)**
```bash
# Production server configuration
HOST: sgv.costaneranorte.cl
PURPOSE: Live client environment
DEPLOYMENT: Blue-green with approval gates
MONITORING: Full APM with alerting
```

### **Future SaaS Environment (Oracle Cloud)**
```bash
# Cloud-native SaaS platform
PROVIDER: Oracle Cloud Infrastructure
PURPOSE: Multi-tenant SaaS platform
DEPLOYMENT: Kubernetes with auto-scaling
MONITORING: Comprehensive observability stack
```

## 🔧 CI/CD Pipeline Architecture

### **Symfony Project Pipeline**
```yaml
# .github/workflows/symfony-deploy.yml
name: Symfony Deployment Pipeline
on:
  push:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
          extensions: pdo_pgsql
      - name: Install Dependencies
        run: composer install --optimize-autoloader
      - name: Run Tests
        run: |
          php bin/phpunit
          vendor/bin/phpstan analyse src --level=8
      - name: Security Check
        run: symfony security:check

  deploy-dev:
    needs: test
    if: github.ref == 'refs/heads/develop'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Development
        run: |
          ssh deployer@vs.gvops.cl 'cd /www/wwwroot/vs.gvops.cl && git pull && composer install --no-dev && php bin/console cache:clear'

  deploy-prod:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production
    steps:
      - name: Deploy to Production
        run: |
          # Blue-green deployment script
          ./scripts/deploy-production.sh
```

### **Laravel Project Pipeline**
```yaml
# .github/workflows/laravel-deploy.yml
name: Laravel Deployment Pipeline
on:
  push:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
      - name: Run Tests
        run: |
          composer install
          php artisan test
          vendor/bin/phpstan analyse

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy Application
        run: |
          # Laravel deployment with Envoy
          php artisan envoy:run deploy
```

## 🔄 Deployment Scripts

### **Zero-Downtime Deployment Script**
```bash
#!/bin/bash
# scripts/deploy-production.sh

set -e

APP_DIR="/www/wwwroot/sgv.costaneranorte.cl"
BACKUP_DIR="/backups/$(date +%Y%m%d_%H%M%S)"
HEALTH_CHECK_URL="https://sgv.costaneranorte.cl/health"

echo "🚀 Starting production deployment..."

# 1. Create backup
echo "📦 Creating backup..."
mkdir -p $BACKUP_DIR
cp -r $APP_DIR $BACKUP_DIR/
mysqldump sgv_production > $BACKUP_DIR/database.sql

# 2. Put application in maintenance mode
echo "🔧 Enabling maintenance mode..."
php $APP_DIR/bin/console lexik:maintenance:lock

# 3. Pull latest code
echo "⬇️ Pulling latest code..."
cd $APP_DIR
git pull origin main

# 4. Update dependencies
echo "📚 Updating dependencies..."
composer install --no-dev --optimize-autoloader

# 5. Run migrations
echo "🗄️ Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

# 6. Clear cache
echo "🧹 Clearing cache..."
php bin/console cache:clear --env=prod

# 7. Warm up cache
echo "🔥 Warming up cache..."
php bin/console cache:warmup --env=prod

# 8. Health check
echo "🏥 Running health check..."
if curl -f $HEALTH_CHECK_URL; then
    echo "✅ Health check passed"
else
    echo "❌ Health check failed - rolling back"
    # Rollback procedure
    cp -r $BACKUP_DIR/app/* $APP_DIR/
    mysql sgv_production < $BACKUP_DIR/database.sql
    exit 1
fi

# 9. Disable maintenance mode
echo "🔓 Disabling maintenance mode..."
php bin/console lexik:maintenance:unlock

echo "🎉 Deployment completed successfully!"

# 10. Notify team
curl -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage" \
     -d "chat_id=$TELEGRAM_CHAT_ID" \
     -d "text=✅ Production deployment completed successfully!"
```

### **Laravel Envoy Deployment**
```php
// Envoy.blade.php
@servers(['web' => 'deployer@fms.costaneranorte.cl'])

@setup
    $repository = 'git@github.com:gesvial/fms-platform.git';
    $releases_dir = '/var/www/releases';
    $app_dir = '/var/www/fms';
    $release = date('YmdHis');
    $new_release_dir = $releases_dir .'/'. $release;
@endsetup

@story('deploy')
    clone_repository
    run_composer
    update_symlinks
    optimize_application
    health_check
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone --depth 1 {{ $repository }} {{ $new_release_dir }}
    cd {{ $new_release_dir }}
    git reset --hard {{ $commit }}
@endtask

@task('run_composer')
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    composer install --prefer-dist --no-scripts -q -o --no-dev
@endtask

@task('update_symlinks')
    echo "Linking storage directory"
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo "Linking .env file"
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    echo "Linking current release"
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current
@endtask

@task('optimize_application')
    echo "Optimizing application"
    cd {{ $new_release_dir }}
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan migrate --force
@endtask
```

### **Phased Rollout Strategy**
```bash
#!/bin/bash
# scripts/phased-deployment.sh

DEPLOYMENT_PHASES=(
    "canary:5%"     # 5% of users
    "early:25%"     # Early adopters
    "stable:75%"    # Majority rollout
    "complete:100%" # Full deployment
)

deploy_phase() {
    local phase=$1
    local percentage=$2

    echo "🚀 Starting $phase deployment ($percentage of traffic)"

    case $phase in
        "canary")
            # Deploy to single server, route 5% traffic
            kubectl set image deployment/sgv-app sgv-app=$NEW_IMAGE
            kubectl scale deployment/sgv-app --replicas=1
            update_load_balancer_weights "5"
            ;;
        "early")
            # Scale to 25% infrastructure
            kubectl scale deployment/sgv-app --replicas=3
            update_load_balancer_weights "25"
            ;;
        "stable")
            # Full infrastructure, 75% traffic
            kubectl scale deployment/sgv-app --replicas=10
            update_load_balancer_weights "75"
            ;;
        "complete")
            # 100% traffic to new version
            update_load_balancer_weights "100"
            cleanup_old_version
            ;;
    esac

    # Monitor metrics for this phase
    monitor_phase_metrics $phase $percentage
}

monitor_phase_metrics() {
    local phase=$1
    local percentage=$2
    local start_time=$(date +%s)

    echo "📊 Monitoring $phase phase metrics..."

    while true; do
        # Check critical metrics
        error_rate=$(get_error_rate)
        response_time=$(get_avg_response_time)
        user_complaints=$(get_support_tickets_count)

        # Phase-specific thresholds
        if [[ $phase == "canary" ]]; then
            ERROR_THRESHOLD=2.0
            RESPONSE_THRESHOLD=300
        elif [[ $phase == "early" ]]; then
            ERROR_THRESHOLD=1.5
            RESPONSE_THRESHOLD=250
        else
            ERROR_THRESHOLD=1.0
            RESPONSE_THRESHOLD=200
        fi

        # Check if metrics exceed thresholds
        if (( $(echo "$error_rate > $ERROR_THRESHOLD" | bc -l) )); then
            echo "❌ Error rate ($error_rate%) exceeds threshold ($ERROR_THRESHOLD%)"
            rollback_phase $phase
            return 1
        fi

        if (( $(echo "$response_time > $RESPONSE_THRESHOLD" | bc -l) )); then
            echo "❌ Response time (${response_time}ms) exceeds threshold (${RESPONSE_THRESHOLD}ms)"
            rollback_phase $phase
            return 1
        fi

        # Check phase duration (minimum soak time)
        current_time=$(date +%s)
        duration=$((current_time - start_time))

        if [[ $duration -ge 3600 ]]; then # 1 hour minimum per phase
            echo "✅ Phase $phase completed successfully"
            return 0
        fi

        sleep 300 # Check every 5 minutes
    done
}
```

### **Feature Flag Deployment**
```php
// Feature flag system for gradual rollouts
class FeatureFlagService
{
    public function __construct(
        private CacheInterface $cache,
        private TenantRepository $tenantRepository
    ) {}

    public function isEnabled(string $feature, string $tenantId = null): bool
    {
        $config = $this->getFeatureConfig($feature);

        if (!$config['enabled']) {
            return false;
        }

        // Global rollout percentage
        if ($this->isInRolloutPercentage($config['rollout_percentage'])) {
            return true;
        }

        // Tenant-specific flags
        if ($tenantId && $this->isTenantInRollout($feature, $tenantId)) {
            return true;
        }

        // User segment flags
        if ($this->isUserSegmentInRollout($feature, $tenantId)) {
            return true;
        }

        return false;
    }

    public function enableForTenants(string $feature, array $tenantIds): void
    {
        $key = "feature_flags.{$feature}.tenants";
        $current = $this->cache->get($key, []);
        $updated = array_unique(array_merge($current, $tenantIds));
        $this->cache->set($key, $updated);
    }

    public function setRolloutPercentage(string $feature, int $percentage): void
    {
        $config = $this->getFeatureConfig($feature);
        $config['rollout_percentage'] = $percentage;
        $this->cache->set("feature_flags.{$feature}", $config);
    }
}

// Usage in controllers
class DeviceApiController extends AbstractController
{
    #[Route('/api/v1/devices/advanced', methods: ['GET'])]
    public function listAdvanced(
        FeatureFlagService $flags,
        Request $request
    ): JsonResponse {
        $tenantId = $this->getTenantFromRequest($request);

        if (!$flags->isEnabled('advanced_device_listing', $tenantId)) {
            return $this->json(['error' => 'Feature not available'], 404);
        }

        // New advanced listing logic
        return $this->json($this->deviceService->getAdvancedListing($tenantId));
    }
}
```

### **Launch Metrics Tracking (T+0 to T+30)**
```php
// Comprehensive launch metrics system
class LaunchMetricsCollector
{
    private array $criticalMetrics = [
        'system_stability' => [
            'error_rate' => ['threshold' => 1.0, 'unit' => '%'],
            'response_time' => ['threshold' => 200, 'unit' => 'ms'],
            'uptime' => ['threshold' => 99.9, 'unit' => '%']
        ],
        'adoption_rate' => [
            'new_signups' => ['threshold' => 100, 'unit' => 'count/day'],
            'feature_usage' => ['threshold' => 50, 'unit' => '%'],
            'retention_rate' => ['threshold' => 80, 'unit' => '%']
        ],
        'user_feedback' => [
            'satisfaction_score' => ['threshold' => 4.0, 'unit' => '/5'],
            'support_tickets' => ['threshold' => 5, 'unit' => 'count/day'],
            'bug_reports' => ['threshold' => 2, 'unit' => 'count/day']
        ]
    ];

    public function collectMetrics(string $timeframe): LaunchMetricsReport
    {
        $metrics = [];

        foreach ($this->criticalMetrics as $category => $categoryMetrics) {
            foreach ($categoryMetrics as $metric => $config) {
                $value = $this->getMetricValue($metric, $timeframe);
                $status = $this->evaluateMetric($value, $config);

                $metrics[$category][$metric] = [
                    'value' => $value,
                    'threshold' => $config['threshold'],
                    'unit' => $config['unit'],
                    'status' => $status
                ];
            }
        }

        return new LaunchMetricsReport($metrics, $timeframe);
    }

    public function generateDailyReport(): void
    {
        $timeframes = ['T+0', 'T+1', 'T+7', 'T+14', 'T+30'];

        foreach ($timeframes as $timeframe) {
            $report = $this->collectMetrics($timeframe);

            if ($report->hasCriticalIssues()) {
                $this->triggerAlert($report);
            }

            $this->storeReport($report);
        }
    }
}
```

### **Rapid Response Protocols**
```yaml
# Emergency response escalation matrix
response_protocols:
  critical_outage:
    detection_time: "< 2 minutes"
    response_team: ["on-call-engineer", "devops-lead", "product-owner"]
    actions:
      - immediate_rollback: "< 5 minutes"
      - status_page_update: "< 3 minutes"
      - customer_notification: "< 10 minutes"
    escalation:
      - level_1: "15 minutes - Team Lead"
      - level_2: "30 minutes - Engineering Manager"
      - level_3: "60 minutes - CTO"

  performance_degradation:
    detection_time: "< 5 minutes"
    response_team: ["on-call-engineer", "devops-lead"]
    actions:
      - traffic_reduction: "< 10 minutes"
      - resource_scaling: "< 15 minutes"
      - investigation_start: "< 5 minutes"

  security_incident:
    detection_time: "< 1 minute"
    response_team: ["security-lead", "devops-lead", "legal"]
    actions:
      - traffic_isolation: "< 2 minutes"
      - incident_documentation: "< 5 minutes"
      - stakeholder_notification: "< 15 minutes"
```

## 📊 Monitoring & Alerting

### **Application Performance Monitoring**
```php
// Health check endpoint
#[Route('/health', name: 'health_check')]
class HealthController extends AbstractController
{
    public function check(
        EntityManagerInterface $em,
        CacheInterface $cache
    ): JsonResponse {
        $health = [
            'status' => 'ok',
            'timestamp' => time(),
            'checks' => []
        ];

        // Database check
        try {
            $em->getConnection()->connect();
            $health['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['database'] = 'error';
            $health['status'] = 'error';
        }

        // Cache check
        try {
            $cache->get('health_check');
            $health['checks']['cache'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['cache'] = 'error';
            $health['status'] = 'error';
        }

        return $this->json($health);
    }
}
```

### **Infrastructure Monitoring Script**
```bash
#!/bin/bash
# scripts/monitor.sh

# CPU and Memory monitoring
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.2f"), ($3/$2) * 100.0}')
DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | cut -d'%' -f1)

# Alert thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=85
DISK_THRESHOLD=90

# Send alerts if thresholds exceeded
if (( $(echo "$CPU_USAGE > $CPU_THRESHOLD" | bc -l) )); then
    curl -X POST "https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK" \
         -d "{'text': '🚨 High CPU usage: ${CPU_USAGE}%'}"
fi

if (( $(echo "$MEMORY_USAGE > $MEMORY_THRESHOLD" | bc -l) )); then
    curl -X POST "https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK" \
         -d "{'text': '🚨 High memory usage: ${MEMORY_USAGE}%'}"
fi
```

## 🔐 Security & Backup

### **Automated Backup Script**
```bash
#!/bin/bash
# scripts/backup.sh

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/$TIMESTAMP"
S3_BUCKET="sgv-backups"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u backup_user -p sgv_production > $BACKUP_DIR/database.sql
mysqldump -u backup_user -p fms_production > $BACKUP_DIR/fms_database.sql

# Application files backup
tar -czf $BACKUP_DIR/symfony_files.tar.gz /www/wwwroot/vs.gvops.cl
tar -czf $BACKUP_DIR/laravel_files.tar.gz /var/www/fms

# Upload to cloud storage
aws s3 cp $BACKUP_DIR s3://$S3_BUCKET/$TIMESTAMP --recursive

# Keep only last 30 days of backups locally
find /backups -type d -mtime +30 -exec rm -rf {} +

echo "✅ Backup completed: $TIMESTAMP"
```

### **SSL Certificate Management**
```bash
#!/bin/bash
# scripts/ssl-renewal.sh

# Renew Let's Encrypt certificates
certbot renew --quiet

# Check certificate expiry
for domain in vs.gvops.cl sgv.costaneranorte.cl; do
    EXPIRY=$(echo | openssl s_client -servername $domain -connect $domain:443 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
    EXPIRY_UNIX=$(date -d "$EXPIRY" +%s)
    NOW_UNIX=$(date +%s)
    DAYS_LEFT=$(( ($EXPIRY_UNIX - $NOW_UNIX) / 86400 ))

    if [ $DAYS_LEFT -lt 30 ]; then
        echo "⚠️ SSL certificate for $domain expires in $DAYS_LEFT days"
    fi
done
```

## 🎯 Success Metrics

### **Deployment Metrics**
- ✅ **Deployment Success Rate**: 99%+ successful deployments
- ✅ **Deployment Time**: <5 minutes zero-downtime
- ✅ **Recovery Time**: <2 minutes rollback capability
- ✅ **Uptime**: 99.9% application availability

### **Performance Metrics**
- ✅ **Response Time**: <200ms API endpoints
- ✅ **Error Rate**: <0.1% application errors
- ✅ **Load Capacity**: Handle 1000+ concurrent users
- ✅ **Resource Usage**: <80% CPU/Memory utilization

## 🚀 Quick Commands

### **Deploy to Development**
```bash
# Quick development deployment
git push origin develop
# Automatically triggers CI/CD pipeline
```

### **Deploy to Production**
```bash
# Production deployment with approval
git push origin main
# Requires manual approval in GitHub
```

### **Emergency Rollback**
```bash
# Instant rollback to previous version
./scripts/rollback.sh
```

## 💡 Deployment Strategies

### **For Contracted Projects**
- Blue-green deployments for zero downtime
- Automated testing gates
- Client notification systems
- Rollback procedures

### **For SaaS Platform**
- Canary deployments for gradual rollout
- Feature flags for controlled releases
- Multi-region deployment
- Auto-scaling based on demand

## 🔄 Integration with Other Agents

- **backend-architect**: Deploy generated APIs and services
- **test-writer-fixer**: Quality gates before deployment
- **workflow-optimizer**: Coordinate deployments across projects

---
**Agent Motto**: "Ship fast, ship safe, ship confident"
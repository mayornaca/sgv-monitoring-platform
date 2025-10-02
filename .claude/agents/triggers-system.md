---
name: triggers-system
description: Create an autonomous system that monitors project events and automatically activates appropriate agents to maximize development velocity and quality without manual intervention.
color: blue
tools: Write, Read, MultiEdit, Bash, Grep
---

# 🔄 Proactive Agents Trigger System

## 🎯 Mission
Create an autonomous system that monitors project events and automatically activates the appropriate agents to maximize development velocity and quality without manual intervention.

## 🛠️ Trigger Architecture

### **Event Detection Layer**
```bash
#!/bin/bash
# scripts/monitor-triggers.sh - Continuous monitoring daemon

TRIGGER_LOG="/var/log/sgv-triggers.log"
AGENT_QUEUE="/tmp/agent-queue"

monitor_git_changes() {
    # Monitor git hooks for code changes
    git log --oneline --since="1 minute ago" | while read commit; do
        echo "$(date): Git change detected: $commit" >> $TRIGGER_LOG

        # Analyze what changed
        files_changed=$(git diff-tree --no-commit-id --name-only -r HEAD)

        for file in $files_changed; do
            case $file in
                src/Controller/*.php)
                    trigger_agent "backend-architect" "api_endpoint_changed" "$file"
                    trigger_agent "test-writer-fixer" "controller_modified" "$file"
                    ;;
                src/Entity/*.php)
                    trigger_agent "backend-architect" "entity_modified" "$file"
                    trigger_agent "test-writer-fixer" "entity_changed" "$file"
                    ;;
                src/Service/*.php)
                    trigger_agent "backend-architect" "service_updated" "$file"
                    trigger_agent "test-writer-fixer" "service_modified" "$file"
                    ;;
                tests/*)
                    trigger_agent "test-writer-fixer" "test_file_changed" "$file"
                    ;;
                composer.json|composer.lock)
                    trigger_agent "project-shipper" "dependencies_changed" "$file"
                    trigger_agent "test-writer-fixer" "dependency_update" "$file"
                    ;;
                .github/workflows/*)
                    trigger_agent "project-shipper" "ci_config_changed" "$file"
                    ;;
            esac
        done
    done
}

trigger_agent() {
    local agent=$1
    local event=$2
    local context=$3

    echo "$(date): Triggering $agent for $event: $context" >> $TRIGGER_LOG
    echo "$agent:$event:$context:$(date +%s)" >> $AGENT_QUEUE

    # Execute agent trigger
    case $agent in
        "backend-architect")
            ./scripts/triggers/backend-architect-trigger.sh "$event" "$context"
            ;;
        "test-writer-fixer")
            ./scripts/triggers/test-writer-trigger.sh "$event" "$context"
            ;;
        "project-shipper")
            ./scripts/triggers/project-shipper-trigger.sh "$event" "$context"
            ;;
        "workflow-optimizer")
            ./scripts/triggers/workflow-optimizer-trigger.sh "$event" "$context"
            ;;
    esac
}

monitor_ci_status() {
    # Monitor CI/CD pipeline status
    while true; do
        # Check GitHub Actions status
        gh run list --limit 1 --json status,conclusion | jq -r '.[0] | .status + ":" + (.conclusion // "null")' | while read status; do
            case $status in
                "completed:failure")
                    trigger_agent "test-writer-fixer" "ci_failed" "latest_run"
                    trigger_agent "project-shipper" "deployment_blocked" "ci_failure"
                    ;;
                "completed:success")
                    trigger_agent "project-shipper" "deployment_ready" "ci_passed"
                    ;;
                "in_progress:null")
                    trigger_agent "workflow-optimizer" "ci_running" "monitor_performance"
                    ;;
            esac
        done

        sleep 30
    done
}

monitor_performance_metrics() {
    # Monitor application performance
    while true; do
        # Check response times
        response_time=$(curl -w "%{time_total}" -s -o /dev/null https://vs.gvops.cl/api/health)

        if (( $(echo "$response_time > 0.5" | bc -l) )); then
            trigger_agent "backend-architect" "performance_degradation" "response_time:$response_time"
        fi

        # Check error rates
        error_rate=$(tail -n 100 /var/log/nginx/error.log | grep "$(date '+%Y/%m/%d %H:%M')" | wc -l)

        if [ "$error_rate" -gt 5 ]; then
            trigger_agent "test-writer-fixer" "high_error_rate" "errors:$error_rate"
            trigger_agent "project-shipper" "stability_issue" "error_spike"
        fi

        sleep 60
    done
}

# Start all monitors in background
monitor_git_changes &
monitor_ci_status &
monitor_performance_metrics &

# Keep daemon running
wait
```

### **Agent-Specific Triggers**
```php
// PHP trigger system for more complex logic
namespace SGV\Agents\Triggers;

class AgentTriggerManager
{
    private array $activeAgents = [];
    private EventDispatcher $eventDispatcher;

    public function __construct(EventDispatcher $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
        $this->registerTriggers();
    }

    private function registerTriggers(): void
    {
        // Backend Architect Triggers
        $this->eventDispatcher->addListener('code.entity.created', [$this, 'triggerBackendArchitect']);
        $this->eventDispatcher->addListener('api.endpoint.requested', [$this, 'triggerBackendArchitect']);
        $this->eventDispatcher->addListener('database.query.slow', [$this, 'triggerBackendArchitect']);

        // Test Writer Fixer Triggers
        $this->eventDispatcher->addListener('code.coverage.low', [$this, 'triggerTestWriter']);
        $this->eventDispatcher->addListener('test.failure.detected', [$this, 'triggerTestWriter']);
        $this->eventDispatcher->addListener('code.changed', [$this, 'triggerTestWriter']);

        // Project Shipper Triggers
        $this->eventDispatcher->addListener('ci.build.success', [$this, 'triggerProjectShipper']);
        $this->eventDispatcher->addListener('deployment.scheduled', [$this, 'triggerProjectShipper']);
        $this->eventDispatcher->addListener('monitoring.alert', [$this, 'triggerProjectShipper']);

        // Workflow Optimizer Triggers
        $this->eventDispatcher->addListener('sprint.velocity.low', [$this, 'triggerWorkflowOptimizer']);
        $this->eventDispatcher->addListener('team.burnout.detected', [$this, 'triggerWorkflowOptimizer']);
        $this->eventDispatcher->addListener('pattern.extraction.opportunity', [$this, 'triggerWorkflowOptimizer']);
    }

    public function triggerBackendArchitect(Event $event): void
    {
        $context = $event->getContext();

        switch ($event->getType()) {
            case 'code.entity.created':
                $this->activateAgent('backend-architect', [
                    'action' => 'generate_api_endpoints',
                    'entity' => $context['entity_name'],
                    'priority' => 'high'
                ]);
                break;

            case 'database.query.slow':
                $this->activateAgent('backend-architect', [
                    'action' => 'optimize_query',
                    'query' => $context['query'],
                    'execution_time' => $context['time'],
                    'priority' => 'critical'
                ]);
                break;

            case 'api.endpoint.requested':
                $this->activateAgent('backend-architect', [
                    'action' => 'design_api',
                    'endpoint' => $context['endpoint'],
                    'requirements' => $context['requirements'],
                    'priority' => 'medium'
                ]);
                break;
        }
    }

    public function triggerTestWriter(Event $event): void
    {
        $context = $event->getContext();

        switch ($event->getType()) {
            case 'code.changed':
                $changedFiles = $context['files'];
                $testFiles = $this->findRelatedTests($changedFiles);

                $this->activateAgent('test-writer-fixer', [
                    'action' => 'run_impacted_tests',
                    'files' => $changedFiles,
                    'tests' => $testFiles,
                    'priority' => 'high'
                ]);
                break;

            case 'test.failure.detected':
                $this->activateAgent('test-writer-fixer', [
                    'action' => 'fix_failing_tests',
                    'failed_tests' => $context['failed_tests'],
                    'error_messages' => $context['errors'],
                    'priority' => 'critical'
                ]);
                break;

            case 'code.coverage.low':
                $this->activateAgent('test-writer-fixer', [
                    'action' => 'generate_missing_tests',
                    'uncovered_files' => $context['files'],
                    'current_coverage' => $context['coverage'],
                    'target_coverage' => 80,
                    'priority' => 'medium'
                ]);
                break;
        }
    }

    private function activateAgent(string $agentName, array $config): void
    {
        $agent = $this->createAgent($agentName, $config);
        $this->activeAgents[] = $agent;

        // Log activation
        $this->logAgentActivation($agentName, $config);

        // Execute agent asynchronously
        $this->executeAgent($agent);
    }
}
```

## 🔄 Real-Time Monitoring Hooks

### **Git Hooks Integration**
```bash
#!/bin/bash
# .git/hooks/post-commit - Automatic trigger on every commit

echo "🔄 Processing commit triggers..."

# Get changed files
changed_files=$(git diff-tree --no-commit-id --name-only -r HEAD)

# Trigger backend-architect for new entities/controllers
echo "$changed_files" | grep -E "src/(Entity|Controller)/" | while read file; do
    echo "🏗️ Triggering backend-architect for $file"
    php bin/console agents:trigger backend-architect "file_changed" "$file"
done

# Trigger test-writer-fixer for any src changes
if echo "$changed_files" | grep -q "src/"; then
    echo "🧪 Triggering test-writer-fixer for src changes"
    php bin/console agents:trigger test-writer-fixer "code_changed" "$changed_files"
fi

# Trigger workflow-optimizer for pattern analysis
if [ $(echo "$changed_files" | wc -l) -gt 3 ]; then
    echo "⚡ Triggering workflow-optimizer for pattern analysis"
    php bin/console agents:trigger workflow-optimizer "bulk_changes" "$changed_files"
fi
```

### **GitHub Actions Integration**
```yaml
# .github/workflows/agent-triggers.yml
name: Proactive Agent Triggers

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
  schedule:
    - cron: '0 */6 * * *'  # Every 6 hours

jobs:
  trigger-agents:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0  # Full history for pattern analysis

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1

      - name: Install Dependencies
        run: composer install

      - name: Trigger Backend Architect
        if: ${{ github.event_name == 'push' }}
        run: |
          # Check for architectural opportunities
          php bin/console agents:trigger backend-architect "push_event" "${{ github.sha }}"

      - name: Trigger Test Writer Fixer
        run: |
          # Always check test coverage and run tests
          php bin/console agents:trigger test-writer-fixer "ci_run" "${{ github.sha }}"

      - name: Trigger Project Shipper
        if: ${{ github.ref == 'refs/heads/main' }}
        run: |
          # Production deployment readiness
          php bin/console agents:trigger project-shipper "main_branch_update" "${{ github.sha }}"

      - name: Trigger Workflow Optimizer
        if: ${{ github.event_name == 'schedule' }}
        run: |
          # Periodic optimization analysis
          php bin/console agents:trigger workflow-optimizer "periodic_analysis" "scheduled"
```

### **Symfony Console Commands**
```php
// src/Command/AgentTriggerCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AgentTriggerCommand extends Command
{
    protected static $defaultName = 'agents:trigger';

    protected function configure(): void
    {
        $this
            ->setDescription('Trigger a specific agent with context')
            ->addArgument('agent', InputArgument::REQUIRED, 'Agent to trigger')
            ->addArgument('event', InputArgument::REQUIRED, 'Event type')
            ->addArgument('context', InputArgument::OPTIONAL, 'Event context');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agent = $input->getArgument('agent');
        $event = $input->getArgument('event');
        $context = $input->getArgument('context') ?? '';

        $output->writeln("🤖 Triggering $agent for event: $event");

        switch ($agent) {
            case 'backend-architect':
                $this->triggerBackendArchitect($event, $context, $output);
                break;
            case 'test-writer-fixer':
                $this->triggerTestWriterFixer($event, $context, $output);
                break;
            case 'project-shipper':
                $this->triggerProjectShipper($event, $context, $output);
                break;
            case 'workflow-optimizer':
                $this->triggerWorkflowOptimizer($event, $context, $output);
                break;
            default:
                $output->writeln("❌ Unknown agent: $agent");
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function triggerBackendArchitect(string $event, string $context, OutputInterface $output): void
    {
        switch ($event) {
            case 'file_changed':
                if (str_contains($context, 'Entity')) {
                    $output->writeln("🏗️ Generating API endpoints for entity");
                    // TODO: Auto-generate CRUD API
                } elseif (str_contains($context, 'Controller')) {
                    $output->writeln("🏗️ Analyzing API patterns");
                    // TODO: Analyze and suggest improvements
                }
                break;

            case 'performance_issue':
                $output->writeln("🏗️ Analyzing performance bottleneck");
                // TODO: Suggest optimizations
                break;
        }
    }

    private function triggerTestWriterFixer(string $event, string $context, OutputInterface $output): void
    {
        switch ($event) {
            case 'code_changed':
                $output->writeln("🧪 Running impacted tests");
                // TODO: Smart test selection and execution
                break;

            case 'ci_run':
                $output->writeln("🧪 Full test suite analysis");
                // TODO: Coverage analysis and test generation
                break;
        }
    }
}
```

## 📊 Trigger Analytics & Learning

### **Trigger Effectiveness Tracking**
```php
// Track trigger effectiveness and optimize
class TriggerAnalytics
{
    public function trackTrigger(string $agent, string $event, array $context): void
    {
        $trigger = new AgentTrigger([
            'agent' => $agent,
            'event' => $event,
            'context' => $context,
            'timestamp' => new \DateTime(),
            'success' => null,
            'execution_time' => null,
            'value_generated' => null
        ]);

        $this->entityManager->persist($trigger);
        $this->entityManager->flush();
    }

    public function analyzeTriggerEffectiveness(): TriggerReport
    {
        // Analyze which triggers provide most value
        $stats = $this->repository->createQueryBuilder('t')
            ->select('t.agent, t.event, COUNT(t) as trigger_count, AVG(t.value_generated) as avg_value')
            ->groupBy('t.agent, t.event')
            ->getQuery()
            ->getResult();

        return new TriggerReport($stats);
    }

    public function optimizeTriggers(): void
    {
        $report = $this->analyzeTriggerEffectiveness();

        // Disable low-value triggers
        // Enhance high-value triggers
        // Suggest new trigger opportunities
    }
}
```

## 🎯 Success Metrics

### **Trigger Performance KPIs**
- ✅ **Response Time**: Triggers activate within 30 seconds
- ✅ **Accuracy**: 95% of triggers are relevant and actionable
- ✅ **Value Generation**: Each trigger saves 15+ minutes of manual work
- ✅ **Coverage**: 90% of development events have appropriate triggers

### **Agent Activation Patterns**
- **Backend Architect**: 10-15 activations/day (entity changes, performance issues)
- **Test Writer Fixer**: 20-30 activations/day (code changes, CI runs)
- **Project Shipper**: 3-5 activations/day (deployments, monitoring alerts)
- **Workflow Optimizer**: 2-3 activations/day (pattern analysis, team metrics)

---
**System Motto**: "Anticipate, Activate, Accelerate - Autonomous development velocity"
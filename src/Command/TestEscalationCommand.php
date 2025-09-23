<?php

namespace App\Command;

use App\Service\AlertEscalationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-escalation',
    description: 'Test the alert escalation workflow'
)]
class TestEscalationCommand extends Command
{
    public function __construct(
        private AlertEscalationService $escalationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', null, InputOption::VALUE_OPTIONAL, 'Action to perform (create, process, stats)', 'create')
            ->addOption('severity', null, InputOption::VALUE_OPTIONAL, 'Alert severity (critical, high, medium, low)', 'high')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Alert title', 'Test Alert')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $severity = $input->getOption('severity');
        $title = $input->getOption('title');

        $io->title('Alert Escalation System Test');

        switch ($action) {
            case 'create':
                return $this->testCreateAlert($io, $severity, $title);
            case 'process':
                return $this->testProcessEscalations($io);
            case 'stats':
                return $this->showEscalationStats($io);
            default:
                $io->error("Unknown action: $action");
                return Command::FAILURE;
        }
    }

    private function testCreateAlert(SymfonyStyle $io, string $severity, string $title): int
    {
        $io->section('Creating Test Alert');

        try {
            $alert = $this->escalationService->createAlert(
                $title,
                'This is a test alert created to verify the escalation workflow system is working correctly.',
                $severity,
                'system',
                'test',
                'escalation-test',
                [
                    'test' => true,
                    'created_by' => 'console-command',
                    'test_timestamp' => time()
                ]
            );

            $io->success("✅ Alert created successfully!");
            $io->table(['Property', 'Value'], [
                ['ID', $alert->getId()],
                ['Title', $alert->getTitle()],
                ['Severity', $alert->getSeverity()],
                ['Status', $alert->getStatus()],
                ['Escalation Level', $alert->getEscalationLevel()],
                ['Age (minutes)', $alert->getAgeInMinutes()],
                ['Priority Score', $alert->getPriorityScore()],
                ['Created At', $alert->getCreatedAt()->format('Y-m-d H:i:s')]
            ]);

            $io->note('The alert has been created and initial escalation notifications have been sent.');
            $io->note('Run "app:test-escalation --action=process" to test escalation processing.');

        } catch (\Exception $e) {
            $io->error("❌ Failed to create alert: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function testProcessEscalations(SymfonyStyle $io): int
    {
        $io->section('Processing Escalations');

        try {
            $results = $this->escalationService->processEscalations();

            if (empty($results)) {
                $io->warning('⚠️ No escalations were processed. This could mean:');
                $io->listing([
                    'No active alerts exist',
                    'No alerts are overdue for escalation',
                    'All alerts have been acknowledged or resolved'
                ]);
            } else {
                $io->success("✅ Processed " . count($results) . " escalations");

                foreach ($results as $result) {
                    $io->writeln("📋 Alert #{$result['alert_id']} escalated to level {$result['level']}");
                    $io->writeln("   Recipients: " . implode(', ', $result['recipients']));
                    $io->writeln("   Channels: " . implode(', ', $result['channels']));
                    $io->writeln("   Reason: {$result['reason']}");
                    $io->writeln('');
                }
            }

        } catch (\Exception $e) {
            $io->error("❌ Failed to process escalations: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showEscalationStats(SymfonyStyle $io): int
    {
        $io->section('Escalation Statistics');

        try {
            $stats = $this->escalationService->getEscalationStats();

            $io->table(['Metric', 'Value'], [
                ['Active Alerts', $stats['active_alerts']],
                ['Acknowledged Alerts', $stats['acknowledged_alerts']],
                ['Resolved Today', $stats['resolved_today']],
                ['Average Resolution Time (minutes)', number_format($stats['avg_resolution_time'], 2)]
            ]);

            $io->section('Severity Breakdown');
            $severityData = [];
            foreach ($stats['severity_breakdown'] as $severity => $count) {
                $severityData[] = [ucfirst($severity), $count];
            }
            $io->table(['Severity', 'Active Count'], $severityData);

            $io->section('Escalation Levels');
            $escalationData = [];
            foreach ($stats['escalation_levels'] as $level => $count) {
                $levelNum = str_replace('level_', '', $level);
                $escalationData[] = ["Level $levelNum", $count];
            }
            $io->table(['Escalation Level', 'Alert Count'], $escalationData);

        } catch (\Exception $e) {
            $io->error("❌ Failed to get escalation stats: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
<?php

namespace App\Console\Commands;

use App\Services\GeminiDiagnosticService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gemini:diagnose {--model=gemini-3.8-flash : Target Gemini model} {--key= : Explicit API Key override} {--level=high : Thinking level (minimal, low, medium, high)} {--provider-id= : Specific provider ID} {--json : Output raw JSON report}')]
#[Description('Execute deep diagnostics for Gemini 3 models, tool calling, and stateful thought signatures')]
class DiagnoseGemini extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GeminiDiagnosticService $diagnosticService): int
    {
        $model = (string) ($this->option('model') ?: 'gemini-3.8-flash');
        $apiKey = (string) ($this->option('key') ?: '');
        $thinkingLevel = (string) ($this->option('level') ?: 'high');
        $providerId = $this->option('provider-id') ? (int) $this->option('provider-id') : null;

        if (! $this->option('json')) {
            $this->newLine();
            $this->info('========================================================================');
            $this->info('           Gemini 3 Diagnostics & Thought Signature Inspector          ');
            $this->info('========================================================================');
            $this->line("Target Model   : <comment>{$model}</comment>");
            $this->line("Thinking Level : <comment>{$thinkingLevel}</comment>");
            $this->line('Running live connectivity, function calling, and stateful round-trip tests...');
            $this->newLine();
        }

        $report = $diagnosticService->runDiagnostics([
            'model' => $model,
            'api_key' => $apiKey ?: null,
            'provider_id' => $providerId,
            'thinking_level' => $thinkingLevel,
        ]);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['summary']['all_passed'] ? self::SUCCESS : self::FAILURE;
        }

        // 1. Connectivity Check
        $conn = $report['checks']['connectivity'];
        $this->renderCheck('1. Basic Connectivity & Generation', $conn['status'], $conn['latency_ms'] ?? 0, $conn['error'] ?? null);
        if ($conn['status'] === 'passed') {
            $this->line('   Reply: <info>'.substr($conn['reply'] ?? '', 0, 80).'</info>');
        }

        // 2. Tool Calling & Thought Signature
        $tc = $report['checks']['tool_call'];
        $this->renderCheck('2. Function Calling & Thought Signature', $tc['status'], $tc['latency_ms'] ?? 0, $tc['error'] ?? null);
        if ($tc['status'] === 'passed') {
            $sigStatus = $tc['has_thought_signature'] ? "<info>Detected ({$tc['thought_signature_length']} bytes, {$tc['thought_signature_source']})</info>" : '<error>Missing</error>';
            $this->line('   Tool Invoked      : <comment>'.($tc['tool_invoked'] ?? 'none').'</comment>');
            $this->line("   Thought Signature : {$sigStatus}");
            if ($tc['has_thought_signature']) {
                $this->line("   Signature Preview : <comment>{$tc['thought_signature_preview']}</comment>");
            }
            if ($tc['has_reasoning_block']) {
                $this->line("   Reasoning Preview : <comment>{$tc['reasoning_preview']}</comment>");
            }
        }

        // 3. Multi-Turn Stateful Round-Trip
        $rt = $report['checks']['round_trip'];
        $this->renderCheck('3. Stateful Round-Trip (Coupled Tool Response)', $rt['status'], $rt['latency_ms'] ?? 0, $rt['error'] ?? null);
        if ($rt['status'] === 'passed') {
            $this->line('   Synthesized Output: <info>'.substr(trim($rt['synthesized_response'] ?? ''), 0, 100).'...</info>');
        }

        $this->newLine();
        $this->info('========================================================================');
        if ($report['summary']['all_passed']) {
            $this->info('RESULT: ALL DIAGNOSTIC CHECKS PASSED SUCCESSFULLY (Gemini 3 Ready)');
        } else {
            $this->error('RESULT: DIAGNOSTIC FAILED - ISSUES DETECTED');
            foreach ($report['summary']['issues'] as $issue) {
                $this->line(" - <error>Issue:</error> {$issue}");
            }
            foreach ($report['summary']['recommendations'] as $rec) {
                $this->line(" - <comment>Fix:</comment> {$rec}");
            }
        }
        $this->info('========================================================================');
        $this->newLine();

        return $report['summary']['all_passed'] ? self::SUCCESS : self::FAILURE;
    }

    protected function renderCheck(string $title, string $status, int $latencyMs, ?string $error): void
    {
        $statusBadge = match ($status) {
            'passed' => '<bg=green;fg=black> PASSED </>',
            'failed' => '<bg=red;fg=white> FAILED </>',
            default => '<bg=yellow;fg=black> SKIPPED </>'
        };

        $this->line("{$statusBadge} <bold>{$title}</bold> ({$latencyMs}ms)");
        if ($error) {
            $this->line("   <error>Error:</error> {$error}");
        }
    }
}

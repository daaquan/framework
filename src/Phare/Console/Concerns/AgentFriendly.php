<?php

namespace Phare\Console\Concerns;

use Symfony\Component\Console\Input\InputOption;

/**
 * AgentFriendly — Netlify-style AI agent support for CLI commands.
 *
 * When a command uses this trait it gains:
 *   --json          Output results as JSON (machine-readable).
 *   --no-interactive  Skip all interactive prompts; fail instead of blocking.
 *
 * Pattern inspired by QCon London 2026 / Netlify CLI redesign for AI agents.
 *
 * @see https://www.infoq.com/news/2026/03/qcon-next-developers/
 */
trait AgentFriendly
{
    /** Buffer for data to be emitted as JSON at the end of execute(). */
    private array $_agentData = [];

    private bool $_agentJsonMode = false;

    private bool $_agentNoInteractive = false;

    /** Call once inside configure() to register the agent options. */
    protected function configureAgentOptions(): void
    {
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            'Output result as JSON (for AI agents and scripts)'
        );
        $this->addOption(
            'no-interactive',
            null,
            InputOption::VALUE_NONE,
            'Disable all interactive prompts (CI / AI agent mode)'
        );
    }

    /** Call at the start of execute() / handle() to initialise flags. */
    protected function initAgentMode(): void
    {
        $this->_agentJsonMode = (bool)($this->input->getOption('json') ?? false);
        $this->_agentNoInteractive = (bool)($this->input->getOption('no-interactive') ?? false);
    }

    /** Returns true when --json was passed. */
    protected function isJsonMode(): bool
    {
        return $this->_agentJsonMode;
    }

    /** Returns true when --no-interactive was passed. */
    protected function isNonInteractive(): bool
    {
        return $this->_agentNoInteractive;
    }

    /**
     * In non-interactive mode: fail with an error instead of prompting.
     * In interactive mode: delegate to the parent confirm().
     */
    protected function confirmOrFail(string $text, string $hint = ''): bool
    {
        if ($this->isNonInteractive()) {
            $msg = "Interactive prompt skipped in --no-interactive mode: {$text}";
            if ($hint !== '') {
                $msg .= " ({$hint})";
            }
            $this->agentError($msg, 'PROMPT_REQUIRED');

            return false;
        }

        return $this->confirm($text);
    }

    /**
     * Store a key/value pair that will be included in --json output.
     */
    protected function agentSet(string $key, mixed $value): void
    {
        $this->_agentData[$key] = $value;
    }

    /**
     * Emit JSON output if --json mode is active, otherwise write a human
     * readable info line.
     *
     * @param string $message Human-readable success message.
     * @param array $data Additional fields merged into the JSON envelope.
     */
    protected function agentSuccess(string $message, array $data = []): void
    {
        $payload = array_merge(
            ['status' => 'success', 'message' => $message],
            $this->_agentData,
            $data
        );

        if ($this->isJsonMode()) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info($message);
        }
    }

    /**
     * Emit a structured error (JSON or human-readable).
     *
     * @param string $code Machine-readable error code (e.g. 'PROMPT_REQUIRED').
     */
    protected function agentError(string $message, string $code = 'ERROR', array $data = []): void
    {
        $payload = array_merge(
            ['status' => 'error', 'code' => $code, 'message' => $message],
            $data
        );

        if ($this->isJsonMode()) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($message);
        }
    }

    /**
     * Dump any buffered agent data as JSON.
     * Call at the end of handle() if you used agentSet() directly.
     */
    protected function flushAgentJson(): void
    {
        if ($this->isJsonMode() && !empty($this->_agentData)) {
            $this->line(json_encode(
                array_merge(['status' => 'ok'], $this->_agentData),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        }
    }
}

<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;
use Phare\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'key:generate', description: 'Generate the application key.')]
class KeyGenerateCommand extends Command
{
    public function handle(): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            $this->output->writeError('.env file not found.');

            return;
        }

        $envContent = file_get_contents($envPath) ?: '';

        // Replace the APP_KEY line
        if (preg_match('/^APP_KEY=.*$/m', $envContent, $matches)) {
            $newKey = $this->generateKey();
            if (!$newKey) {
                $this->output->writeError('Failed to generate application key.');

                return;
            }

            $envContent = str_replace($matches[0], 'APP_KEY=base64:' . $newKey, $envContent);
            file_put_contents($envPath, $envContent);

            $this->output->writeInfo('Application key set successfully.');
        } else {
            $this->output->writeError('APP_KEY not found in .env file.');
        }
    }

    private function generateKey(): ?string
    {
        try {
            return base64_encode(random_bytes(32));
        } catch (\Throwable $e) {
            Log::error($e->getMessage());

            return null;
        }
    }
}

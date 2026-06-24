<?php

namespace App\Console\Commands\Deploy;

use App\Utils\Str;
use Dotenv\Dotenv;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('deploy:generate-k8s-yml')]
#[Description('Command description')]
class GenerateK8sYml extends Command
{
    protected $useDefaultValues = false;

    protected string $envFile;

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle()
    {
        $this->selectEnvFile();
        if (!isset($this->envFile)) {
            return Command::FAILURE;
        }

        if (!$this->confirm('Use '.$this->envFile.'?', true)) {
            return Command::FAILURE;
        }

        // Check deploy.yml
        $deployVersion = preg_replace('/^.env/', '', $this->envFile);
        $deployVersion = preg_replace('/.deploy$/', '', $deployVersion);
        $deployYmlPath = base_path('k8s/deploy'.$deployVersion.'.yml');
        $filePointer = fopen($deployYmlPath, 'w') or die('Unable to open '.$deployYmlPath);

        $this->useDefaultValues = $this->confirm('Use default value?', true);

        // K8s config
        $k8sConfig = [
            'APP_HOST' => $this->askDeployEnv('APP_HOST'),
            'K8S_NAMESPACE' => $this->askDeployEnv('K8S_NAMESPACE', 'freeqr'),
            'IMAGE_PULL_POLICY' => $this->askDeployEnv('IMAGE_PULL_POLICY', 'IfNotPresent'),
            'PHP_IMAGE' => $this->askDeployEnv('PHP_IMAGE'),

            'NODE_LABEL_SELECTOR_KEY' => $this->askDeployEnv('NODE_LABEL_SELECTOR_KEY'),
            'WEB_DEPLOYMENT_NODE_LABEL_SELECTOR_VALUE' => $this->askDeployEnv('WEB_DEPLOYMENT_NODE_LABEL_SELECTOR_VALUE'),
            'SCHEDULER_DEPLOYMENT_NODE_LABEL_SELECTOR_VALUE' => $this->askDeployEnv('SCHEDULER_DEPLOYMENT_NODE_LABEL_SELECTOR_VALUE'),
            'WORKER_DEPLOYMENT_NODE_LABEL_SELECTOR_VALUE' => $this->askDeployEnv('WORKER_DEPLOYMENT_NODE_LABEL_SELECTOR_VALUE'),

            'WEB_MIN_REPLICAS' => $this->askDeployEnv('WEB_MIN_REPLICAS', 1),
            'WEB_MAX_REPLICAS' => $this->askDeployEnv('WEB_MAX_REPLICAS', 100),
            'WEB_CPU_REQUEST' => $this->askDeployEnv('WEB_CPU_REQUEST', 0.3),
            'WEB_MEMORY_REQUEST' => $this->askDeployEnv('WEB_MEMORY_REQUEST', '600M'),
            'WEB_CPU_LIMIT' => $this->askDeployEnv('WEB_CPU_LIMIT', 0.5),
            'WEB_MEMORY_LIMIT' => $this->askDeployEnv('WEB_MEMORY_LIMIT', '850M'),
            'WEB_AVERAGE_CPU' => $this->askDeployEnv('WEB_AVERAGE_CPU', 70),
            'WEB_AVERAGE_MEMORY' => $this->askDeployEnv('WEB_AVERAGE_MEMORY', 70),

            'SCHEDULER_CPU_REQUEST' => $this->askDeployEnv('SCHEDULER_CPU_REQUEST', 0.1),
            'SCHEDULER_MEMORY_REQUEST' => $this->askDeployEnv('SCHEDULER_MEMORY_REQUEST', '200M'),
            'SCHEDULER_CPU_LIMIT' => $this->askDeployEnv('SCHEDULER_CPU_LIMIT', 0.2),
            'SCHEDULER_MEMORY_LIMIT' => $this->askDeployEnv('SCHEDULER_MEMORY_LIMIT', '512M'),

            'WORKER_CPU_REQUEST' => $this->askDeployEnv('WORKER_CPU_REQUEST', 0.1),
            'WORKER_MEMORY_REQUEST' => $this->askDeployEnv('WORKER_MEMORY_REQUEST', '200M'),
            'WORKER_CPU_LIMIT' => $this->askDeployEnv('WORKER_CPU_LIMIT', 0.2),
            'WORKER_MEMORY_LIMIT' => $this->askDeployEnv('WORKER_MEMORY_LIMIT', '512M'),

            'WORKER_ARTICLE_BUILDING_REPLICAS' => $this->askDeployEnv('WORKER_ARTICLE_BUILDING_REPLICAS', 1),
            'WORKER_DEFAULT_REPLICAS' => $this->askDeployEnv('WORKER_DEFAULT_REPLICAS', 1),
            'WORKER_FILE_SCRAPING_REPLICAS' => $this->askDeployEnv('WORKER_FILE_SCRAPING_REPLICAS', 1),
            'WORKER_KEYWORD_RESEARCHING_REPLICAS' => $this->askDeployEnv('WORKER_KEYWORD_RESEARCHING_REPLICAS', 1),
            'WORKER_PAGE_SCRAPING_REPLICAS' => $this->askDeployEnv('WORKER_PAGE_SCRAPING_REPLICAS', 1),
            'WORKER_PUBLISHING_REPLICAS' => $this->askDeployEnv('WORKER_PUBLISHING_REPLICAS', 1),
            'WORKER_SCHEDULER_REPLICAS' => $this->askDeployEnv('WORKER_SCHEDULER_REPLICAS', 1),
        ];

        $secret = [
            'APP_KEY' => $this->askDeployEnv('APP_KEY'),
            'SENTRY_LARAVEL_DSN' => $this->askDeployEnv('SENTRY_LARAVEL_DSN', null, false),

            // DB
            'DB_HOST' => $this->askDeployEnv('DB_HOST'),
            'DB_PORT' => $this->askDeployEnv('DB_PORT', 3306),
            'DB_DATABASE' => $this->askDeployEnv('DB_DATABASE'),
            'DB_USERNAME' => $this->askDeployEnv('DB_USERNAME'),
            'DB_PASSWORD' => $this->askDeployEnv('DB_PASSWORD'),

            // Redis
            'REDIS_HOST' => $this->askDeployEnv('REDIS_HOST'),
            'REDIS_USERNAME' => $this->askDeployEnv('REDIS_USERNAME', '', false),
            'REDIS_PASSWORD' => $this->askDeployEnv('REDIS_PASSWORD'),
            'REDIS_PORT' => $this->askDeployEnv('REDIS_PORT'),

            // AWS
            'AWS_ACCESS_KEY_ID' => $this->askDeployEnv('AWS_ACCESS_KEY_ID', null, false),
            'AWS_SECRET_ACCESS_KEY' => $this->askDeployEnv('AWS_SECRET_ACCESS_KEY', null, false),
            'AWS_DEFAULT_REGION' => $this->askDeployEnv('AWS_DEFAULT_REGION', null, false),
            'AWS_BUCKET' => $this->askDeployEnv('AWS_BUCKET'),
            'AWS_USE_PATH_STYLE_ENDPOINT' => $this->askDeployEnv('AWS_USE_PATH_STYLE_ENDPOINT'),
            'AWS_ENDPOINT' => $this->askDeployEnv('AWS_ENDPOINT'),
        ];

        $configmap = collect($this->parseEnvFile())
            ->filter(function ($value, $key) use ($secret, $k8sConfig) {
                return !in_array($key, array_merge(array_keys($secret), array_keys($k8sConfig)));
            })->toArray();

        // Preview k8s config
        $this->info('K8s config preview:');
        foreach ($k8sConfig as $key => $value) {
            $this->line($key.': '.$value);
        }
        $this->info('----------');

        // Preview config
        $this->info('Configmap preview:');
        foreach ($configmap as $key => $value) {
            $this->line($key.': '.$value);
        }
        $this->info('----------');

        // Preview secret
        $this->info('Secret preview:');
        foreach ($secret as $key => $value) {
            $this->line($key.': '.$value);
        }

        // Confirm build
        if (!$this->confirm('Build deploy.yml?', true)) {
            fclose($filePointer);
            return Command::SUCCESS;
        }

        // Merge configmap & secret
        $config = array_merge($k8sConfig, $configmap, collect($secret)->mapWithKeys(function ($value, $key) {
            return [$key => base64_encode($value)];
        })->toArray());

        // Build file
        $deploy = [];
        $path = base_path('k8s/templates');
        foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ) ) as $file ) {
            $filePath = $file->getPathname();
            if (!preg_match('/.yml$/', $filePath)) {
                continue;
            }

            $fileContents = file_get_contents($filePath);

            // Replace env data
            $envPlaceholder = '#auto-env-filling#';
            if (Str::contains($fileContents, $envPlaceholder)) {
                $envYml = "\n";
                foreach ($configmap as $key => $value) {
                    $envYml .= '  '.$key.': "'.$value.'"'."\n";
                }
                $fileContents = str_replace($envPlaceholder, $envYml, $fileContents);
            }

            $this->line('Parsing file: ' . $filePath);
            $deploy[substr($filePath, strlen($path) + 1)] = str_replace(
                collect(array_keys($config))->map(fn ($key) => '{{ '.$key.' }}')->toArray(),
                array_values($config),
                $fileContents
            );
        }

        // Push the namespace to the top
        $namespace = $deploy['namespace.yml'];
        unset($deploy['namespace.yml']);
        $deploy = array_merge(['namespace' => $namespace], $deploy);

        fwrite($filePointer, implode("\n---\n", $deploy));
        fclose($filePointer);

        $this->info('K8s file is generated successfully at: '.$deployYmlPath);

        return Command::SUCCESS;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @param bool $required
     * @return string
     */
    protected function askDeployEnv(string $key, mixed $default = null, bool $required = true): string
    {
        $deployEnvData = $this->parseEnvFile();

        $default = $deployEnvData[$key] ?? $default;

        if ($this->useDefaultValues and $default) {
            $value = $default;
        } else {
            $value = $this->ask($key, $default);
        }

        if (is_null($value) and $required) {
            throw new \Exception($key.' is required.');
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    protected function parseEnvFile(): array
    {
        return Dotenv::parse(file_get_contents(base_path($this->envFile)));
    }

    protected function selectEnvFile(): void
    {
        // Check .env.deploy
        $scanEnvFiles = scandir(base_path());
        $deployEnvFiles = [];
        foreach ($scanEnvFiles as $file) {
            if (preg_match('/^.env/', $file) and preg_match('/.deploy$/i', $file)) {
                $deployEnvFiles[] = $file;
            }
        }

        if (!$deployEnvFilesCount = count($deployEnvFiles)) {
            $this->error('.env.deploy file not found.');
            return;
        }

        if ($deployEnvFilesCount > 1) {
            $this->info('List of deploy env files:');
            foreach ($deployEnvFiles as $index => $file) {
                $this->line($index.': '.$file);
            }
            $this->envFile = $deployEnvFiles[$this->ask('Select env file')] ?? null;
        } else {
            $this->envFile = $deployEnvFiles[0];
        }

        if (!$this->envFile) {
            throw new \Exception('Invalid env file.');
        }
    }
}

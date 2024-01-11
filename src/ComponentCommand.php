<?php

namespace LamaLama\Clli\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use LamaLama\Clli\Console\Services\GitHubAuthException;
use LamaLama\Clli\Console\Services\GitHubClient;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ComponentCommand extends Command
{
    use Concerns\ConfiguresPrompts;

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected ?string $componentType;
    protected string $tokenLocation;
    protected ?string $rename = null;
    protected array $componentTypes = [
        'section',
        'block',
        'part',
        'template',
    ];

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('component')
            ->setDescription('Install a LamaPress component in your project ')
            ->addArgument('type', InputArgument::OPTIONAL);
    }

    /**
     * Interact with the user before validating the input.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);
        $homePath = exec('cd ~ && pwd');
        $this->tokenLocation = "$homePath/.clli/config.json";

        $this->configurePrompts($input, $output);

        $output->write(PHP_EOL.'<fg=white>██       █████  ███    ███  █████  ██████  ██████  ███████ ███████ ███████ 
██      ██   ██ ████  ████ ██   ██ ██   ██ ██   ██ ██      ██      ██      
██      ███████ ██ ████ ██ ███████ ██████  ██████  █████   ███████ ███████ 
██      ██   ██ ██  ██  ██ ██   ██ ██      ██   ██ ██           ██      ██ 
███████ ██   ██ ██      ██ ██   ██ ██      ██   ██ ███████ ███████ ███████ '.PHP_EOL.PHP_EOL);

        $this->componentType = $input->getArgument('type');
        if (!$this->componentType) {
            $this->componentType = select('What kind of component would you like to install', $this->componentTypes);

        }

        if (!in_array($this->componentType, $this->componentTypes)) {
            $output->write( "$this->componentType is not a valid component type.");
            die('');
        }

        // TODO: Use lamapress.config.cjs
        if (!file_exists("vite.config.js")) {
            // Attempt to create the directory
            if (!confirm(
                label: "I don't see no vite.config.js file so i guess the current directory is not the Lamapress there root folder. Are you sure you want to continue?",
                default: false,
                yes: "Yes, i'm sure",
                no: 'Quit',
                hint: 'If you proceed in the wrong folder, the files will be generated in the wrong place')) {

                die('Canceled');
            }
        }
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $githubToken = $this->getToken();
        $github = new GitHubClient($githubToken);
        $componentTypeFolder = Str::plural($this->componentType);
        try {
            $components = $github->listFilesInDirectory('lamalamaNL/lamapress', 'components/'.$componentTypeFolder);
        } catch (GitHubAuthException $e) {
            $output->writeln('Github Authorisation failed. Please provide valid token on next run');
            $this->removeToken();
            die('');
        }
        $components = collect($components)->filter(fn($f) => substr($f, 0, 1) !== '.')->values();

        $component = select('Which component would you like to install', $components);

        // TODO: Give option to rename the component
        // TODO: Store your token

        if (confirm('Would you like to rename the component?')) {
            $this->rename = text(
                label: 'Component name?',
                required: true,
                validate: function (string $value) {
                    if (str_contains($value, ' ')) {
                        return 'The component name can not contain a space. Please use snake case';
                    }
                    return null;
                }
            );
        }

        $componentFolderPath = "components/$componentTypeFolder/$component";
        $componentFiles = $github->listFilesInDirectory('lamalamaNL/lamapress', $componentFolderPath);
        if ($this->rename) {
            $componentFolderPath = $this->renameComponentPath($componentFolderPath);
        }
        if (!file_exists($componentFolderPath)) {
            // Attempt to create the directory
            mkdir($componentFolderPath, 0777, true);
        }
        foreach ($componentFiles as $componentFile) {
            $content = $github->downloadFile('lamalamaNL/lamapress', "$componentFolderPath/$componentFile");

            if (file_exists("$componentFolderPath/$componentFile")) {
                if (!confirm("$componentFolderPath/$componentFile already exitsts. Overwrite it?")) {
                    continue;
                }
            }
            if (strtolower($componentFile) === 'acf.php') {
               $content = $this->randomizeAcfKeys($content);
            }
            $output->writeln('Generated: ' . "$componentFolderPath/$componentFile");
            file_put_contents("$componentFolderPath/$componentFile", $content);
        }

        return 0;
    }

    private function randomizeAcfKeys($content)
    {
        $hasKeypattern = '/\$key\s*=/i';
        if (preg_match($hasKeypattern, $content) === false) {
            return $content;
        }

        $replacePattern = '/(\$key\s*=\s*\')([^\']*)(\')/';
        $replacement = '${1}'.Str::random(12).'${3}';
        $content = preg_replace($replacePattern, $replacement, $content);

        return $content;
    }

    private function renameComponentPath($componentFolderPath)
    {
        $baseName = pathinfo($componentFolderPath)['basename'];
        $newPath = str_replace($baseName, Str::snake($this->rename), $componentFolderPath);
        return $newPath;
    }

    private function getToken() :?string
    {
        $config = $this->getConfig();
        if (!$config['github-token']) {
            $token = text('No github token available. Please provide a github API token: ');
            if ($token) {
                $this->storeToken($token);
                return $token;
            }
        }
        return $config['github-token'] ?? null;
    }

    private function storeToken($token)
    {
        $config = $this->getConfig();
        $config['github-token'] = $token;
        file_put_contents($this->tokenLocation, json_encode($config));
    }

    private function removeToken()
    {
        $config = $this->getConfig();
        $config['github-token'] = null;
        file_put_contents($this->tokenLocation, json_encode($config));
    }

    private function getConfig() : array
    {
        $dir = pathinfo($this->tokenLocation)['dirname'];
        if(!file_exists($dir)) {
            mkdir($dir);
        }

        if(!file_exists($this->tokenLocation)) {
            return [];
        }
        $rawConfig = file_get_contents($this->tokenLocation);
        if(!$rawConfig) {
            return [];
        }

        $config = json_decode($rawConfig, true);
        return $config ?? [];
    }
}

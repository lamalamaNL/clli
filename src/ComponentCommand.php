<?php

namespace LamaLama\Clli\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
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
    protected ?string $rename;
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
//            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
//            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', $this->defaultBranch())
//            ->addOption('github', null, InputOption::VALUE_OPTIONAL, 'Create a new repository on GitHub', false)
//            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'The GitHub organization to create the new repository for')
//            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
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

        if (!file_exists("vite.config.js")) {
            // Attempt to create the directory
            if (!confirm(
                label: "I don't see no vite.config.js file so i guess the current directory is not the Lamapress there root folder. Are you sure you want to continue?",
                default: false,
                yes: "Yes, i'm sure",
                no: 'Quit',
                hint: 'If you proceed in the wrong folder, the files will be genereted')) {

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
        $github = new GitHubClient('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');
        $componentTypeFolder = Str::plural($this->componentType);
        $components = $github->listFilesInDirectory('lamalamaNL/lamapress', 'components/'.$componentTypeFolder);
        $components = collect($components)->filter(fn($f) => substr($f, 0, 1) !== '.')->values();

        $component = select('Which component would you like to install', $components);
        $output->writeln('$component: ' . $component);

        // TODO: Generate keys for acf fields
        // TODO: Give option to rename the component
        // TODO: Store your token

        $componentFolderPath = "components/$componentTypeFolder/$component";
        $componentFiles = $github->listFilesInDirectory('lamalamaNL/lamapress', $componentFolderPath);
        if (!file_exists($componentFolderPath)) {
            // Attempt to create the directory
            mkdir($componentFolderPath, 0777, true);
        }
        foreach ($componentFiles as $componentFile) {
            $content = $github->downloadFile('lamalamaNL/lamapress', "$componentFolderPath/$componentFile");
            var_dump("$componentFolderPath/$componentFile");
            if (file_exists("$componentFolderPath/$componentFile")) {
                if (!confirm("$componentFolderPath/$componentFile already exitsts. Overwrite it?")) {
                    continue;
                }
            }
            if (strtolower($componentFile) === 'acf.php') {
               $content = $this->randomizeAcfKeys($content);
            }
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

}

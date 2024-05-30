<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use LamaLama\Clli\Console\Services\CliConfig;
use Laravel\Forge\Forge;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\text;

class CreateStaging extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;
    protected InputInterface $input;
    protected OutputInterface $output;
    protected CliConfig $cfg;
    protected Forge $forge;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('lamapress:staging')
//            ->addArgument('key', InputArgument::REQUIRED)
            ->setDescription('Create a staging environment and install wordpress for this project on a forge server.');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);
        $this->input = $input;
        $this->output = $output;


        $output->write('<fg=white>
 ░▒▓██████▓▒░░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
 ░▒▓██████▓▒░░▒▓████████▓▒░▒▓████████▓▒░▒▓█▓▒░'.PHP_EOL.PHP_EOL);

//        if (! $input->getArgument('key')) {
//            $input->setArgument('key', text(
//                label: 'What is the key of your Figma file?',
//                placeholder: 'E.g. 6sftwMKT80KxNnVjS9cZcX',
//                required: 'The file key is required.',
//                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
//                    ? 'The key may only contain letters, numbers, dashes, underscores, and periods.'
//                    : null,
//            ));
//        }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {


        $this->cfg = new CliConfig();
        $this->forge = new Forge($this->getForgeToken());
        $subdomain = 'test-staging';
        $serverId = '525126';

        # Setup the site
        $sites = $this->forge->sites($serverId);
        var_dump($sites);
        die('test');



        // Create site
        $output->writeln('Create site in forge');
        $site = $forge->createSite($serverId, [
            "domain" => $subdomain . ".lamalama.dev",
            "project_type" => "php",
            "aliases" => [],
            "directory" => "/public",
            "isolated" => true,
            "username" => false,
            "database" => $db,
            "php_version" => "php81"
        ]);

        // Create a database
        $db = "db_$subdomain";
        $db_user = "db_user_$subdomain";
        $db_password = Str::random(14);
        $forge->createDatabase($serverId, [
            'name' => $db,
            'user' => $db_user,
            'password' => $db_password,
        ]);


        # Install wordpress
        $localCommands = [
            'forge login --token='.$token,
            'forge server:switch lamalama-dev-2',
            ''
        ];

        $remoteCommands = [
            'forge login --token='.$token,
            'forge server:switch lamalama-dev-2',
            ''
        ];


        # Pull repo


        #

        return 1;
    }



    private function getForgeToken()
    {
        $forgeToken = $this->cfg->get('forge_token');
        if ($forgeToken) {
            return $forgeToken;
        }
        $forgeToken = text(label: 'We need a forge token for this command. Please provide a forge token', required: true);
        $this->cfg->set('forge_token', $forgeToken);

        return $forgeToken;
    }
}

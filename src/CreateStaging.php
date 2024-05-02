<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Illuminate\Support\Str;
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
        // Todo: Check of forge CLI is installed
        $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiOGVkYWVhYjEyY2I5MGY4MmNiYzhmMDRiMmRhZDFmNTFkZTdiODFhZDZmNmM1YzA1ODJhY2Y0ZDNjOTI5MDViNjA5ODEyNDBhZDViNzE5ZWUiLCJpYXQiOjE3MTA0MzE3MzguNzM1MzE3LCJuYmYiOjE3MTA0MzE3MzguNzM1MzIsImV4cCI6MjAyNTk2NDUzOC43MzA0Miwic3ViIjoiNjM1Iiwic2NvcGVzIjpbXX0.TnlHpjgRc18nayxwQUehEyZWPANlpdFpgj6zNo0EzrXayYmwpNemz53b7Gr7Kh8LasHnF5WrJsS1DHjEXeeS5T4OdHIbLZgHdnsSrjzB8ESGXmOGq5AZnoxtfdPj9cC3qtuPZxydtn--QtsdfERkuPH8jZfStkUb73ujR8dsnkDW8KSfpBHRmc3GLUciQAYnd426Xdlj0r_xkppYbPp77mc76O9Ox6xmgHjBYi4lhpJf_BYQzfB1__3bqMwT9Ro-8XGF2A6vYARut2w7IqqiytC3nUpRSvxRT2DJFEcUjhI-fWcNXk6Pe1ckBrVRBBj1kMkzKR7YQt9kxRaLvSEvY1XakEKU5MqaIRhrVIndtb62C9E7sg-qNwyyrwHfgPjgQK5diqFV11usdxIk0QHmF4QRzwh-ZBCjupmoC-EU3YLXjCBqJ1ntopDNRgY36XDSTmKUZHxf8ao-mzj0S53uw3Jrx1yBxr8xLefBsVj6f78-g-2s6fpnIEvw6fcxKJLyBrIPe4iJQKAuIhPFk1E5sA0X88x6Kv1OJnWjHx2aDzvDGM3KihlSbOECyjbA5sklRZ3B8z-WGqqGDIi2LGNZicfrsVru1fmTsceZAtjJnJ43McqSZc2Rx-V5dZ5G638xpEWpSIFrFr5ytjEQRDoIT25CCeFc7Cuo5GzjIqfnjOo";
        $subdomain = 'test-staging';
        $serverId = '525126';

        # Setup the site
        $forge = new Forge($token);
        $sites = $forge->sites($serverId);



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

        return 1;
    }
}

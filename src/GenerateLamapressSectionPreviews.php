<?php

namespace LamaLama\Clli\Console;

use Spatie\Browsershot\Browsershot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class GenerateLamapressSectionPreviews extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    protected OutputInterface $output;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('lamapress:generate-preview')
            ->setDescription('Generate section previews for your lamapress project');
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->testByEd();

        return Command::SUCCESS;
    }

    protected function testByEd(): void
    {
        $sections = [
            [
                'url' => 'https://lamalama.nl/',
                'section' => '.js-home-work',
                'path' => '/Users/edwinfennema/Downloads/lamalama-work.jpg',
            ],
            [
                'url' => 'https://lamalama.nl/contact/',
                'section' => '.contact',
                'path' => '/Users/edwinfennema/Downloads/lamalama-contact.jpg',
            ],
        ];
        foreach ($sections as $s) {
            $this->saveSectionScreenshot($s['url'], $s['section'], $s['path']);
        }

        info('Done ✅');
        table(['url', 'component', 'file'], $sections);

    }

    protected function saveSectionScreenshot(string $url, string $selector, string $savePath): ?string
    {
        /*
            TODO:
            - Check if puppeteer is installed. If not install it
            - Wait for transition / animation
            - Hide menu / footer / other stuff
            - Add param to hide extra elements?
            - Add param for url
            - Margins?
            - Image type and background?
        */
        spin(
            message: "Creating screenshot for {$selector}",
            callback: fn () => Browsershot::url($url)
                ->windowSize(1280, 960)
                ->select($selector)
                ->save($savePath));

        return $savePath;
    }
}

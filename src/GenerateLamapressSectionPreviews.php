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

    protected InputInterface $input;

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
        $this->input = $input;
        $this->output = $output;
        if (! $this->testByEd()) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function testByEd(): bool
    {
        if (! $this->isPuppeteerInstalled()) {
            info('❌ puppeteer is missing.');
            $this->installPuppeteer();
        }
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

        return true;
    }

    protected function saveSectionScreenshot(string $url, string $selector, string $savePath): ?string
    {
        /*
            TODO:
            - Check if puppeteer is installed. If not install it
            - Wait for transition / animation (waitUntilNetworkIdle + setDelay)
            - Hide menu / footer / other stuff
            - Add param to hide extra elements?
            - Add param for url
            - Margins? Maybe: https://spatie.be/docs/image/v3/image-manipulations/image-canvas
            - Image type and background?
        */
        spin(
            message: "Creating screenshot for {$selector}",
            callback: fn () => Browsershot::url($url)
                ->windowSize(1280, 960)
                ->waitUntilNetworkIdle()
                ->setDelay(5000)
                ->select($selector)
                ->save($savePath));

        return $savePath;
    }

    protected function isPuppeteerInstalled(): bool
    {
        // Determine the global install directory (where this script is running)
        $cliRoot = realpath(__DIR__.'/../');
        $cmd = 'cd '.escapeshellarg($cliRoot).' && npm ls puppeteer --json 2>/dev/null';
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            return false;
        }
        $json = implode("\n", $output);
        $data = json_decode($json, true);

        return isset($data['dependencies']['puppeteer']);
    }

    public function installPuppeteer(): void
    {
        info('🤖 installing puppeteer');
        $cliRoot = realpath(__DIR__.'/../');
        $cmd = 'cd '.escapeshellarg($cliRoot).' && npm install puppeteer';
        exec($cmd, $output, $exitCode);
        info('🤖 puppeteer is installed');
    }
}

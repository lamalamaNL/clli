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

    private $sitemapUrls = [];
    private $sections = [];
    private $sectionsFolder = '';
    private $sectionsToGenerate = [];

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
        $this->getSitemapUrls($this->getDomain() . '/sitemap.xml');
        $this->getRenderedSections();
        $this->getSectionsFolder();
        $this->makeScreenshots();
        // $this->getSections();

        // echo $this->getDomain();
        // if (! $this->testByEd()) {
        //     return Command::FAILURE;
        // }

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

    private function getSectionsFolder()
    {
        $currentDir = getcwd();
        $pathParts = explode(DIRECTORY_SEPARATOR, $currentDir);

        // Find the index of 'wp-content' in the path
        $wpContentIndex = array_search('wp-content', $pathParts);
        if ($wpContentIndex === false) {
            throw new \Exception('This command must be run from inside a wp-content folder');
        }

        // Go two folders deeper from wp-content
        if (!isset($pathParts[$wpContentIndex + 2])) {
            throw new \Exception('Cannot find lamapress theme folder');
        }

        // Build the path to the target directory
        $targetPathParts = array_slice($pathParts, 0, $wpContentIndex + 3);
        $targetDir = implode(DIRECTORY_SEPARATOR, $targetPathParts);

        // Append /components/sections to the path
        $sectionsDir = $targetDir . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'sections';

        if (!is_dir($sectionsDir)) {
            throw new \Exception("Sections directory not found: {$sectionsDir}");
        }

        // // List all directories in /components/sections
        $dirs = [];
        foreach (scandir($sectionsDir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $sectionsDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $dirs[] = $item;
            }
        }

        $this->sectionsToGenerate = $dirs;
        $this->sectionsFolder = $sectionsDir;
    }

    private function makeScreenshots()
    {
        foreach ($this->sections as $name => $urls) {
            if (!in_array($name, $this->sectionsToGenerate)) {
                echo 'Skipping ' . $name . ' because it is not in the sections to generate';
                continue;
            } else {
                $this->saveSectionScreenshot($urls[0] . '?section-render=true', '[data-section-render="'.$name.'"]', $this->sectionsFolder . DIRECTORY_SEPARATOR . $name . '/preview.jpg');
            }
        }
    }

    private function getDomain()
    {
        $currentDir = getcwd();
        $pathParts = explode(DIRECTORY_SEPARATOR, $currentDir);
        // Find wp-content in the path
        $wpContentIndex = array_search('wp-content', $pathParts);
        if ($wpContentIndex === false) {
            throw new \Exception('This command must be run from inside a wp-content folder');
        }
        
        // Get the parent folder name (the folder containing wp-content)
        if ($wpContentIndex > 0) {
            $parentFolderName = $pathParts[$wpContentIndex - 1];
            return 'http://' . $parentFolderName . '.test';
        }
        
        throw new \Exception('Could not determine parent folder name');
    }

    private function getSitemapUrls($url)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url);
        $sitemap = $response->getBody()->getContents();
        
        if (!preg_match_all('/<loc>(.*?)<\/loc>/is', $sitemap, $matches)) {
            throw new \Exception('Failed to parse sitemap XML');
        }

        $locations = $matches[1];

        foreach ($locations as $location) {
            if (strpos($location, 'sitemap.xml') !== false && $location !== $url) {
                $this->getSitemapUrls($location);
            } else {
                $this->sitemapUrls[] = $location;
            }
        }
        
        return $locations;
    }

    private function getRenderedSections()
    {
        $client = new \GuzzleHttp\Client();
        $sectionMap = [];
        
        foreach ($this->sitemapUrls as $url) {
            $response = $client->request('GET', $url . '?section-render=true');
            $html = $response->getBody()->getContents();
        
            if (preg_match_all('/data-section-render=[\'"]([^\'"]+)[\'"]/', $html, $matches)) {
                foreach ($matches[1] as $name) {
                    if (!isset($sectionMap[$name])) {
                        $sectionMap[$name] = [];
                    }
                    if (!in_array($url, $sectionMap[$name])) {
                        $sectionMap[$name][] = $url;
                    }
                }
            }
        }
        
        $this->sections = $sectionMap;
        // print_r($this->sections);
    }
}

<?php

namespace LamaLama\Clli\Console\Services\Websocket\InternalFigmaConnect\Prompt;

use Chewie\Concerns\RegistersThemes;
use Laravel\Prompts\Prompt;

class FigmaPrompt extends Prompt
{
    use RegistersThemes;


    public function __construct()
    {
        $this->registerTheme(FigmaRenderer::class);
    }

    public function value(): mixed
    {
        return true;
    }
}

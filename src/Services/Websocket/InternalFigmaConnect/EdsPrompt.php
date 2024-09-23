<?php

namespace LamaLama\Clli\Console\Services\Websocket\InternalFigmaConnect;

use Laravel\Prompts\Prompt;

class EdsPrompt extends Prompt
{


    public function __construct()
    {
        static::output()->write("Testing 123");
        $this->prompt();
    }

    public function value(): mixed
    {
        // TODO: Implement value() method.
    }
}

<?php

namespace App\MessageHandler;

use App\Message\TestMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class TestMessageHandler implements MessageHandlerInterface
{
    public function __invoke(TestMessage $message)
    {
        // ... do some work - like sending an SMS message!
    }
}
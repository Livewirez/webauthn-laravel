<?php 

namespace Livewirez\Webauthn\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

class Dispatcher implements EventDispatcherInterface
{
    public function __construct(
        protected DispatcherContract $laravelDispatcher
    ) {

    }

    public function dispatch(object $event): object
    {
        $this->laravelDispatcher->dispatch($event);
        
        return $event;
    }
}
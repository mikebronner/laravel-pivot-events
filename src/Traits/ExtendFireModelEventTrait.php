<?php namespace GeneaLabs\LaravelPivotEvents\Traits;

use GeneaLabs\LaravelPivotEvents\Contracts\ReceivesPivotPayload;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Support\Arr;

trait ExtendFireModelEventTrait
{
    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param bool   $halt
     *
     * @return mixed
     */
    public function fireModelEvent(
        $event,
        $halt = true,
        $relationName = null,
        $ids = [],
        $idsAttributes = []
    ) {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        $method = $halt
            ? 'until'
            : 'dispatch';

        $payload = [
            'model' => $this,
            'relation' => $relationName,
            'pivotIds' => $ids,
            'pivotIdsAttributes' => $idsAttributes,
            0 => $this,
        ];

        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method, $payload)
        );

        if (false === $result) {
            return false;
        }

        $result = $result
            ?: static::$dispatcher
                ->{$method}("eloquent.{$event}: " . static::class, $payload);
        $this->broadcastPivotEvent($event, $payload);

        return $result;
    }

    /**
     * Fire a custom model event for the given event.
     *
     * Custom event classes that implement ReceivesPivotPayload will receive
     * the full pivot payload array as a second constructor argument. Classes
     * that do not implement the interface are constructed with only the model
     * (backwards-compatible behaviour).
     *
     * @param  string  $event
     * @param  string  $method
     * @param  array   $payload
     * @return mixed|null
     */
    protected function fireCustomModelEvent($event, $method, $payload = [])
    {
        if (! isset($this->dispatchesEvents[$event])) {
            return;
        }

        $eventClass = $this->dispatchesEvents[$event];

        $instance = is_a($eventClass, ReceivesPivotPayload::class, true)
            ? new $eventClass($this, $payload)
            : new $eventClass($this);

        $result = static::$dispatcher->$method($instance);

        if (! is_null($result)) {
            return $result;
        }
    }

    protected function broadcastPivotEvent(string $event, array $payload): void
    {
        $events = [
            "pivotAttached",
            "pivotDetached",
            "pivotSynced",
            "pivotUpdated",
        ];

        if (! in_array($event, $events)) {
            return;
        }

        $className = explode("\\", get_class($this));
        $name = method_exists($this, "broadcastAs")
                ? $this->broadcastAs()
                : array_pop($className) . ucwords($event);
        $channels = method_exists($this, "broadcastOn")
            ? Arr::wrap($this->broadcastOn($event))
            : [];

        if (empty($channels)) {
            return;
        }

        $connections = method_exists($this, "broadcastConnections")
            ? $this->broadcastConnections()
            : [null];
        $manager = app(BroadcastingFactory::class);

        foreach ($connections as $connection) {
            $manager->connection($connection)
                ->broadcast($channels, $name, $payload);
        }
    }
}

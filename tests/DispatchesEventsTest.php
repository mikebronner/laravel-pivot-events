<?php namespace GeneaLabs\LaravelPivotEvents\Tests;

use GeneaLabs\LaravelPivotEvents\Tests\Events\LegacyPivotAttached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotAttached;
use GeneaLabs\LaravelPivotEvents\Tests\Models\Role;
use GeneaLabs\LaravelPivotEvents\Tests\Models\UserWithLegacyEvent;
use GeneaLabs\LaravelPivotEvents\Tests\Models\UserWithPayloadEvent;

/**
 * Tests for dispatchesEvents + pivot payload (issue #17).
 *
 * Verifies two scenarios:
 *   1. Backwards compat — a legacy custom event class that does NOT implement
 *      ReceivesPivotPayload is instantiated with only the model, no
 *      ArgumentCountError thrown.
 *   2. Payload-aware — a custom event class that implements
 *      ReceivesPivotPayload receives the full pivot payload array as its
 *      second constructor argument, with all expected keys present.
 */
class DispatchesEventsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Backwards-compatibility: legacy event class (no interface)
    // -----------------------------------------------------------------------

    public function test_legacy_custom_event_class_receives_model_only()
    {
        $dispatched = [];

        \Event::listen(LegacyPivotAttached::class, function (LegacyPivotAttached $event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $user = UserWithLegacyEvent::find(1);
        // Must not throw ArgumentCountError even though constructor only takes $model
        $user->roles()->attach(1, ['value' => 42]);

        $this->assertCount(1, $dispatched, 'LegacyPivotAttached should be dispatched once');
        $this->assertInstanceOf(UserWithLegacyEvent::class, $dispatched[0]->model);
    }

    // -----------------------------------------------------------------------
    // New behaviour: payload-aware event class (implements ReceivesPivotPayload)
    // -----------------------------------------------------------------------

    public function test_payload_aware_custom_event_class_receives_full_payload()
    {
        $dispatched = [];

        \Event::listen(PayloadAwarePivotAttached::class, function (PayloadAwarePivotAttached $event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $user = UserWithPayloadEvent::find(1);
        $user->roles()->attach(2, ['value' => 99]);

        $this->assertCount(1, $dispatched, 'PayloadAwarePivotAttached should be dispatched once');

        $event   = $dispatched[0];
        $payload = $event->payload;

        $this->assertInstanceOf(UserWithPayloadEvent::class, $event->model);

        // All four documented payload keys must be present
        $this->assertArrayHasKey('model',              $payload, 'payload missing "model"');
        $this->assertArrayHasKey('relation',           $payload, 'payload missing "relation"');
        $this->assertArrayHasKey('pivotIds',           $payload, 'payload missing "pivotIds"');
        $this->assertArrayHasKey('pivotIdsAttributes', $payload, 'payload missing "pivotIdsAttributes"');

        $this->assertInstanceOf(UserWithPayloadEvent::class, $payload['model']);
        $this->assertSame('roles',              $payload['relation']);
        $this->assertSame([2],                  $payload['pivotIds']);
        $this->assertSame([2 => ['value' => 99]], $payload['pivotIdsAttributes']);
    }

    public function test_payload_aware_event_receives_payload_on_second_attach()
    {
        $dispatched = [];

        \Event::listen(PayloadAwarePivotAttached::class, function (PayloadAwarePivotAttached $event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $user = UserWithPayloadEvent::find(1);
        $user->roles()->attach(1, ['value' => 10]);
        $user->roles()->attach(2, ['value' => 20]);

        $this->assertCount(2, $dispatched);

        foreach ($dispatched as $event) {
            $p = $event->payload;
            $this->assertArrayHasKey('model',              $p);
            $this->assertArrayHasKey('relation',           $p);
            $this->assertArrayHasKey('pivotIds',           $p);
            $this->assertArrayHasKey('pivotIdsAttributes', $p);
            $this->assertSame('roles', $p['relation']);
        }

        $this->assertSame([1], $dispatched[0]->payload['pivotIds']);
        $this->assertSame([2], $dispatched[1]->payload['pivotIds']);
    }
}

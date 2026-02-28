<?php namespace GeneaLabs\LaravelPivotEvents\Tests;

use GeneaLabs\LaravelPivotEvents\Tests\Events\LegacyPivotAttached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\LegacyPivotDetached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotAttached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotAttaching;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotDetached;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotSynced;
use GeneaLabs\LaravelPivotEvents\Tests\Events\PayloadAwarePivotUpdated;
use GeneaLabs\LaravelPivotEvents\Tests\Models\Role;
use GeneaLabs\LaravelPivotEvents\Tests\Models\UserWithFullLegacyEvents;
use GeneaLabs\LaravelPivotEvents\Tests\Models\UserWithFullPayloadEvents;
use GeneaLabs\LaravelPivotEvents\Tests\Models\UserWithLegacyEvent;
use GeneaLabs\LaravelPivotEvents\Tests\Models\UserWithPayloadEvent;
use GeneaLabs\LaravelPivotEvents\Tests\Models\VideoWithPayloadEvent;

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
    // Helper: collects dispatched events of a given class
    // -----------------------------------------------------------------------

    private function collectEvents(string $eventClass, array &$dispatched): void
    {
        \Event::listen($eventClass, function ($event) use (&$dispatched) {
            $dispatched[] = $event;
        });
    }

    private function assertPayloadStructure(array $payload, string $message = ''): void
    {
        foreach (['model', 'relation', 'pivotIds', 'pivotIdsAttributes'] as $key) {
            $this->assertArrayHasKey($key, $payload, $message ?: "payload missing \"{$key}\"");
        }
    }

    // =======================================================================
    // 1. Backwards-compatibility: legacy event classes (no interface)
    // =======================================================================

    public function test_legacy_custom_event_class_receives_model_only()
    {
        $dispatched = [];
        $this->collectEvents(LegacyPivotAttached::class, $dispatched);

        $user = UserWithLegacyEvent::find(1);
        $user->roles()->attach(1, ['value' => 42]);

        $this->assertCount(1, $dispatched, 'LegacyPivotAttached should be dispatched once');
        $this->assertInstanceOf(UserWithLegacyEvent::class, $dispatched[0]->model);
    }

    public function test_legacy_detach_event_receives_model_only()
    {
        $user = UserWithFullLegacyEvents::find(1);
        $user->roles()->attach(1, ['value' => 10]);

        $dispatched = [];
        $this->collectEvents(LegacyPivotDetached::class, $dispatched);

        $user->roles()->detach(1);

        $this->assertCount(1, $dispatched, 'LegacyPivotDetached should be dispatched once');
        $this->assertInstanceOf(UserWithFullLegacyEvents::class, $dispatched[0]->model);
    }

    public function test_legacy_sync_fires_attach_and_detach_custom_events()
    {
        $user = UserWithFullLegacyEvents::find(1);
        $user->roles()->attach([1, 2]);

        $attached = [];
        $detached = [];
        $this->collectEvents(LegacyPivotAttached::class, $attached);
        $this->collectEvents(LegacyPivotDetached::class, $detached);

        // Sync to [3] — should detach 1,2 and attach 3
        $user->roles()->sync([3]);

        $this->assertCount(1, $attached, 'LegacyPivotAttached should fire once during sync');
        $this->assertCount(1, $detached, 'LegacyPivotDetached should fire once during sync');
    }

    // =======================================================================
    // 2. Payload-aware: attach operations
    // =======================================================================

    public function test_payload_aware_custom_event_class_receives_full_payload()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $dispatched);

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
        $this->collectEvents(PayloadAwarePivotAttached::class, $dispatched);

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

    public function test_payload_aware_attach_with_multiple_ids()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $dispatched);

        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1 => ['value' => 100], 2 => ['value' => 200]]);

        $this->assertCount(1, $dispatched, 'Single attach call dispatches one pivotAttached event');

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertSame([1, 2], $payload['pivotIds']);
        $this->assertSame(
            [1 => ['value' => 100], 2 => ['value' => 200]],
            $payload['pivotIdsAttributes']
        );
    }

    public function test_payload_aware_attach_with_model_instance()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $dispatched);

        $user = UserWithFullPayloadEvents::find(1);
        $role = Role::find(1);
        $user->roles()->attach($role, ['value' => 55]);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertSame([1], $payload['pivotIds']);
        $this->assertSame([1 => ['value' => 55]], $payload['pivotIdsAttributes']);
    }

    public function test_payload_aware_attach_with_collection()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $dispatched);

        $user  = UserWithFullPayloadEvents::find(1);
        $roles = Role::whereIn('id', [1, 2])->get();
        $user->roles()->attach($roles, ['value' => 77]);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertSame([1, 2], $payload['pivotIds']);
        $this->assertSame(
            [1 => ['value' => 77], 2 => ['value' => 77]],
            $payload['pivotIdsAttributes']
        );
    }

    public function test_payload_aware_attaching_pre_event_receives_payload()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotAttaching::class, $dispatched);

        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach(3, ['value' => 42]);

        $this->assertCount(1, $dispatched, 'pivotAttaching custom event should fire');

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertSame('roles', $payload['relation']);
        $this->assertSame([3], $payload['pivotIds']);
        $this->assertSame([3 => ['value' => 42]], $payload['pivotIdsAttributes']);
    }

    // =======================================================================
    // 3. Payload-aware: detach operations
    // =======================================================================

    public function test_payload_aware_detach_receives_correct_payload()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1, 2, 3]);

        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotDetached::class, $dispatched);

        $user->roles()->detach(2);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertInstanceOf(UserWithFullPayloadEvents::class, $payload['model']);
        $this->assertSame('roles', $payload['relation']);
        $this->assertSame([2], $payload['pivotIds']);
    }

    public function test_payload_aware_detach_array_receives_all_ids()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1, 2, 3]);

        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotDetached::class, $dispatched);

        $user->roles()->detach([1, 3]);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertSame([1, 3], $payload['pivotIds']);
    }

    public function test_payload_aware_detach_null_receives_all_ids()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1, 2, 3]);

        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotDetached::class, $dispatched);

        $user->roles()->detach();

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertSame([1, 2, 3], $payload['pivotIds']);
    }

    public function test_payload_aware_detach_with_model_instance()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1, 2]);

        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotDetached::class, $dispatched);

        $role = Role::find(2);
        $user->roles()->detach($role);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertSame([2], $payload['pivotIds']);
    }

    // =======================================================================
    // 4. Payload-aware: sync operations
    // =======================================================================

    public function test_payload_aware_sync_fires_synced_event_with_payload()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotSynced::class, $dispatched);

        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->sync([1 => ['value' => 50], 2 => ['value' => 60]]);

        $this->assertCount(1, $dispatched, 'pivotSynced custom event should fire once');

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertInstanceOf(UserWithFullPayloadEvents::class, $payload['model']);
        $this->assertSame('roles', $payload['relation']);
    }

    public function test_sync_fires_both_attach_and_detach_custom_events()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1, 2]);

        $attached = [];
        $detached = [];
        $synced   = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $attached);
        $this->collectEvents(PayloadAwarePivotDetached::class, $detached);
        $this->collectEvents(PayloadAwarePivotSynced::class, $synced);

        // Sync to [3] — detaches 1,2 and attaches 3
        $user->roles()->sync([3 => ['value' => 99]]);

        $this->assertCount(1, $attached, 'pivotAttached custom event should fire during sync');
        $this->assertCount(1, $detached, 'pivotDetached custom event should fire during sync');
        $this->assertCount(1, $synced, 'pivotSynced custom event should fire during sync');

        // Verify attached payload
        $this->assertSame([3], $attached[0]->payload['pivotIds']);

        // Verify synced payload contains relation
        $this->assertSame('roles', $synced[0]->payload['relation']);
    }

    public function test_sync_to_same_state_fires_only_synced_custom_event()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1]);

        $attached = [];
        $detached = [];
        $synced   = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $attached);
        $this->collectEvents(PayloadAwarePivotDetached::class, $detached);
        $this->collectEvents(PayloadAwarePivotSynced::class, $synced);

        $user->roles()->sync([1]);

        $this->assertCount(0, $attached, 'No attach when syncing to same state');
        $this->assertCount(0, $detached, 'No detach when syncing to same state');
        $this->assertCount(1, $synced, 'pivotSynced should still fire');
    }

    public function test_sync_empty_array_fires_detach_and_synced_custom_events()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1, 2]);

        $detached = [];
        $synced   = [];
        $this->collectEvents(PayloadAwarePivotDetached::class, $detached);
        $this->collectEvents(PayloadAwarePivotSynced::class, $synced);

        $user->roles()->sync([]);

        $this->assertCount(1, $detached, 'pivotDetached should fire when syncing to empty');
        $this->assertCount(1, $synced, 'pivotSynced should fire when syncing to empty');
    }

    // =======================================================================
    // 5. Payload-aware: updateExistingPivot operations
    // =======================================================================

    public function test_payload_aware_update_receives_correct_payload()
    {
        $user = UserWithFullPayloadEvents::find(1);
        $user->roles()->attach([1, 2]);

        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotUpdated::class, $dispatched);

        $user->roles()->updateExistingPivot(1, ['value' => 999]);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertInstanceOf(UserWithFullPayloadEvents::class, $payload['model']);
        $this->assertSame('roles', $payload['relation']);
        $this->assertSame([1], $payload['pivotIds']);
        $this->assertSame([1 => ['value' => 999]], $payload['pivotIdsAttributes']);
    }

    // =======================================================================
    // 6. Polymorphic relations with custom events
    // =======================================================================

    public function test_polymorphic_attach_fires_payload_aware_event()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $dispatched);

        $video = VideoWithPayloadEvent::find(1);
        $video->tags()->attach(1, ['value' => 88]);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertInstanceOf(VideoWithPayloadEvent::class, $payload['model']);
        $this->assertSame('tags', $payload['relation']);
        $this->assertSame([1], $payload['pivotIds']);
        $this->assertSame([1 => ['value' => 88]], $payload['pivotIdsAttributes']);
    }

    public function test_polymorphic_detach_fires_payload_aware_event()
    {
        $video = VideoWithPayloadEvent::find(1);
        $video->tags()->attach([1, 2]);

        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotDetached::class, $dispatched);

        $video->tags()->detach(1);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertPayloadStructure($payload);
        $this->assertInstanceOf(VideoWithPayloadEvent::class, $payload['model']);
        $this->assertSame('tags', $payload['relation']);
        $this->assertSame([1], $payload['pivotIds']);
    }

    public function test_polymorphic_attach_multiple_fires_payload_aware_event()
    {
        $dispatched = [];
        $this->collectEvents(PayloadAwarePivotAttached::class, $dispatched);

        $video = VideoWithPayloadEvent::find(1);
        $video->tags()->attach([1 => ['value' => 11], 2 => ['value' => 22]]);

        $this->assertCount(1, $dispatched);

        $payload = $dispatched[0]->payload;
        $this->assertSame([1, 2], $payload['pivotIds']);
        $this->assertSame(
            [1 => ['value' => 11], 2 => ['value' => 22]],
            $payload['pivotIdsAttributes']
        );
    }
}

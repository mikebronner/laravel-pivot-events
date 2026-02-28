<?php

namespace GeneaLabs\LaravelPivotEvents\Tests;

use GeneaLabs\LaravelPivotEvents\Tests\Models\User;

class ObservableEventsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_events()
    {
        $user = User::find(1);
        $events = $user->getObservableEvents();

        $this->assertTrue(in_array('pivotAttaching', $events));
    }

    public function test_no_duplicate_observable_events()
    {
        $user = User::find(1);
        $events = $user->getObservableEvents();

        $duplicates = array_diff_assoc($events, array_unique($events));

        $this->assertEmpty(
            $duplicates,
            'Duplicate observable events found: ' . implode(', ', array_unique($duplicates))
        );
    }

}

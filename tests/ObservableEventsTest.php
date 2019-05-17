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
}

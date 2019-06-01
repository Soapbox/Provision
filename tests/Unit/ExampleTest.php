<?php

namespace Tests\Unit;

use App\EC2\EC2;
use Tests\TestCase;
use JSHayes\FakeRequests\Traits\Laravel\FakeRequests;

class ExampleTest extends TestCase
{
    use FakeRequests;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $this->assertTrue(true);
        $this->fakeRequests();

        resolve(EC2::class)->getInstances();
    }
}

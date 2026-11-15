<?php

namespace Tests\Unit;

use App\Support\ClientTripTimes;
use PHPUnit\Framework\TestCase;

class ClientTripTimeFormattingTest extends TestCase
{
    public function test_time_labels_distinguish_am_from_pm(): void
    {
        $this->assertSame('12:00 AM', ClientTripTimes::displayTime('00:00'));
        $this->assertSame('6:00 AM', ClientTripTimes::displayTime('06:00'));
        $this->assertSame('11:59 AM', ClientTripTimes::displayTime('11:59'));
        $this->assertSame('12:00 PM', ClientTripTimes::displayTime('12:00'));
        $this->assertSame('1:30 PM', ClientTripTimes::displayTime('13:30'));
        $this->assertSame('11:59 PM', ClientTripTimes::displayTime('23:59'));
    }
}

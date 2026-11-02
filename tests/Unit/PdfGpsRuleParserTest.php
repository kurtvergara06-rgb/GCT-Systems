<?php

namespace Tests\Unit;

use App\Services\Gps\PdfGpsRuleParser;
use PHPUnit\Framework\TestCase;

class PdfGpsRuleParserTest extends TestCase
{
    public function test_wrapped_and_inline_pdf_rows_keep_their_locations_times_and_zero_values(): void
    {
        $text = <<<'TEXT'
GPS Engine Hours Report - 10220_NGA-6363
№ Grouping Beginning Initial location End Final location Engine hours Total time In motion Idling Mileage
1.1 09-01-2026 04:05:45 09-01-2026 04:05:45
Lipa-Rosario Rd, San Felipe, Padre Garcia,
4224 Batangas
09-01-2026 08:21:13
San Pedro Rd, San Pedro I (Eastern),
Malvar, 4233 Batangas
4:15:28 4:15:28 1:44:31 2:30:57 50 km
1.2 09-01-2026 10:32:25 09-01-2026 10:32:25 Lawa Rd, Calamba 09-01-2026 10:33:27 Lawa Rd, Calamba 0:01:02 0:01:02 0:00:00 0:01:02 0.00 km
GPS Engine Hours Report - 10239_NHE_2168
№ Grouping Beginning Initial location End Final location Engine hours Total time In motion Idling Mileage
1.1 09-01-2026 10:33:28 09-01-2026 10:33:28
Lawa Rd, Calamba
09-01-2026 10:34:30
Lawa Rd, Calamba
----- 0:01:02 0:00:00 0:01:02 ----- 
TEXT;

        $result = (new PdfGpsRuleParser)->parseText($text);

        $this->assertSame(0, $result['skipped_invalid']);
        $this->assertCount(3, $result['records']);
        $first = $result['records'][0];
        $this->assertSame('NGA-6363-1.1', $first['record_no']);
        $this->assertSame('Lipa-Rosario Rd, San Felipe, Padre Garcia, 4224 Batangas', $first['initial_location']);
        $this->assertSame('San Pedro Rd, San Pedro I (Eastern), Malvar, 4233 Batangas', $first['final_location']);
        $this->assertSame('4:15:28', $first['raw_data']['source_values']['total_time']);
        $this->assertSame(0.0, $result['records'][1]['mileage_km']);
        $this->assertSame(0.0, $result['records'][1]['in_motion_minutes']);
        $this->assertSame(1.0333, $result['records'][1]['total_minutes']);
        $this->assertSame(0.017222, $result['records'][1]['engine_hours']);
        $this->assertSame('NHE_2168-1.1', $result['records'][2]['record_no']);
        $this->assertNull($result['records'][2]['engine_hours']);
        $this->assertNotEmpty($result['records'][2]['raw_data']['source_warnings']);
    }
}

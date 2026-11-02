<?php

namespace App\Services\Gps;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class PdfGpsRuleParser
{
    public function extract(string $storedPath): array
    {
        $path = Storage::disk('public')->path($storedPath);
        if (! is_file($path)) {
            throw new \RuntimeException('Uploaded PDF is missing.');
        }

        // Poppler's local pdftotext utility is required; no Python or ML service.
        $binary = config('gps.pdf_to_text_binary', env('GPS_PDFTOTEXT_BINARY', 'pdftotext'));
        $process = new Process([$binary, '-layout', '-nopgbrk', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(900);
        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Local PDF extraction failed. Install Poppler pdftotext and set GPS_PDFTOTEXT_BINARY; '
                . 'verify the PDF contains selectable text. ' . $e->getMessage(), 0, $e
            );
        }

        $text = $process->getOutput();
        if (! str_contains($text, 'Engine Hours') || ! str_contains($text, 'Beginning')) {
            throw new \RuntimeException('Unsupported PDF: expected a GPS Engine Hours table.');
        }

        $records = [];
        $vehicle = null;
        $skipped = 0;
        $seen = [];
        // Source report layout: row number, start/grouping date,
        // beginning date, end date, then four duration columns and mileage.
        // Address text wraps across separate lines and is deliberately not
        // guessed into the wrong vehicle's location fields.
        $datePattern = '\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2}';
        $durationPattern = '(?:\d+\s+days?\s+)?\d+:\d{2}:\d{2}';
        $rowPattern = '/^\s*(\d+\.\d+)\s+('.$datePattern.')\s+('.$datePattern.')\s+('.$datePattern.')\s+('.$durationPattern.')\s+('.$durationPattern.')\s+('.$durationPattern.')\s+('.$durationPattern.')\s+([\d,.]+\s*km|-----)\s*$/iu';
        foreach (preg_split('/\R/u', $text) as $line) {
            if (preg_match('/GPS Engine Hours Report\s*[-–]\s*\d+_([A-Z0-9-]+)/i', $line, $header)) {
                $vehicle = strtoupper($header[1]);
                continue;
            }
            if (! $vehicle || ! preg_match('/^\s*\d+\.\d+\s+/', $line)) {
                continue;
            }
            if (! preg_match($rowPattern, $line, $m)) {
                $skipped++;
                continue;
            }
            [, $no, $grouping, $start, $end, $engine, $total, $motion, $idle, $mileage] = $m;
            $start = $this->date($start);
            $end = $this->date($end);
            $engineSeconds = $this->duration($engine);
            $totalSeconds = $this->duration($total);
            $motionSeconds = $this->duration($motion);
            $idleSeconds = $this->duration($idle);
            if (! $start || ! $end
                || ! preg_match('/^([\d,.]+)\s*km$/i', $mileage, $km)
                || in_array(null, [$engineSeconds, $totalSeconds, $motionSeconds, $idleSeconds], true)
                || abs($totalSeconds - $motionSeconds - $idleSeconds) > 60
                || abs($engineSeconds - $totalSeconds) > 60
                || strtotime($start) > strtotime($end)) {
                $skipped++;
                continue;
            }
            $signature = $vehicle.'|'.$no.'|'.$start.'|'.$end;
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $records[] = [
                'record_no' => $vehicle.'-'.$no,
                'bus_no' => $vehicle,
                'trip_type' => 'Engine Activity',
                'beginning' => $start,
                'ending' => $end,
                'duration_minutes' => round((strtotime($end) - strtotime($start)) / 60, 2),
                'total_minutes' => round($totalSeconds / 60, 2),
                'in_motion_minutes' => round($motionSeconds / 60, 2),
                'idling_minutes' => round($idleSeconds / 60, 2),
                'engine_hours' => round($engineSeconds / 3600, 4),
                'mileage_km' => (float) str_replace(',', '', $km[1]),
                'description' => 'Engine activity interval; not a scheduled trip. Wrapped locations require review.',
                'raw_data' => ['row' => $line, 'grouping_source' => $grouping, 'source' => 'Local PDF rule parser'],
            ];
        }
        if ($records === []) {
            throw new \RuntimeException(
                'No validated GPS activity records found. The PDF table layout may require a dedicated format mapping.'
            );
        }

        return ['records' => $records, 'skipped_invalid' => $skipped];
    }

    private function date(string $text): ?string
    {
        foreach (['m-d-Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, trim($text));
            if ($date && $date->format($format) === trim($text)) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    private function duration(string $value): ?int
    {
        $value = trim($value);
        if (! preg_match('/^(?:(\d+)\s+days?\s+)?(\d+):(\d{2}):(\d{2})$/i', $value, $m)) {
            return null;
        }
        if ((int) $m[3] > 59 || (int) $m[4] > 59) {
            return null;
        }
        return ((int) ($m[1] ?? 0)) * 86400 + (int) $m[2] * 3600 + (int) $m[3] * 60 + (int) $m[4];
    }
}

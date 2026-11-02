<?php

namespace App\Services\Gps;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PdfGpsRuleParser
{
    public function extract(string $storedPath, ?callable $onProgress = null): array
    {
        $path = Storage::disk('public')->path($storedPath);
        if (! is_file($path)) {
            throw new \RuntimeException('Uploaded PDF is missing.');
        }

        // Raw content order keeps each wrapped location and numeric row together
        // with both Xpdf and Poppler. Layout mode mixes numeric columns across rows.
        $binary = config('gps.pdf_to_text_binary', env('GPS_PDFTOTEXT_BINARY', 'pdftotext'));
        if ($binary === 'pdftotext' && PHP_OS_FAMILY === 'Windows'
            && ! (new ExecutableFinder)->find('pdftotext')) {
            $gitBinary = rtrim((string) getenv('ProgramFiles'), '\\/').'/Git/mingw64/bin/pdftotext.exe';
            if (is_file($gitBinary)) {
                $binary = $gitBinary;
            }
        }
        $process = new Process([$binary, '-raw', '-nopgbrk', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(900);
        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'PDF extraction failed. Install pdftotext and set GPS_PDFTOTEXT_BINARY; verify the PDF contains selectable text. '.$e->getMessage(),
                0,
                $e
            );
        }

        return $this->parseText($process->getOutput(), $onProgress);
    }

    public function parseText(string $text, ?callable $onProgress = null): array
    {
        if (! str_contains($text, 'Engine Hours') || ! str_contains($text, 'Beginning')) {
            throw new \RuntimeException('Unsupported PDF: expected a GPS Engine Hours table.');
        }

        $lines = preg_split('/\R/u', $text);
        $lineCount = max(1, count($lines));
        $records = [];
        $vehicle = null;
        $pending = null;
        $skipped = 0;
        $seen = [];
        $date = '\\d{2}-\\d{2}-\\d{4}\\s+\\d{2}:\\d{2}:\\d{2}';
        $time = '(?:\\d+\\s+days?\\s+)?\\d+:\\d{2}:\\d{2}|-----';
        $rowPattern = '/^(\\d+\\.\\d+)\\s+('.$date.')\\s+('.$date.')(?:\\s+(.*))?$/u';
        $bodyPattern = '/^(.*?)\\s+('.$date.')\\s+(.*?)\\s+('.$time.')\\s+('.$time.')\\s+('.$time.')\\s+('.$time.')\\s+([\\d,.]+\\s*km|-----)$/iu';

        $flush = function () use (&$pending, &$records, &$skipped, &$seen): void {
            if ($pending === null) {
                return;
            }
            $record = isset($pending['engine']) ? $this->makeRecord($pending) : null;
            if ($record === null) {
                $skipped++;
            } else {
                $signature = $record['record_no'].'|'.$record['beginning'].'|'.$record['ending'];
                if (! isset($seen[$signature])) {
                    $seen[$signature] = true;
                    $records[] = $record;
                }
            }
            $pending = null;
        };
        $complete = function () use (&$pending, $bodyPattern, $flush): void {
            if ($pending === null || ! preg_match($bodyPattern, $pending['body'], $values)) {
                return;
            }
            [, $pending['initial'], $pending['end'], $pending['final'],
                $pending['engine'], $pending['total'], $pending['motion'],
                $pending['idle'], $pending['mileage']] = $values;
            $flush();
        };

        foreach ($lines as $index => $sourceLine) {
            $line = trim($sourceLine);
            if ($line === '') {
                continue;
            }
            if ($onProgress && $index % 500 === 0) {
                $onProgress(min(90, 5 + (int) floor(85 * $index / $lineCount)));
            }
            if (preg_match('/GPS Engine Hours Report\\s*[-–]\\s*\\d+_([A-Z0-9_-]+)/i', $line, $header)) {
                $flush();
                $vehicle = strtoupper($header[1]);
                continue;
            }
            if (! $vehicle) {
                continue;
            }
            if (preg_match($rowPattern, $line, $parts)) {
                $flush();
                $pending = [
                    'vehicle' => $vehicle,
                    'no' => $parts[1],
                    'grouping' => $parts[2],
                    'start' => $parts[3],
                    'body' => trim($parts[4] ?? ''),
                    'row' => $line,
                ];
                $complete();
                continue;
            }
            if (preg_match('/^\\s*\\d+\\.\\d+\\s+/', $line)) {
                $flush();
                $skipped++;
                continue;
            }
            if ($pending === null) {
                continue;
            }
            $pending['body'] = trim($pending['body'].' '.$line);
            $pending['row'] .= ' | '.$line;
            $complete();
        }
        $flush();
        $onProgress && $onProgress(90);
        if ($records === []) {
            throw new \RuntimeException('No GPS activity rows could be read from the PDF table.');
        }

        return ['records' => $records, 'skipped_invalid' => $skipped];
    }

    private function makeRecord(array $row): ?array
    {
        $start = $this->date($row['start']);
        $end = $this->date($row['end']);
        if (! $start || ! $end || strtotime($start) > strtotime($end)) {
            return null;
        }

        $seconds = [];
        foreach (['engine', 'total', 'motion', 'idle'] as $field) {
            $seconds[$field] = $this->duration($row[$field]);
        }
        $mileage = preg_match('/^([\\d,.]+)\\s*km$/i', $row['mileage'], $km)
            ? (float) str_replace(',', '', $km[1]) : null;
        $warnings = [];
        if (in_array(null, $seconds, true) || $mileage === null) {
            $warnings[] = 'The source row has a missing or invalid duration or mileage.';
        }
        if ($seconds['total'] !== null && $seconds['motion'] !== null && $seconds['idle'] !== null
            && $seconds['total'] !== $seconds['motion'] + $seconds['idle']) {
            $warnings[] = 'In motion plus idling differs from total time in the source.';
        }
        if ($seconds['engine'] !== null && $seconds['total'] !== null
            && $seconds['engine'] !== $seconds['total']) {
            $warnings[] = 'Engine hours differs from total time in the source.';
        }
        $initial = trim($row['initial']);
        $final = trim($row['final']);
        if ($initial === '' || $final === '') {
            $warnings[] = 'One or both source locations are missing.';
        }

        return [
            'record_no' => $row['vehicle'].'-'.$row['no'],
            'bus_no' => $row['vehicle'],
            'trip_type' => 'Engine Activity',
            'grouping' => $row['grouping'],
            'beginning' => $start,
            'initial_location' => $initial !== '' ? $initial : null,
            'ending' => $end,
            'final_location' => $final !== '' ? $final : null,
            'duration_minutes' => round((strtotime($end) - strtotime($start)) / 60, 4),
            'total_minutes' => $seconds['total'] !== null ? round($seconds['total'] / 60, 4) : null,
            'in_motion_minutes' => $seconds['motion'] !== null ? round($seconds['motion'] / 60, 4) : null,
            'idling_minutes' => $seconds['idle'] !== null ? round($seconds['idle'] / 60, 4) : null,
            'engine_hours' => $seconds['engine'] !== null ? round($seconds['engine'] / 3600, 6) : null,
            'mileage_km' => $mileage,
            'description' => 'Engine activity interval; not a scheduled trip.'.($warnings ? ' Source values require review.' : ''),
            'raw_data' => [
                'row' => $row['row'],
                'source' => 'Local PDF raw parser',
                'source_values' => [
                    'engine_hours' => $row['engine'],
                    'total_time' => $row['total'],
                    'in_motion' => $row['motion'],
                    'idling' => $row['idle'],
                    'mileage' => $row['mileage'],
                ],
                'source_warnings' => $warnings,
            ],
        ];
    }

    private function date(string $text): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!m-d-Y H:i:s', trim($text));

        return $date && $date->format('m-d-Y H:i:s') === trim($text)
            ? $date->format('Y-m-d H:i:s') : null;
    }

    private function duration(string $value): ?int
    {
        if (! preg_match('/^(?:(\\d+)\\s+days?\\s+)?(\\d+):(\\d{2}):(\\d{2})$/i', trim($value), $m)
            || (int) $m[3] > 59 || (int) $m[4] > 59) {
            return null;
        }

        return ((int) ($m[1] ?? 0)) * 86400 + (int) $m[2] * 3600 + (int) $m[3] * 60 + (int) $m[4];
    }
}

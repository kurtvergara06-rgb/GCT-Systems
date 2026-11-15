<?php

namespace App\Support;

use App\Models\Operation\ClientTripTimeConfiguration;
use App\Models\Operation\OperationClient;
use Illuminate\Validation\ValidationException;

class ClientTripTimes
{
    public const CATEGORY_INCOMING_AM = 'incoming_am';

    public const CATEGORY_INCOMING_PM = 'incoming_pm';

    public const CATEGORY_OUTGOING_AM = 'outgoing_am';

    public const CATEGORY_OUTGOING_PM = 'outgoing_pm';

    public const CATEGORIES = [
        self::CATEGORY_INCOMING_AM,
        self::CATEGORY_INCOMING_PM,
        self::CATEGORY_OUTGOING_AM,
        self::CATEGORY_OUTGOING_PM,
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_INCOMING_AM => 'Incoming AM',
        self::CATEGORY_INCOMING_PM => 'Incoming PM',
        self::CATEGORY_OUTGOING_AM => 'Outgoing AM',
        self::CATEGORY_OUTGOING_PM => 'Outgoing PM',
    ];

    /** Minutes from midnight where the AM period ends and the PM period begins (12:00 NN). */
    public const NOON_MINUTES = 720;

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? $category;
    }

    public static function isValidCategory(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }

    public static function isAmCategory(string $category): bool
    {
        return str_ends_with($category, '_am');
    }

    public static function minutesFromTime(string $time): int
    {
        $parts = explode(':', trim($time));

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }

    public static function timeFromMinutes(int $minutes): string
    {
        $minutes = max(0, min(1439, $minutes));

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    public static function inputTimeFromMinutes(int $minutes): string
    {
        $minutes = max(0, min(1439, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function displayTime(string|int $time): string
    {
        $minutes = is_int($time) ? $time : self::minutesFromTime($time);
        $minutes = max(0, min(1439, $minutes));
        $hour = intdiv($minutes, 60);
        $minute = $minutes % 60;
        $period = $minutes >= self::NOON_MINUTES ? 'PM' : 'AM';

        return sprintf('%d:%02d %s', $hour % 12 === 0 ? 12 : $hour % 12, $minute, $period);
    }

    /**
     * Derive the trip-time category from a direction and a departure time.
     * AM covers 12:00 AM up to (but not including) 12:00 NN; PM covers the rest.
     */
    public static function categoryFor(?string $direction, string|int $time): ?string
    {
        $direction = trim((string) $direction);

        if (! in_array($direction, ['Incoming', 'Outgoing'], true)) {
            return null;
        }

        $minutes = is_int($time) ? $time : self::minutesFromTime($time);
        $periodSuffix = $minutes < self::NOON_MINUTES ? 'am' : 'pm';

        return mb_strtolower($direction).'_'.$periodSuffix;
    }

    public static function normalizeClientName(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    public static function findClient(?string $clientName): ?OperationClient
    {
        $normalized = self::normalizeClientName($clientName);

        if ($normalized === '') {
            return null;
        }

        return OperationClient::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
    }

    /**
     * Find the active configuration governing a trip departure, if any.
     * Clients without configuration keep the unrestricted legacy behavior.
     */
    public static function findActiveConfiguration(
        ?string $clientName,
        ?string $direction,
        string|int $departureTime
    ): ?ClientTripTimeConfiguration {
        $category = self::categoryFor($direction, $departureTime);

        if ($category === null) {
            return null;
        }

        $client = self::findClient($clientName);

        if ($client === null) {
            return null;
        }

        return ClientTripTimeConfiguration::query()
            ->where('operation_client_id', $client->id)
            ->where('category', $category)
            ->with(['slots', 'client'])
            ->first();
    }

    /**
     * Server-side enforcement of client trip time configuration for a
     * trip departure. Throws when an active configuration exists for the
     * trip's client and derived category but the departure is not one of
     * its enabled slots. Clients without configuration are unrestricted.
     */
    public static function assertTripDepartureAllowed(
        ?string $clientName,
        ?string $direction,
        string|int $departureTime,
        string $field = 'departure_time'
    ): void {
        $minutes = is_int($departureTime) ? $departureTime : self::minutesFromTime($departureTime);

        $configuration = self::findActiveConfiguration(
            $clientName,
            $direction,
            $minutes
        );

        if ($configuration === null) {
            return;
        }

        $categoryLabel = self::categoryLabel($configuration->category);
        if (! $configuration->is_active) {
            throw ValidationException::withMessages([
                $field => sprintf('%s is disabled for this client. Enable the category in Client Trip Times before scheduling.', $categoryLabel),
            ]);
        }
        $clientLabel = $configuration->client?->name
            ?? trim((string) $clientName);
        $departureLabel = self::displayTime($minutes);
        $windowLabel = self::displayTime($configuration->start_time)
            .' – '.self::displayTime($configuration->end_time);

        if (
            $minutes < $configuration->startMinutes()
            || $minutes > $configuration->endMinutes()
        ) {
            throw ValidationException::withMessages([
                $field => sprintf(
                    '%s is outside the configured %s window (%s) for %s. Choose a configured time or update Client Trip Times.',
                    $departureLabel,
                    $categoryLabel,
                    $windowLabel,
                    $clientLabel
                ),
            ]);
        }

        $allowedSlots = $configuration->enabledSlotMinutes();

        if (! in_array($minutes, $allowedSlots, true)) {
        $allowedLabel = $allowedSlots === []
            ? 'No time slots are currently enabled for this category.'
            : 'Allowed slots: '.implode(', ', array_map(
                self::displayTime(...),
                $allowedSlots
            )).'.';

            throw ValidationException::withMessages([
                $field => sprintf(
                    '%s is not an allowed %s trip time for %s. %s Update Client Trip Times if this needs to change.',
                    $departureLabel,
                    $categoryLabel,
                    $clientLabel,
                    $allowedLabel
                ),
            ]);
        }
    }
}

from __future__ import annotations

from collections import defaultdict
from datetime import datetime, timedelta
from statistics import median
from typing import Literal

from pydantic import BaseModel, Field


class HistoricalTrip(BaseModel):
    route: str
    bus_no: str | None = None
    beginning_at: datetime
    duration_minutes: float = Field(gt=0)
    in_motion_minutes: float | None = Field(default=None, ge=0)
    idling_minutes: float | None = Field(default=None, ge=0)
    mileage_km: float | None = Field(default=None, ge=0)


class ForecastTarget(BaseModel):
    trip_code: str
    route: str
    departure_at: datetime
    bus_no: str | None = None


class FleetTripForecastRequest(BaseModel):
    records: list[HistoricalTrip]
    targets: list[ForecastTarget] = Field(default_factory=list)


class TripPrediction(BaseModel):
    trip_code: str
    route: str
    departure_at: datetime
    predicted_duration_minutes: float
    estimated_arrival_at: datetime
    delay_risk_percent: float
    risk_level: Literal["Low", "Moderate", "High"]
    sample_size: int
    method: str
    baseline_duration_minutes: float


class PeakPeriod(BaseModel):
    period: str
    sample_size: int
    duration_index: float
    speed_index: float | None = None
    interpretation: str


class FleetTripForecastResponse(BaseModel):
    success: bool = True
    model: str = "historical-statistical-v1"
    predictions: list[TripPrediction]
    peak_periods: list[PeakPeriod]
    historical_records: int
    target_count: int
    predicted_target_count: int


def _route_key(route: str) -> str:
    return " ".join(route.strip().lower().replace("–", "-").split())


def _moving_speed(record: HistoricalTrip) -> float | None:
    motion = record.in_motion_minutes or 0
    distance = record.mileage_km or 0

    if motion <= 0 or distance <= 0:
        return None

    return distance / (motion / 60.0)


def _risk_level(percent: float) -> Literal["Low", "Moderate", "High"]:
    if percent >= 60:
        return "High"
    if percent >= 30:
        return "Moderate"
    return "Low"


def _select_comparable_records(
    records: list[HistoricalTrip],
    target: ForecastTarget,
) -> tuple[list[HistoricalTrip], str]:
    same_route = [
        record
        for record in records
        if _route_key(record.route) == _route_key(target.route)
        and record.beginning_at < target.departure_at
    ]

    if not same_route:
        return [], "no route history"

    same_weekday_hour = [
        record
        for record in same_route
        if record.beginning_at.weekday() == target.departure_at.weekday()
        and abs(record.beginning_at.hour - target.departure_at.hour) <= 1
    ]

    if len(same_weekday_hour) >= 3:
        return same_weekday_hour, "route + weekday + departure hour"

    same_hour = [
        record
        for record in same_route
        if abs(record.beginning_at.hour - target.departure_at.hour) <= 1
    ]

    if len(same_hour) >= 3:
        return same_hour, "route + departure hour"

    if len(same_route) >= 3:
        return same_route, "route history fallback"

    return [], "insufficient route history"


def _build_prediction(
    records: list[HistoricalTrip],
    target: ForecastTarget,
) -> TripPrediction | None:
    comparable, method = _select_comparable_records(records, target)

    if len(comparable) < 3:
        return None

    durations = [record.duration_minutes for record in comparable]
    predicted_duration = float(median(durations))

    route_history = [
        record
        for record in records
        if _route_key(record.route) == _route_key(target.route)
        and record.beginning_at < target.departure_at
    ]
    route_durations = [record.duration_minutes for record in route_history]
    route_baseline = float(median(route_durations)) if route_durations else predicted_duration

    delay_threshold = max(route_baseline * 1.20, route_baseline + 10.0)
    delayed_count = sum(
        1 for record in comparable if record.duration_minutes > delay_threshold
    )
    delay_risk = (delayed_count / len(comparable)) * 100.0

    return TripPrediction(
        trip_code=target.trip_code,
        route=target.route,
        departure_at=target.departure_at,
        predicted_duration_minutes=round(predicted_duration, 1),
        estimated_arrival_at=target.departure_at + timedelta(minutes=predicted_duration),
        delay_risk_percent=round(delay_risk, 1),
        risk_level=_risk_level(delay_risk),
        sample_size=len(comparable),
        method=method,
        baseline_duration_minutes=round(route_baseline, 1),
    )


def _build_peak_periods(records: list[HistoricalTrip]) -> list[PeakPeriod]:
    if len(records) < 6:
        return []

    route_groups: dict[str, list[HistoricalTrip]] = defaultdict(list)
    for record in records:
        route_groups[_route_key(record.route)].append(record)

    route_duration_medians: dict[str, float] = {}
    route_speed_medians: dict[str, float] = {}

    for route, route_records in route_groups.items():
        durations = [record.duration_minutes for record in route_records]
        if durations:
            route_duration_medians[route] = float(median(durations))

        speeds = [
            speed
            for record in route_records
            if (speed := _moving_speed(record)) is not None
        ]
        if speeds:
            route_speed_medians[route] = float(median(speeds))

    buckets: dict[int, list[tuple[float, float | None]]] = defaultdict(list)

    for record in records:
        route = _route_key(record.route)
        duration_baseline = route_duration_medians.get(route)
        if not duration_baseline or duration_baseline <= 0:
            continue

        duration_ratio = record.duration_minutes / duration_baseline
        speed = _moving_speed(record)
        route_speed = route_speed_medians.get(route)
        speed_ratio = (
            speed / route_speed
            if speed is not None and route_speed and route_speed > 0
            else None
        )

        bucket_start = (record.beginning_at.hour // 2) * 2
        buckets[bucket_start].append((duration_ratio, speed_ratio))

    periods: list[PeakPeriod] = []

    for bucket_start, values in buckets.items():
        if len(values) < 3:
            continue

        duration_index = float(median([value[0] for value in values]))
        speed_values = [value[1] for value in values if value[1] is not None]
        speed_index = float(median(speed_values)) if speed_values else None

        is_slow_period = duration_index >= 1.10 or (
            speed_index is not None and speed_index <= 0.90
        )
        if not is_slow_period:
            continue

        end_hour = (bucket_start + 2) % 24
        period_label = f"{bucket_start:02d}:00-{end_hour:02d}:00"

        periods.append(
            PeakPeriod(
                period=period_label,
                sample_size=len(values),
                duration_index=round(duration_index, 2),
                speed_index=round(speed_index, 2) if speed_index is not None else None,
                interpretation=(
                    "Historical trips in this time block tend to take longer or move slower "
                    "than their own route baseline. This is a historical peak/slow-period "
                    "indicator, not live traffic data."
                ),
            )
        )

    return sorted(
        periods,
        key=lambda item: (item.duration_index, -(item.speed_index or 1.0)),
        reverse=True,
    )[:4]


def forecast_fleet_trips(
    payload: FleetTripForecastRequest,
) -> FleetTripForecastResponse:
    predictions = [
        prediction
        for target in payload.targets
        if (prediction := _build_prediction(payload.records, target)) is not None
    ]

    return FleetTripForecastResponse(
        predictions=predictions,
        peak_periods=_build_peak_periods(payload.records),
        historical_records=len(payload.records),
        target_count=len(payload.targets),
        predicted_target_count=len(predictions),
    )

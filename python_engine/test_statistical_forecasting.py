"""Regression tests for the five-record historical statistical forecasting gate."""

from datetime import datetime, timedelta

from analytics.forecasting import (
    HistoricalTrip,
    ForecastTarget,
    _build_eligibility,
    _build_prediction,
)


def _scenario(count: int):
    departure = datetime(2026, 11, 9, 8, 0)
    target = ForecastTarget(trip_code="T-001", route="Malvar to Lipa", departure_at=departure)
    records = [
        HistoricalTrip(
            route="Malvar to Lipa",
            beginning_at=departure - timedelta(days=7 * (i + 1)),
            duration_minutes=float([30, 40, 50, 60, 70][i % 5]),
        )
        for i in range(count)
    ]
    return records, target


def test_four_comparable_records_are_insufficient():
    records, target = _scenario(4)
    assert _build_eligibility(records, target).eligible is False
    assert _build_prediction(records, target) is None


def test_five_records_produce_median_duration_and_eta():
    records, target = _scenario(5)
    eligibility = _build_eligibility(records, target)
    result = _build_prediction(records, target)
    assert eligibility.eligible is True
    assert result is not None
    assert result.sample_size == 5
    assert result.predicted_duration_minutes == 50.0
    assert result.estimated_arrival_at == target.departure_at + timedelta(minutes=50)


def test_future_trip_is_excluded_from_history():
    records, target = _scenario(4)
    records.append(
        HistoricalTrip(
            route="Malvar to Lipa",
            beginning_at=target.departure_at + timedelta(days=1),
            duration_minutes=20.0,
        )
    )
    assert _build_prediction(records, target) is None


def test_other_route_cannot_satisfy_history_threshold():
    records, target = _scenario(4)
    records.append(
        HistoricalTrip(
            route="Lipa to Batangas",
            beginning_at=target.departure_at - timedelta(days=1),
            duration_minutes=20.0,
        )
    )
    assert _build_prediction(records, target) is None

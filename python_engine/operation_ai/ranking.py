"""Shared eligibility rules and scoring for Operation AI.

Both recommender (best pick) and analyzer (alternative ranking) reason
about the same drivers and buses. Keeping the rules here guarantees the two
modules never drift apart, so a candidate ranks identically whether it is
being recommended as the primary pick or listed as an alternative.
"""

from typing import Optional

from .schemas import BusData, DriverData

ELIGIBLE_DRIVER_STATUSES = {"Present", "Late"}


def remaining_pms_mileage(bus: BusData) -> Optional[float]:
    """
    Return the remaining mileage before PMS.

    None means that mileage information is incomplete.
    """
    if bus.mileage is None:
        return None

    if bus.next_pms_mileage is not None:
        return bus.next_pms_mileage - bus.mileage


def is_driver_eligible(driver: DriverData, trip_shift: Optional[str]) -> bool:
    """
    Apply hard eligibility rules for drivers.
    """
    if driver.status not in ELIGIBLE_DRIVER_STATUSES:
        return False

    if driver.has_conflict:
        return False

    if trip_shift and driver.shift and driver.shift != trip_shift:
        return False

    return True


def is_bus_eligible(bus: BusData) -> bool:
    """
    Apply hard eligibility rules for buses.
    """
    if bus.status != "Active":
        return False

    if bus.has_conflict:
        return False

    remaining = remaining_pms_mileage(bus)

    if remaining is not None and remaining <= 0:
        return False

    return True


def driver_score(driver: DriverData, trip_shift: Optional[str]) -> int:
    """
    Rank an eligible driver.

    Returns 0 for ineligible drivers so callers can safely use the score
    as a filter. A higher score means a preferable candidate.
    """
    if not is_driver_eligible(driver, trip_shift):
        return 0

    score = 100

    if driver.status == "Late":
        score -= 10

    score -= min(driver.assigned_minutes // 30, 35)

    score -= min(driver.assigned_trips * 5, 25)

    return max(1, min(score, 100))


def bus_score(bus: BusData) -> int:
    """
    Rank an eligible bus.

    Returns 0 for ineligible buses so callers can safely use the score
    as a filter. A higher score means a preferable candidate.
    """
    if not is_bus_eligible(bus):
        return 0

    score = 100

    score -= min(bus.assigned_minutes // 30, 30)

    score -= min(bus.assigned_trips * 5, 20)

    remaining = remaining_pms_mileage(bus)

    if remaining is None:
        score -= 10
    elif remaining <= 500:
        score -= 25
    elif remaining <= 1000:
        score -= 10

    return max(1, min(score, 100))
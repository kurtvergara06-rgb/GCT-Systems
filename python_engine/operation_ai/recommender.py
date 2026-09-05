from typing import Optional

from .analyzer import analyze_recommendation, evaluate_ranking
from .ranking import (
    ELIGIBLE_DRIVER_STATUSES,
    driver_score,
    bus_score,
    is_bus_eligible,
    is_driver_eligible,
    remaining_pms_mileage,
)
from .schemas import (
    AiRecommendationResponse,
    BusData,
    ConflictData,
    ConflictFinding,
    DriverData,
    RecommendationData,
    RecommendedAction,
    SelectedRecommendation,
)


def _driver_rank(driver: DriverData, payload: RecommendationData, outcome) -> int:
    """0-100 ranking for a driver from ML/derived suitability, else rule score."""
    derived = outcome.driver_scores.get(driver.id)
    if derived is not None:
        return max(0, min(100, int(round(derived * 100))))
    return driver_score(driver, payload.trip.shift)


def _bus_rank(bus: BusData, payload: RecommendationData, outcome) -> int:
    """0-100 ranking for a bus from ML suitability, else rule score."""
    if outcome.bus_readiness.ml_ready:
        s = outcome.bus_scores.get(bus.id)
        if s is not None:
            return max(0, min(100, int(round(s * 100))))
    return bus_score(bus)


def select_best_driver(
    payload: RecommendationData,
    outcome=None,
) -> tuple[
    Optional[DriverData],
    int,
]:
    """
    Select the highest-ranked eligible driver.

    Ranking uses the ML/data-derived suitability when available, falling back
    to the rule-based score when the driver model is not ready. ML never
    overrides hard eligibility rules.
    """
    if outcome is None:
        outcome = evaluate_ranking(payload)

    ranked_drivers = [
        (driver, _driver_rank(driver, payload, outcome))
        for driver in payload.eligible_drivers
        if is_driver_eligible(driver, payload.trip.shift)
    ]

    ranked_drivers.sort(
        key=lambda item: (
            -item[1],
            item[0].assigned_minutes,
            item[0].assigned_trips,
            item[0].name.lower(),
        )
    )

    if not ranked_drivers:
        return None, 0

    return ranked_drivers[0]


def select_best_bus(
    payload: RecommendationData,
    outcome=None,
) -> tuple[
    Optional[BusData],
    int,
]:
    """
    Select the highest-ranked eligible bus.

    Ranking uses the ML suitability when the bus model is ready, otherwise the
    rule-based score. ML never overrides hard eligibility rules.
    """
    if outcome is None:
        outcome = evaluate_ranking(payload)

    ranked_buses = [
        (bus, _bus_rank(bus, payload, outcome))
        for bus in payload.eligible_buses
        if is_bus_eligible(bus)
    ]

    ranked_buses.sort(
        key=lambda item: (
            -item[1],
            item[0].assigned_minutes,
            item[0].assigned_trips,
            item[0].bus_no.lower(),
        )
    )

    if not ranked_buses:
        return None, 0

    return ranked_buses[0]


def find_nearest_driver_time(
    drivers: list[DriverData],
) -> Optional[str]:
    """
    Find the nearest known time when a conflicting
    driver becomes available.

    Laravel must still verify this time before applying it.
    """

    times = sorted(
        {
            driver.conflict_end_time
            for driver in drivers
            if driver.has_conflict
            and driver.conflict_end_time
        }
    )

    return times[0] if times else None


def find_nearest_bus_time(
    buses: list[BusData],
) -> Optional[str]:
    """
    Find the nearest known time when a conflicting
    bus becomes available.
    """

    times = sorted(
        {
            bus.conflict_end_time
            for bus in buses
            if bus.has_conflict
            and bus.conflict_end_time
        }
    )

    return times[0] if times else None


def build_driver_findings(
    payload: RecommendationData,
) -> list[ConflictFinding]:
    drivers = payload.eligible_drivers
    findings: list[ConflictFinding] = []

    unavailable_status = [
        driver
        for driver in drivers
        if driver.status
        not in ELIGIBLE_DRIVER_STATUSES
    ]

    wrong_shift = [
        driver
        for driver in drivers
        if (
            payload.trip.shift
            and driver.shift
            and driver.shift
            != payload.trip.shift
        )
    ]

    overlapping = [
        driver
        for driver in drivers
        if driver.has_conflict
    ]

    if unavailable_status:
        findings.append(
            ConflictFinding(
                category="driver_status",
                count=len(unavailable_status),
                explanation=(
                    f"{len(unavailable_status)} driver(s) "
                    "are absent, on leave, on duty, "
                    "or otherwise unavailable."
                ),
            )
        )

    if wrong_shift:
        findings.append(
            ConflictFinding(
                category="shift_mismatch",
                count=len(wrong_shift),
                explanation=(
                    f"{len(wrong_shift)} driver(s) "
                    "belong to a different shift."
                ),
            )
        )

    if overlapping:
        findings.append(
            ConflictFinding(
                category="driver_overlap",
                count=len(overlapping),
                explanation=(
                    f"{len(overlapping)} driver(s) "
                    "have overlapping trip assignments."
                ),
            )
        )

    if not drivers:
        findings.append(
            ConflictFinding(
                category="no_driver_records",
                count=0,
                explanation=(
                    "No driver attendance records were "
                    "provided for this trip."
                ),
            )
        )

    return findings


def build_bus_findings(
    payload: RecommendationData,
) -> list[ConflictFinding]:
    buses = payload.eligible_buses
    findings: list[ConflictFinding] = []

    inactive = [
        bus
        for bus in buses
        if bus.status != "Active"
    ]

    overlapping = [
        bus
        for bus in buses
        if bus.has_conflict
    ]

    pms_due = [
        bus
        for bus in buses
        if (
            remaining_pms_mileage(bus)
            is not None
            and remaining_pms_mileage(bus) <= 0
        )
    ]

    near_pms = [
        bus
        for bus in buses
        if (
            remaining_pms_mileage(bus)
            is not None
            and 0
            < remaining_pms_mileage(bus)
            <= 500
        )
    ]

    if inactive:
        findings.append(
            ConflictFinding(
                category="inactive_bus",
                count=len(inactive),
                explanation=(
                    f"{len(inactive)} bus(es) are inactive "
                    "or under maintenance."
                ),
            )
        )

    if overlapping:
        findings.append(
            ConflictFinding(
                category="bus_overlap",
                count=len(overlapping),
                explanation=(
                    f"{len(overlapping)} bus(es) "
                    "have overlapping trip assignments."
                ),
            )
        )

    if pms_due:
        findings.append(
            ConflictFinding(
                category="bus_pms_due",
                count=len(pms_due),
                explanation=(
                    f"{len(pms_due)} bus(es) have reached "
                    "or exceeded the next PMS mileage."
                ),
            )
        )

    if near_pms:
        findings.append(
            ConflictFinding(
                category="bus_near_pms",
                count=len(near_pms),
                explanation=(
                    f"{len(near_pms)} bus(es) are within "
                    "500 km of their next PMS schedule."
                ),
            )
        )

    if not buses:
        findings.append(
            ConflictFinding(
                category="no_bus_records",
                count=0,
                explanation=(
                    "No bus records were provided "
                    "for this trip."
                ),
            )
        )

    return findings


def build_conflict(
    payload: RecommendationData,
    selected_driver: Optional[DriverData],
    selected_bus: Optional[BusData],
) -> ConflictData:
    """
    Build a structured conflict explanation and actions.
    """

    findings: list[ConflictFinding] = []
    actions: list[RecommendedAction] = []

    if (
        selected_driver is None
        and selected_bus is None
    ):
        conflict_type = (
            "driver_and_bus_unavailable"
        )

        title = (
            "No eligible driver and bus are available."
        )

        explanation = (
            f"Trip {payload.trip.trip_code} cannot be "
            "scheduled because no valid driver-bus "
            "combination is currently available."
        )

        findings.extend(
            build_driver_findings(payload)
        )

        findings.extend(
            build_bus_findings(payload)
        )

    elif selected_driver is None:
        conflict_type = "no_eligible_driver"

        title = "No eligible driver is available."

        explanation = (
            f"Trip {payload.trip.trip_code} has no driver "
            "who satisfies the attendance, shift, "
            "and availability requirements."
        )

        findings.extend(
            build_driver_findings(payload)
        )

    else:
        conflict_type = "no_eligible_bus"

        title = "No eligible bus is available."

        explanation = (
            f"Trip {payload.trip.trip_code} has no bus "
            "that satisfies the active-status, conflict, "
            "and PMS requirements."
        )

        findings.extend(
            build_bus_findings(payload)
        )

    nearest_driver_time = (
        find_nearest_driver_time(
            payload.eligible_drivers
        )
    )

    nearest_bus_time = (
        find_nearest_bus_time(
            payload.eligible_buses
        )
    )

    suggested_times = [
        value
        for value in [
            nearest_driver_time,
            nearest_bus_time,
        ]
        if value
    ]

    if suggested_times:
        suggested_time = max(suggested_times)

        actions.append(
            RecommendedAction(
                type="adjust_departure_time",
                label=(
                    f"Review departure at "
                    f"{suggested_time}"
                ),
                explanation=(
                    "A conflicting resource may become "
                    "available at this time. Laravel must "
                    "recheck the trip duration and all "
                    "assignments before applying the change."
                ),
                suggested_time=suggested_time,
            )
        )

    actions.append(
        RecommendedAction(
            type="review_attendance",
            label="Review driver attendance",
            explanation=(
                "Confirm whether another qualified driver "
                "can be marked available for the trip."
            ),
        )
    )

    actions.append(
        RecommendedAction(
            type="resolve_manually",
            label="Resolve manually",
            explanation=(
                "Open Driver and Bus Assignment and select "
                "a valid resource combination manually."
            ),
        )
    )

    return ConflictData(
        type=conflict_type,
        title=title,
        explanation=explanation,
        findings=findings,
        recommended_actions=actions,
    )


def recommend_trip(
    payload: RecommendationData,
) -> AiRecommendationResponse:
    """
    Generate the best recommendation for one trip.

    The result is only a recommendation.
    Laravel must perform final validation and saving.
    """

    outcome = evaluate_ranking(payload)

    selected_driver, driver_score = (
        select_best_driver(payload, outcome)
    )

    selected_bus, bus_score = (
        select_best_bus(payload, outcome)
    )

    analyzed_payload = payload.model_copy(
        update={
            "selected_driver": selected_driver,
            "selected_bus": selected_bus,
        }
    )

    analysis = analyze_recommendation(
        analyzed_payload
    )

    if (
        selected_driver is None
        or selected_bus is None
    ):
        conflict = build_conflict(
            payload=payload,
            selected_driver=selected_driver,
            selected_bus=selected_bus,
        )

        return AiRecommendationResponse(
            success=True,
            status="conflict",
            recommendation=None,
            analysis=analysis,
            conflict=conflict,
        )

    recommendation = SelectedRecommendation(
        trip=payload.trip,
        driver=selected_driver,
        bus=selected_bus,
        driver_rank_score=driver_score,
        bus_rank_score=bus_score,
    )

    return AiRecommendationResponse(
        success=True,
        status=analysis.status,
        recommendation=recommendation,
        analysis=analysis,
        conflict=None,
    )
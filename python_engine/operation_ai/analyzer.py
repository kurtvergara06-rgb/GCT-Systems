from .ml.predict import evaluate_candidates
from .ranking import (
    ELIGIBLE_DRIVER_STATUSES,
    bus_score,
    driver_score,
    is_bus_eligible,
    is_driver_eligible,
)
from .schemas import (
    AiAnalysis,
    AlternativeItem,
    RecommendationData,
)


def evaluate_ranking(payload: RecommendationData):
    """Run ML suitability evaluation over the payload's eligible candidates.

    Returns a PredictionOutcome. The outcome supplies ML/derived suitability
    for drivers and ML suitability for buses. When a model is not ready, the
    legacy rule-based score is used as the fallback (see driver_rscore /
    bus_rscore below).
    """
    return evaluate_candidates(
        payload.trip,
        payload.eligible_drivers,
        payload.eligible_buses,
        payload.driver_histories,
        payload.bus_histories,
    )


def driver_rscore(driver, payload, outcome) -> int:
    """Return a 0-100 suitability for a driver.

    Uses the ML model score when the driver model is ready; otherwise uses the
    data-derived reliability (attendance-grounded), and finally the legacy
    rule-based score as a safety net.
    """
    derived = outcome.driver_scores.get(driver.id)
    if derived is not None:
        return max(0, min(100, int(round(derived * 100))))
    return driver_score(driver, payload.trip.shift)


def bus_rscore(bus, outcome) -> int:
    """Return a 0-100 suitability for a bus.

    Uses the ML model score when the bus model is ready; otherwise the legacy
    rule-based score.
    """
    if outcome.bus_readiness.ml_ready:
        s = outcome.bus_scores.get(bus.id)
        if s is not None:
            return max(0, min(100, int(round(s * 100))))
    return bus_score(bus)


def analyze_recommendation(
    payload: RecommendationData,
) -> AiAnalysis:
    warnings: list[str] = []
    hard_conflicts: list[str] = []

    outcome = evaluate_ranking(payload)

    score = 100

    driver_explanation = None
    bus_explanation = None

    selected_driver = payload.selected_driver
    selected_bus = payload.selected_bus

    if selected_driver is None:
        score = 0

        hard_conflicts.append(
            "No eligible driver was selected "
            "for this trip."
        )
    else:
        driver_reasons: list[str] = []

        if (
            selected_driver.status
            in ELIGIBLE_DRIVER_STATUSES
        ):
            driver_reasons.append(
                f"the driver is marked as "
                f"{selected_driver.status}"
            )
        else:
            score = 0

            hard_conflicts.append(
                f"Driver status is "
                f"{selected_driver.status}."
            )

        if selected_driver.has_conflict:
            score = 0

            hard_conflicts.append(
                "The selected driver has an "
                "overlapping assignment."
            )
        else:
            driver_reasons.append(
                "the driver has no overlapping assignment"
            )

        if (
            payload.trip.shift
            and selected_driver.shift
            and selected_driver.shift
            != payload.trip.shift
        ):
            score = 0

            hard_conflicts.append(
                f"The driver belongs to the "
                f"{selected_driver.shift} shift, "
                f"but the trip belongs to the "
                f"{payload.trip.shift} shift."
            )
        elif selected_driver.shift:
            driver_reasons.append(
                f"the driver belongs to the "
                f"{selected_driver.shift} shift"
            )

        driver_reasons.append(
            f"the driver currently has "
            f"{selected_driver.assigned_trips} "
            "assigned trip(s)"
        )

        driver_reasons.append(
            f"with approximately "
            f"{selected_driver.assigned_minutes} "
            "assigned minute(s)"
        )

        driver_suit = driver_rscore(selected_driver, payload, outcome)
        if outcome.driver_readiness.ml_ready:
            driver_reasons.append(
                "the ML model ranks this driver highly"
            )

        driver_explanation = (
            f"{selected_driver.name} was recommended "
            "because "
            + ", ".join(driver_reasons)
            + "."
        )

        score -= min(
            selected_driver.assigned_minutes // 30,
            20,
        )

        score -= min(
            selected_driver.assigned_trips * 3,
            15,
        )

        if selected_driver.status == "Late":
            score -= 10

            warnings.append(
                f"{selected_driver.name} is marked Late."
            )

    if selected_bus is None:
        score = 0

        hard_conflicts.append(
            "No eligible active bus was selected."
        )
    else:
        bus_reasons: list[str] = []

        if selected_bus.status == "Active":
            bus_reasons.append(
                "the bus is active"
            )
        else:
            score = 0

            hard_conflicts.append(
                f"Bus status is "
                f"{selected_bus.status}."
            )

        if selected_bus.has_conflict:
            score = 0

            hard_conflicts.append(
                "The selected bus has an "
                "overlapping trip."
            )
        else:
            bus_reasons.append(
                "the bus has no overlapping trip"
            )

        if (
            selected_bus.mileage is not None
            and selected_bus.next_pms_mileage
            is not None
        ):
            remaining_mileage = (
                selected_bus.next_pms_mileage
                - selected_bus.mileage
            )

            if remaining_mileage <= 0:
                score = 0

                hard_conflicts.append(
                    f"Bus {selected_bus.bus_no} "
                    "has reached or exceeded its "
                    "next PMS mileage."
                )
            elif remaining_mileage <= 500:
                score -= 20

                warnings.append(
                    f"Bus {selected_bus.bus_no} is only "
                    f"{remaining_mileage:,.0f} km away "
                    "from its next PMS schedule."
                )
            elif remaining_mileage <= 1000:
                score -= 10

                warnings.append(
                    f"Bus {selected_bus.bus_no} is "
                    f"{remaining_mileage:,.0f} km away "
                    "from its next PMS schedule."
                )
            else:
                bus_reasons.append(
                    "its mileage is still within "
                    "the PMS limit"
                )
        else:
            score -= 10

            warnings.append(
                f"Bus {selected_bus.bus_no} has "
                "incomplete PMS mileage information."
            )

        bus_reasons.append(
            f"the bus currently has "
            f"{selected_bus.assigned_trips} "
            "assigned trip(s)"
        )

        bus_reasons.append(
            f"with approximately "
            f"{selected_bus.assigned_minutes} "
            "assigned minute(s)"
        )

        bus_suit = bus_rscore(selected_bus, outcome)
        if outcome.bus_readiness.ml_ready:
            bus_reasons.append(
                "the ML model ranks this bus highly"
            )

        bus_explanation = (
            f"Bus {selected_bus.bus_no} was recommended "
            "because "
            + ", ".join(bus_reasons)
            + "."
        )

        score -= min(
            selected_bus.assigned_minutes // 30,
            15,
        )

        score -= min(
            selected_bus.assigned_trips * 3,
            15,
        )

    ranked_driver_alternatives = sorted(
        [
            (driver, driver_rscore(driver, payload, outcome))
            for driver in payload.eligible_drivers
            if (
                is_driver_eligible(driver, payload.trip.shift)
                and (
                    selected_driver is None
                    or driver.id != selected_driver.id
                )
            )
        ],
        key=lambda item: (
            -item[1],
            item[0].assigned_minutes,
            item[0].assigned_trips,
            item[0].name.lower(),
        ),
    )

    alternative_drivers = [
        AlternativeItem(
            id=driver.id,
            label=driver.name,
            score=alternative_score,
            reason=(
                f"{driver.status}, "
                f"{driver.assigned_trips} trip(s), "
                f"{driver.assigned_minutes} assigned "
                "minute(s), and no conflict."
            ),
        )
        for driver, alternative_score
        in ranked_driver_alternatives
        if alternative_score > 0
    ][:3]

    ranked_bus_alternatives = sorted(
        [
            (bus, bus_rscore(bus, outcome))
            for bus in payload.eligible_buses
            if (
                is_bus_eligible(bus)
                and (
                    selected_bus is None
                    or bus.id != selected_bus.id
                )
            )
        ],
        key=lambda item: (
            -item[1],
            item[0].assigned_minutes,
            item[0].assigned_trips,
            item[0].bus_no.lower(),
        ),
    )

    alternative_buses = [
        AlternativeItem(
            id=bus.id,
            label=bus.bus_no,
            score=alternative_score,
            reason=(
                "Active bus with no overlapping "
                f"assignment, {bus.assigned_trips} "
                "trip(s), and "
                f"{bus.assigned_minutes} assigned "
                "minute(s)."
            ),
        )
        for bus, alternative_score
        in ranked_bus_alternatives
        if alternative_score > 0
    ][:3]

    score = max(
        0,
        min(score, 100),
    )

    if hard_conflicts:
        status = "conflict"
        score = 0
    elif score >= 85:
        status = "recommended"
    elif score >= 60:
        status = "review"
    else:
        status = "conflict"

    conflict_explanation = (
        " ".join(hard_conflicts)
        if hard_conflicts
        else None
    )

    return AiAnalysis(
        recommendation_score=score,
        status=status,
        driver_explanation=driver_explanation,
        bus_explanation=bus_explanation,
        conflict_explanation=conflict_explanation,
        warnings=warnings,
        alternative_drivers=alternative_drivers,
        alternative_buses=alternative_buses,
        ml_driver_ready=outcome.driver_readiness.ml_ready,
        ml_bus_ready=outcome.bus_readiness.ml_ready,
        ml_driver_source=outcome.driver_readiness.source,
        ml_bus_source=outcome.bus_readiness.source,
        driver_suitability={
            k: max(0, min(100, int(round(v * 100))))
            for k, v in outcome.driver_scores.items()
        },
        bus_suitability={
            k: max(0, min(100, int(round(v * 100))))
            for k, v in outcome.bus_scores.items()
        },
    )

from .schemas import (
    AiAnalysis,
    AlternativeItem,
    RecommendationData,
)


def analyze_recommendation(
    payload: RecommendationData,
) -> AiAnalysis:
    warnings: list[str] = []
    score = 100

    driver_explanation = None
    bus_explanation = None
    conflict_explanation = None

    selected_driver = payload.selected_driver
    selected_bus = payload.selected_bus

    if selected_driver is None:
        score = 0
        conflict_explanation = (
            "No eligible driver was selected for this trip."
        )
    else:
        driver_reasons: list[str] = []

        if selected_driver.status in {"Present", "Late"}:
            driver_reasons.append(
                f"the driver is marked as {selected_driver.status}"
            )
        else:
            score -= 40
            warnings.append(
                f"Driver status is {selected_driver.status}."
            )

        if not selected_driver.has_conflict:
            driver_reasons.append(
                "the driver has no overlapping assignment"
            )
        else:
            score -= 50
            warnings.append(
                "The selected driver has a conflicting assignment."
            )

        if selected_driver.shift:
            driver_reasons.append(
                f"the driver belongs to the "
                f"{selected_driver.shift} shift"
            )

        driver_reasons.append(
            f"the driver currently has "
            f"{selected_driver.assigned_trips} assigned trip(s)"
        )

        driver_explanation = (
            f"{selected_driver.name} was recommended because "
            + ", ".join(driver_reasons)
            + "."
        )

    if selected_bus is None:
        score = 0
        message = "No eligible active bus was selected."

        conflict_explanation = (
            f"{conflict_explanation} {message}".strip()
            if conflict_explanation
            else message
        )
    else:
        bus_reasons: list[str] = []

        if selected_bus.status == "Active":
            bus_reasons.append("the bus is active")
        else:
            score -= 50
            warnings.append(
                f"Bus status is {selected_bus.status}."
            )

        if not selected_bus.has_conflict:
            bus_reasons.append(
                "the bus has no overlapping trip"
            )
        else:
            score -= 50
            warnings.append(
                "The selected bus has a conflicting assignment."
            )

        if (
            selected_bus.mileage is not None
            and selected_bus.next_pms_mileage is not None
        ):
            remaining_mileage = (
                selected_bus.next_pms_mileage
                - selected_bus.mileage
            )

            if remaining_mileage <= 0:
                score -= 40
                warnings.append(
                    f"Bus {selected_bus.bus_no} has reached "
                    "or exceeded its next PMS mileage."
                )
            elif remaining_mileage <= 500:
                score -= 15
                warnings.append(
                    f"Bus {selected_bus.bus_no} is only "
                    f"{remaining_mileage:,.0f} km away from "
                    "its next PMS schedule."
                )
            else:
                bus_reasons.append(
                    "its mileage is still within the PMS limit"
                )

        bus_explanation = (
            f"Bus {selected_bus.bus_no} was recommended because "
            + ", ".join(bus_reasons)
            + "."
        )

    alternative_drivers = [
        AlternativeItem(
            id=driver.id,
            label=driver.name,
            reason=(
                f"{driver.status}, no conflict, "
                f"{driver.assigned_trips} assigned trip(s)."
            ),
        )
        for driver in payload.eligible_drivers
        if not driver.has_conflict
        and (
            selected_driver is None
            or driver.id != selected_driver.id
        )
    ][:3]

    alternative_buses = [
        AlternativeItem(
            id=bus.id,
            label=bus.bus_no,
            reason="Active bus with no overlapping assignment.",
        )
        for bus in payload.eligible_buses
        if bus.status == "Active"
        and not bus.has_conflict
        and (
            selected_bus is None
            or bus.id != selected_bus.id
        )
    ][:3]

    score = max(0, min(score, 100))

    if score >= 85:
        status = "recommended"
    elif score >= 60:
        status = "review"
    else:
        status = "conflict"

    return AiAnalysis(
        recommendation_score=score,
        status=status,
        driver_explanation=driver_explanation,
        bus_explanation=bus_explanation,
        conflict_explanation=conflict_explanation,
        warnings=warnings,
        alternative_drivers=alternative_drivers,
        alternative_buses=alternative_buses,
    )
from typing import Dict, List, Literal, Optional

from pydantic import BaseModel, Field


DriverStatus = Literal[
    "Present",
    "Late",
    "Absent",
    "On Leave",
    "On Duty",
]

BusStatus = Literal[
    "Active",
    "Inactive",
    "Under Maintenance",
]

AnalysisStatus = Literal[
    "recommended",
    "review",
    "conflict",
]

ConflictType = Literal[
    "no_eligible_driver",
    "no_eligible_bus",
    "driver_and_bus_unavailable",
    "shift_mismatch",
    "driver_overlap",
    "bus_overlap",
    "bus_pms_due",
    "insufficient_resources",
]


class DriverData(BaseModel):
    id: int
    name: str
    status: DriverStatus
    shift: Optional[str] = None

    assigned_trips: int = Field(
        default=0,
        ge=0,
    )

    assigned_minutes: int = Field(
        default=0,
        ge=0,
    )

    has_conflict: bool = False

    conflict_end_time: Optional[str] = None


class BusData(BaseModel):
    id: int
    bus_no: str
    status: BusStatus

    mileage: Optional[float] = Field(
        default=None,
        ge=0,
    )

    next_pms_mileage: Optional[float] = Field(
        default=None,
        ge=0,
    )

    assigned_trips: int = Field(
        default=0,
        ge=0,
    )

    assigned_minutes: int = Field(
        default=0,
        ge=0,
    )

    has_conflict: bool = False

    conflict_end_time: Optional[str] = None


class TripData(BaseModel):
    id: int
    trip_code: str
    trip_date: str

    shift: Optional[str] = None

    route_code: Optional[str] = None
    route_name: Optional[str] = None

    departure_time: str
    arrival_time: str


class RecommendationData(BaseModel):
    trip: TripData

    selected_driver: Optional[DriverData] = None
    selected_bus: Optional[BusData] = None

    eligible_drivers: List[DriverData] = Field(
        default_factory=list
    )

    eligible_buses: List[BusData] = Field(
        default_factory=list
    )

    # Optional per-candidate historical profiles supplied by Laravel.
    # driver_histories: {driver_id: {attendance_rate, late_rate, ...}}
    # bus_histories:    {bus_id:      {capacity, fuel_efficiency_avg, ...}}
    driver_histories: Optional[Dict[int, Dict[str, float]]] = None
    bus_histories: Optional[Dict[int, Dict[str, float]]] = None


class AlternativeItem(BaseModel):
    id: int
    label: str
    reason: str
    score: Optional[int] = None


class AiAnalysis(BaseModel):
    recommendation_score: int = Field(
        ge=0,
        le=100,
    )

    status: AnalysisStatus

    driver_explanation: Optional[str] = None
    bus_explanation: Optional[str] = None
    conflict_explanation: Optional[str] = None

    warnings: List[str] = Field(
        default_factory=list
    )

    alternative_drivers: List[AlternativeItem] = Field(
        default_factory=list
    )

    alternative_buses: List[AlternativeItem] = Field(
        default_factory=list
    )

    # --- Machine-learning provenance (additive; never required) ---
    ml_driver_ready: bool = False
    ml_bus_ready: bool = False
    ml_driver_source: str = "rule_fallback"  # ml | data | rule_fallback
    ml_bus_source: str = "rule_fallback"

    # Suitability (0-100) for every eligible candidate, from the ML models
    # (bus) or the data-derived reliability (driver when not ml-ready).
    driver_suitability: Dict[int, int] = Field(default_factory=dict)
    bus_suitability: Dict[int, int] = Field(default_factory=dict)


class AiAnalysisResponse(BaseModel):
    success: bool
    analysis: AiAnalysis


class ConflictFinding(BaseModel):
    category: str
    count: int = Field(ge=0)
    explanation: str


class RecommendedAction(BaseModel):
    type: str
    label: str
    explanation: str

    suggested_time: Optional[str] = None
    driver_id: Optional[int] = None
    bus_id: Optional[int] = None


class ConflictData(BaseModel):
    type: ConflictType
    title: str
    explanation: str

    findings: List[ConflictFinding] = Field(
        default_factory=list
    )

    recommended_actions: List[RecommendedAction] = Field(
        default_factory=list
    )


class SelectedRecommendation(BaseModel):
    trip: TripData

    driver: DriverData
    bus: BusData

    driver_rank_score: int = Field(
        ge=0,
        le=100,
    )

    bus_rank_score: int = Field(
        ge=0,
        le=100,
    )


class AiRecommendationResponse(BaseModel):
    success: bool

    status: AnalysisStatus

    recommendation: Optional[
        SelectedRecommendation
    ] = None

    analysis: AiAnalysis

    conflict: Optional[ConflictData] = None


class MlTrainingStatusResponse(BaseModel):
    success: bool
    bus_model_ready: bool = False
    driver_model_ready: bool = False
    bus_source: str = "rule_fallback"  # ml | data | rule_fallback
    driver_source: str = "rule_fallback"
    bus_reason: Optional[str] = None
    driver_reason: Optional[str] = None
    bus_sample_count: int = 0
    driver_sample_count: int = 0
from typing import List, Optional

from pydantic import BaseModel, Field


class DriverData(BaseModel):
    id: int
    name: str
    status: str
    shift: Optional[str] = None
    assigned_trips: int = 0
    assigned_minutes: int = 0
    has_conflict: bool = False


class BusData(BaseModel):
    id: int
    bus_no: str
    status: str
    mileage: Optional[float] = None
    next_pms_mileage: Optional[float] = None
    has_conflict: bool = False


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
    eligible_drivers: List[DriverData] = Field(default_factory=list)
    eligible_buses: List[BusData] = Field(default_factory=list)


class AlternativeItem(BaseModel):
    id: int
    label: str
    reason: str


class AiAnalysis(BaseModel):
    recommendation_score: int
    status: str
    driver_explanation: Optional[str] = None
    bus_explanation: Optional[str] = None
    conflict_explanation: Optional[str] = None
    warnings: List[str] = Field(default_factory=list)
    alternative_drivers: List[AlternativeItem] = Field(default_factory=list)
    alternative_buses: List[AlternativeItem] = Field(default_factory=list)


class AiAnalysisResponse(BaseModel):
    success: bool
    analysis: AiAnalysis
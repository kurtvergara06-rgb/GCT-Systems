"""CI smoke test for the tracked Fuel Model #2 runtime artifacts.

Unlike ``fuel.test_fuel`` this test does not require the gitignored training
CSV. It verifies that the committed genuine-data model can load and serve the
FastAPI contract used by Laravel.
"""

from fastapi import FastAPI
from fastapi.testclient import TestClient

from .router import router


app = FastAPI()
app.include_router(router, prefix="/fuel")
client = TestClient(app)

status = client.get("/fuel/status")
assert status.status_code == 200, status.text
status_data = status.json()
assert status_data["success"] is True
assert status_data["model_ready"] is True
assert status_data["data_source"] == "genuine"
assert status_data["dataset_type"] == "GENUINE GCT RECORDS"
assert status_data["is_production_model"] is True
assert int(status_data["sample_count"]) >= 50

response = client.post(
    "/fuel/predict",
    json={
        "route": "Talisay - SM Seaside",
        "trip_started_at": "2026-09-10T08:00:00",
        "bus_no": "GCT-101",
        "distance_km": 11.7,
        "trip_duration_minutes": 55,
        "in_motion_minutes": 50,
        "idling_minutes": 5,
        "engine_on_hours": 0.92,
    },
)
assert response.status_code == 200, response.text
prediction = response.json()
assert prediction["success"] is True
assert prediction["data_source"] == "genuine"
assert prediction["is_production_model"] is True
assert prediction["predicted_fuel_liters"] is not None
assert 0.73 <= float(prediction["predicted_fuel_liters"]) <= 4.57

print("Fuel Model #2 API smoke test PASS")
print(f"  training records: {status_data['sample_count']}")
print(f"  prediction: {prediction['predicted_fuel_liters']} L")

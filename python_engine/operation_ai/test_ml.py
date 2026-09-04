"""Comprehensive tests for the scheduling ML pipeline."""

import json
import os
import sys
import tempfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import torch
from operation_ai.ml_model import SchedulingScorer
from operation_ai.ml_features import encode_trip, encode_driver, encode_bus, encode_all, shift_match
from operation_ai.ml_scorer import ml_score, _load_model, MODEL_PATH
from operation_ai.schemas import TripData, DriverData, BusData
from operation_ai.ranking import driver_score, bus_score, is_driver_eligible, is_bus_eligible

PASS = 0
FAIL = 0


def check(label: str, condition: bool, detail: str = ""):
    global PASS, FAIL
    status = "PASS" if condition else "FAIL"
    if not condition:
        FAIL += 1
    else:
        PASS += 1
    suffix = f" ({detail})" if detail else ""
    print(f"  [{status}] {label}{suffix}")


# ============================================================
# 1. ml_model.py
# ============================================================
print("\n=== ml_model.py ===")

model = SchedulingScorer(trip_dim=5, driver_dim=5, bus_dim=5, hidden=64)
model.eval()
check("model instantiation", model is not None)

trip_t = torch.randn(1, 5)
driver_t = torch.randn(1, 5)
bus_t = torch.randn(1, 5)

output = model(trip_t, driver_t, bus_t)
check("output shape", output.shape == (1, 1), f"got {output.shape}")
check("output in [0,100]", 0 <= output.item() <= 100, f"got {output.item():.2f}")

# Batch forward
batch_trip = torch.randn(32, 5)
batch_driver = torch.randn(32, 5)
batch_bus = torch.randn(32, 5)
batch_out = model(batch_trip, batch_driver, batch_bus)
check("batch output shape", batch_out.shape == (32, 1), f"got {batch_out.shape}")
check("batch all in [0,100]", batch_out.min().item() >= 0 and batch_out.max().item() <= 100)

# Gradient flow
model.train()
batch_trip_g = torch.randn(4, 5)
batch_driver_g = torch.randn(4, 5)
batch_bus_g = torch.randn(4, 5)
batch_trip_g.requires_grad_(True)
batch_driver_g.requires_grad_(True)
batch_bus_g.requires_grad_(True)
out = model(batch_trip_g, batch_driver_g, batch_bus_g)
loss = out.sum()
loss.backward()
check("gradient flows", batch_trip_g.grad is not None, f"grad norm={batch_trip_g.grad.norm():.4f}")
model.eval()

# Save/load roundtrip
with tempfile.NamedTemporaryFile(suffix=".pt", delete=False) as f:
    tmp_path = f.name
    torch.save(model.state_dict(), tmp_path)

model2 = SchedulingScorer()
model2.load_state_dict(torch.load(tmp_path, weights_only=True))
model2.eval()
model.eval()
with torch.no_grad():
    orig = model(trip_t.detach(), driver_t.detach(), bus_t.detach())
    loaded = model2(trip_t.detach(), driver_t.detach(), bus_t.detach())
check("save/load roundtrip", torch.allclose(orig, loaded, atol=1e-5), f"max diff={(orig-loaded).abs().max():.6f}")
os.unlink(tmp_path)


# ============================================================
# 2. ml_features.py
# ============================================================
print("\n=== ml_features.py ===")

trip = TripData(id=1, trip_code="TRIP-001", trip_date="2026-09-04", shift="Morning", departure_time="08:00", arrival_time="09:00", route_code="Lipa-Batangas")
driver = DriverData(id=1, name="Test", status="Present", shift="Morning", assigned_trips=2, assigned_minutes=60, has_conflict=False)
bus = BusData(id=1, bus_no="GCT-001", status="Active", assigned_trips=1, assigned_minutes=30, has_conflict=False, mileage=10000, next_pms_mileage=15000)

t, d, b = encode_all(trip, driver, bus)
check("trip tensor shape", t.shape == (5,), f"got {t.shape}")
check("driver tensor shape", d.shape == (5,), f"got {d.shape}")
check("bus tensor shape", b.shape == (5,), f"got {b.shape}")
check("trip shift encoded", t[0].item() == 0.0, f"Morning should be 0, got {t[0].item()}")
check("driver status encoded", d[0].item() == 0.0, f"Present should be 0, got {d[0].item()}")
check("bus status encoded", b[0].item() == 0.0, f"Active should be 0, got {b[0].item()}")
check("driver shift_match=1", shift_match(trip, driver) is True)
check("driver shift_mismatch", shift_match(TripData(id=1, trip_code="T", trip_date="x", shift="Night", departure_time="22:00", arrival_time="23:00", route_code="X"), driver) is False)
check("bus PMS pct positive", b[4].item() > 0, f"got {b[4].item():.3f}")

# Edge cases
check("None shift encoded", encode_trip(TripData(id=1, trip_code="T", trip_date="x", shift=None, departure_time="08:00", arrival_time="09:00", route_code="X"))[0].item() == -1.0)
check("None status encoded", encode_driver(DriverData(id=1, name="X", status="Present", shift=None, assigned_trips=0, assigned_minutes=0))[1].item() == 0.0)
check("None mileage PMS pct", encode_bus(BusData(id=1, bus_no="X", status="Active", mileage=None, next_pms_mileage=None))[4].item() == -1.0)


# ============================================================
# 3. ml_scorer.py
# ============================================================
print("\n=== ml_scorer.py ===")

check("model file exists", MODEL_PATH.exists(), str(MODEL_PATH))

model_loaded = _load_model()
check("model loads", model_loaded is not None)

score = ml_score(trip, driver, bus)
check("ml_score returns float", isinstance(score, float), f"got {type(score)}")
check("ml_score in range", 0 <= score <= 100, f"got {score}")

# Scoring consistency
score2 = ml_score(trip, driver, bus)
check("ml_score deterministic", score == score2, f"{score} vs {score2}")

# Ineligible pair should score low
bad_driver = DriverData(id=2, name="Bad", status="Absent", shift="Morning", assigned_trips=0, assigned_minutes=0, has_conflict=False)
bad_bus = BusData(id=2, bus_no="GCT-002", status="Inactive", mileage=5000, next_pms_mileage=10000)
bad_score = ml_score(trip, bad_driver, bad_bus)
good_score = ml_score(trip, driver, bus)
check("ineligible scores lower", bad_score < good_score, f"bad={bad_score}, good={good_score}")

# Fallback when model missing
import operation_ai.ml_scorer as ms
original_path = ms.MODEL_PATH
ms.MODEL_PATH = Path("/nonexistent/model.pt")
ms._model = None
ms._model_loaded = False
fallback = ml_score(trip, driver, bus)
check("fallback returns None", fallback is None)
ms.MODEL_PATH = original_path
ms._model = None
ms._model_loaded = False
_load_model()  # reload


# ============================================================
# 4. generate_training_data.py
# ============================================================
print("\n=== generate_training_data.py ===")

from operation_ai.training.generate_training_data import generate_sample, generate

# Sample format
sample = generate_sample()
check("sample has trip", "trip" in sample)
check("sample has driver", "driver" in sample)
check("sample has bus", "bus" in sample)
check("sample has label", "label" in sample)
check("label is number", isinstance(sample["label"], (int, float)), f"got {type(sample['label'])}")

# Batch generation with balance
with tempfile.NamedTemporaryFile(suffix=".jsonl", delete=False) as f:
    tmp_jsonl = f.name

generate(200, output_path=tmp_jsonl)

with open(tmp_jsonl) as f:
    lines = [json.loads(l) for l in f if l.strip()]

eligible = sum(1 for s in lines if s["eligible"])
ineligible = len(lines) - eligible
ratio = eligible / len(lines) if lines else 0
check("200 samples generated", len(lines) == 200, f"got {len(lines)}")
check("balanced (40-60%)", 0.35 <= ratio <= 0.65, f"eligible={eligible}, ratio={ratio:.2f}")
check("label range", all(0 <= s["label"] <= 100 for s in lines))
os.unlink(tmp_jsonl)


# ============================================================
# 5. train_scheduling_model.py
# ============================================================
print("\n=== train_scheduling_model.py ===")

# Generate fresh data
with tempfile.NamedTemporaryFile(suffix=".jsonl", delete=False) as f:
    train_jsonl = f.name
generate(500, output_path=train_jsonl)

from operation_ai.training.train_scheduling_model import train

with tempfile.TemporaryDirectory() as tmpdir:
    # Monkey-patch MODELS_DIR
    import operation_ai.training.train_scheduling_model as tsm
    original_models_dir = tsm.MODELS_DIR
    tsm.MODELS_DIR = Path(tmpdir)

    trained_model = train(train_jsonl)

    saved_model = Path(tmpdir) / "scheduling_model.pt"
    saved_report = Path(tmpdir) / "scheduling_report.txt"
    check("model saved", saved_model.exists())
    check("report saved", saved_report.exists())
    check("train returns model", trained_model is not None)

    # Load and verify
    test_model = SchedulingScorer(trip_dim=5, driver_dim=5, bus_dim=5)
    test_model.load_state_dict(torch.load(saved_model, weights_only=True))
    test_model.eval()
    with torch.no_grad():
        out = test_model(torch.randn(1, 5), torch.randn(1, 5), torch.randn(1, 5))
    check("trained model produces output", out.shape == (1, 1))

    tsm.MODELS_DIR = original_models_dir

os.unlink(train_jsonl)


# ============================================================
# 6. Integration: ML vs Rule-based
# ============================================================
print("\n=== Integration: ML vs Rule-based ===")

scenarios = [
    ("Perfect pair", {"shift": "Morning", "dep": "08:00", "arr": "09:00", "route": "Lipa-Batangas"},
     {"status": "Present", "shift": "Morning", "trips": 0, "mins": 0, "conflict": False},
     {"status": "Active", "trips": 0, "mins": 0, "conflict": False, "mileage": 10000, "pms": 20000}),
    ("Heavy workload", {"shift": "Morning", "dep": "08:00", "arr": "09:00", "route": "Lipa-Batangas"},
     {"status": "Present", "shift": "Morning", "trips": 5, "mins": 240, "conflict": False},
     {"status": "Active", "trips": 4, "mins": 200, "conflict": False, "mileage": 10000, "pms": 20000}),
    ("Late driver", {"shift": "Morning", "dep": "08:00", "arr": "09:00", "route": "Lipa-Batangas"},
     {"status": "Late", "shift": "Morning", "trips": 1, "mins": 30, "conflict": False},
     {"status": "Active", "trips": 0, "mins": 0, "conflict": False, "mileage": 10000, "pms": 20000}),
    ("Near PMS", {"shift": "Morning", "dep": "08:00", "arr": "09:00", "route": "Lipa-Batangas"},
     {"status": "Present", "shift": "Morning", "trips": 0, "mins": 0, "conflict": False},
     {"status": "Active", "trips": 0, "mins": 0, "conflict": False, "mileage": 49000, "pms": 50000}),
    ("Shift mismatch", {"shift": "Morning", "dep": "08:00", "arr": "09:00", "route": "Lipa-Batangas"},
     {"status": "Present", "shift": "Night", "trips": 0, "mins": 0, "conflict": False},
     {"status": "Active", "trips": 0, "mins": 0, "conflict": False, "mileage": 10000, "pms": 20000}),
    ("Driver conflict", {"shift": "Morning", "dep": "08:00", "arr": "09:00", "route": "Lipa-Batangas"},
     {"status": "Present", "shift": "Morning", "trips": 0, "mins": 0, "conflict": True},
     {"status": "Active", "trips": 0, "mins": 0, "conflict": False, "mileage": 10000, "pms": 20000}),
]

print(f"  {'Scenario':<20} {'Rule-driver':>11} {'Rule-bus':>9} {'ML':>7} {'Match':>6}")
print(f"  {'-'*20} {'-'*11} {'-'*9} {'-'*7} {'-'*6}")

for name, td, dd, bd in scenarios:
    t = TripData(id=1, trip_code="T", trip_date="2026-09-04", shift=td["shift"],
                 departure_time=td["dep"], arrival_time=td["arr"], route_code=td["route"])
    dr = DriverData(id=1, name="D", status=dd["status"], shift=dd["shift"],
                    assigned_trips=dd["trips"], assigned_minutes=dd["mins"], has_conflict=dd["conflict"])
    b = BusData(id=1, bus_no="B", status=bd["status"],
                assigned_trips=bd["trips"], assigned_minutes=bd["mins"], has_conflict=bd["conflict"],
                mileage=bd["mileage"], next_pms_mileage=bd["pms"])

    r_d = driver_score(dr, td["shift"])
    r_b = bus_score(b)
    ml = ml_score(t, dr, b)

    rule_eligible = r_d > 0 and r_b > 0
    ml_high = ml > 50 if rule_eligible else ml < 50
    match_str = "OK" if ml_high else "WARN"

    print(f"  {name:<20} {r_d:>11} {r_b:>9} {ml:>7.1f} {match_str:>6}")
    check(f"{name} direction correct", ml_high, f"rule_eligible={rule_eligible}, ml={ml:.1f}")


# ============================================================
# Summary
# ============================================================
print(f"\n{'='*50}")
print(f"Results: {PASS} passed, {FAIL} failed")
print(f"{'='*50}")

sys.exit(1 if FAIL > 0 else 0)

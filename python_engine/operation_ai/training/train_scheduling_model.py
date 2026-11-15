"""Train the scheduling neural network.

Reads generated training data (JSONL), trains a SchedulingScorer model,
and saves the best checkpoint to operation_ai/models/scheduling_model.pt.

Usage:
    python -m operation_ai.training.train_scheduling_model [path_to_jsonl]

Output:
    python_engine/operation_ai/models/scheduling_model.pt
    python_engine/operation_ai/models/scheduling_report.txt
"""

import json
import os
import random
import sys
from pathlib import Path

_HERE = Path(__file__).resolve().parent
_PARENT = _HERE.parent
if str(_PARENT.parent) not in sys.path:
    sys.path.insert(0, str(_PARENT.parent))

import torch
import torch.nn as nn
from torch.utils.data import DataLoader, TensorDataset

from operation_ai.ml_features import encode_bus, encode_driver, encode_trip
from operation_ai.ml_model import SchedulingScorer
from operation_ai.schemas import BusData, DriverData, TripData

MODELS_DIR = _PARENT / "models"
DEFAULT_DATA = Path(__file__).resolve().parent.parent.parent / "training_data" / "scheduling_training.jsonl"

TRIP_DIM = 5
DRIVER_DIM = 5
BUS_DIM = 5
HIDDEN = 64

BATCH_SIZE = 128
EPOCHS = 60
LR = 1e-3
WEIGHT_DECAY = 1e-5
PATIENCE = 10
VAL_SPLIT = 0.2


def _load_jsonl(path: str | Path) -> list[dict]:
    samples = []
    with open(path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                samples.append(json.loads(line))
    return samples


def _to_tensors(samples: list[dict]) -> TensorDataset:
    trips, drivers, buses, labels = [], [], [], []

    for s in samples:
        trip_obj = TripData(
            id=0, trip_code="x", trip_date="x",
            shift=s["trip"]["shift"],
            departure_time=s["trip"]["departure_time"],
            arrival_time=s["trip"].get("arrival_time", ""),
            route_code=s["trip"].get("route_code"),
        )
        driver_obj = DriverData(
            id=0, name="x",
            status=s["driver"]["status"],
            shift=s["driver"]["shift"],
            assigned_trips=s["driver"]["assigned_trips"],
            assigned_minutes=s["driver"]["assigned_minutes"],
            has_conflict=s["driver"]["has_conflict"],
        )
        bus_obj = BusData(
            id=0, bus_no="x",
            status=s["bus"]["status"],
            assigned_trips=s["bus"]["assigned_trips"],
            assigned_minutes=s["bus"]["assigned_minutes"],
            has_conflict=s["bus"]["has_conflict"],
            mileage=s["bus"].get("mileage"),
            next_pms_mileage=s["bus"].get("next_pms_mileage"),
        )

        t = encode_trip(trip_obj, driver_obj.shift)
        d = encode_driver(driver_obj)
        b = encode_bus(bus_obj)

        trips.append(t)
        drivers.append(d)
        buses.append(b)
        labels.append(torch.tensor([s["label"]], dtype=torch.float32))

    return TensorDataset(
        torch.stack(trips),
        torch.stack(drivers),
        torch.stack(buses),
        torch.stack(labels),
    )


def train(data_path: str | Path = DEFAULT_DATA):
    samples = _load_jsonl(data_path)
    print(f"Loaded {len(samples)} training samples")

    dataset = _to_tensors(samples)
    n_val = int(len(dataset) * VAL_SPLIT)
    n_train = len(dataset) - n_val
    train_set, val_set = torch.utils.data.random_split(dataset, [n_train, n_val])

    train_loader = DataLoader(train_set, batch_size=BATCH_SIZE, shuffle=True)
    val_loader = DataLoader(val_set, batch_size=BATCH_SIZE)

    model = SchedulingScorer(TRIP_DIM, DRIVER_DIM, BUS_DIM, HIDDEN)
    optimizer = torch.optim.Adam(model.parameters(), lr=LR, weight_decay=WEIGHT_DECAY)
    criterion = nn.MSELoss()
    scheduler = torch.optim.lr_scheduler.ReduceLROnPlateau(optimizer, patience=5, factor=0.5)

    best_val_loss = float("inf")
    patience_counter = 0

    for epoch in range(1, EPOCHS + 1):
        model.train()
        train_loss = 0.0
        for trip_t, driver_t, bus_t, label in train_loader:
            optimizer.zero_grad()
            pred = model(trip_t, driver_t, bus_t)
            loss = criterion(pred, label)
            loss.backward()
            optimizer.step()
            train_loss += loss.item() * len(label)

        train_loss /= n_train

        model.eval()
        val_loss = 0.0
        with torch.no_grad():
            for trip_t, driver_t, bus_t, label in val_loader:
                pred = model(trip_t, driver_t, bus_t)
                val_loss += criterion(pred, label).item() * len(label)
        val_loss /= n_val

        scheduler.step(val_loss)

        if epoch % 10 == 0 or epoch == 1:
            print(f"Epoch {epoch:3d}  train_loss={train_loss:.4f}  val_loss={val_loss:.4f}")

        if val_loss < best_val_loss:
            best_val_loss = val_loss
            patience_counter = 0
            MODELS_DIR.mkdir(parents=True, exist_ok=True)
            torch.save(model.state_dict(), MODELS_DIR / "scheduling_model.pt")
        else:
            patience_counter += 1
            if patience_counter >= PATIENCE:
                print(f"Early stopping at epoch {epoch}")
                break

    print(f"\nBest val_loss: {best_val_loss:.4f}")
    print(f"Model saved to {MODELS_DIR / 'scheduling_model.pt'}")

    report_path = MODELS_DIR / "scheduling_report.txt"
    with open(report_path, "w", encoding="utf-8") as f:
        f.write(f"Training samples: {len(samples)}\n")
        f.write(f"Epochs trained: {epoch}\n")
        f.write(f"Best validation loss (MSE): {best_val_loss:.4f}\n")
        f.write(f"Architecture: SchedulingScorer(trip={TRIP_DIM}, driver={DRIVER_DIM}, bus={BUS_DIM}, hidden={HIDDEN})\n")

    return model


if __name__ == "__main__":
    path = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_DATA
    train(path)

"""Read-only artifact inventory. Does not deserialize models, train, query DBs or alter artifacts.

Run: python tools/audit_ml_provenance.py > storage/logs/ml-provenance-inventory.json
Hashes preserve evidence; they do not certify the truth of metadata source claims.
"""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ENGINE = ROOT / "python_engine"
groups = {"eta": "eta_duration_state.json", "fuel": "fuel_liters_state.json",
          "delay": "delay_arrival_state.json", "inventory": "inventory_demand_state.json",
          "operation_ai": "scheduling_ml_state.json"}
inventory = []
for group, filename in groups.items():
    folder = ENGINE / group / "models"
    state = json.loads((folder / filename).read_text(encoding="utf-8"))
    artifacts = [{"path": p.relative_to(ROOT).as_posix(), "bytes": p.stat().st_size,
                  "sha256": hashlib.sha256(p.read_bytes()).hexdigest()}
                 for p in sorted(folder.iterdir()) if p.is_file()]
    inventory.append({"group": group, "audit_result": "NOT_VERIFIED",
                      "source_claim": state.get("training_source", "unknown"),
                      "reported_counts": {k: v for k, v in state.items() if k.endswith("sample_count")},
                      "source_record_ids_in_state": "source_record_ids" in state,
                      "dataset_hash_in_state": "dataset_hash" in state,
                      "provenance_manifest_in_state": "provenance_manifest" in state,
                      "artifacts": artifacts})
csv_paths = sorted(p.relative_to(ROOT).as_posix() for p in (ENGINE / "training_data").rglob("*.csv"))
print(json.dumps({"scope": "Local retained artifacts; no production database lineage audit performed",
                  "groups": inventory, "available_training_csv_paths": csv_paths}, indent=2))

"""Fuel Consumption Prediction.

Random Forest model that predicts the fuel consumed on a real trip
(``fuel_reports.fuel_liters``, in liters) from historical, per-trip
operational measurements: route, bus, distance, trip duration, time in
motion, idling time, engine-on hours, average speed and clock context.

Unlike ETA Model #1, the prediction uses trip measurements that are known
once the trip is complete (the GPS fuel-consumption report is generated
after the run). These values are measured independently of fuel quantity, so
the label NEVER appears among the input features (no target leakage). In
particular ``km_per_liter`` is rejected because it is distance / fuel and
therefore contains the label.
"""

from __future__ import annotations
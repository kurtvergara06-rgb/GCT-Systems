"""ETA / Trip Duration Prediction.

Random Forest model that predicts actual trip duration (minutes) from
historical GPS trip records, using only features that are known before
departure (route, distance, scheduled hour, day characteristics, bus).

The label NEVER appears among the input features (no target leakage).
"""
"""Model #3 - Delay Prediction (trip late/early).

Two explicit, isolated training-data sources:

    * ``sample``  (DEFAULT): random-forest model for development/demonstration,
                  trained on a deterministic SAMPLE dataset.
    * ``genuine`` (opt-in via ``DELAY_DATA_SOURCE=genuine``): production-quality
                  model trained ONLY on genuine matched DDR history exported by
                  Laravel (php artisan delay:export-genuine). If the genuine
                  data is missing or fails the data-sufficiency gate, the
                  pipeline reports "NOT READY FOR GENUINE TRAINING" and NEVER
                  silently falls back to the sample dataset.

Random Forest model that predicts the expected ARRIVAL delay in minutes
(``arrival_delay_minutes``) for a scheduled trip from pre-trip features only:

    route, bus, driver, scheduled departure hour/minute, day of week,
    weekday/weekend, month, season, scheduled duration, route distance,
    route-level historical delay statistics (strictly prior observations),
    driver trip sequence within the day, and pre-trip incident context
    (incidents reported before the scheduled departure; replacement-bus
    dispatch already initiated).

The label NEVER appears among the input features (no target leakage):
actual departure/arrival times, actual duration and both delay targets are
excluded from the feature set.

The prediction is complemented by a separate business-rule (non-ML) threshold
layer that maps the numeric prediction to an On Time / Minor / Moderate /
High Delay band. See predict.py and DELAY_MODEL_REPORT.md.
"""

from .config import disclaimers  # noqa: F401  (re-exported for API consumers)
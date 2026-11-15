"""Model #3 - Delay Prediction (trip late/early).

DEVELOPMENT PROTOTYPE - SAMPLE / DEMONSTRATION DATA.

Random Forest model that predicts the expected ARRIVAL delay in minutes
(``arrival_delay_minutes``) for a scheduled trip from pre-trip features only:

    route, bus, driver, scheduled departure hour/minute, day of week,
    weekday/weekend, month, season, scheduled duration, route distance,
    route-level historical delay statistics (strictly prior observations), and
    driver trip sequence within the day.

The label NEVER appears among the input features (no target leakage):
actual departure/arrival times, actual duration and both delay targets are
excluded from the feature set.

The prediction is complemented by a separate business-rule (non-ML) threshold
layer that maps the numeric prediction to an On Time / Minor / Moderate /
High Delay band. See predict.py and DELAY_MODEL_REPORT.md.

IMPORTANT: trained on a SEPARATE SAMPLE dataset, NOT genuine GCT operational
historical delay records. See DELAY_MODEL_READINESS.md for why.
"""

from .config import DISCLAIMER  # noqa: F401  (re-exported for API consumers)
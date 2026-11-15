"""Frontend-visible DEMO warehouse data adapter for Inventory Model #4."""

from __future__ import annotations

import logging

import pandas as pd

from .training_data import REQUIRED_COLUMNS, _panelize_genuine_movements

logger = logging.getLogger(__name__)


def fetch_demo_stock_movements() -> pd.DataFrame:
    """Read only ClientDemoDataSeeder warehouse movements (source='demo')."""
    from operation_ai.ml.database import DbConnection

    db = DbConnection()
    try:
        sql = """
            SELECT
                sm.id                                   AS movement_id,
                sm.created_at                           AS activity_at,
                sm.inventory_item_id                    AS part_id,
                COALESCE(ii.item_code, sm.item_code)   AS item_code,
                COALESCE(ii.item_name, sm.item_name)   AS part_name,
                COALESCE(ii.category, 'Uncategorized') AS category,
                COALESCE(sm.unit, ii.unit_of_measurement, 'pcs') AS unit,
                sm.reference_no                         AS reference_no,
                sm.movement_type                        AS movement_type,
                sm.quantity_change                      AS quantity_change,
                sm.previous_stock                       AS previous_stock,
                sm.new_stock                            AS new_stock,
                COALESCE(ii.reorder_level, 0)           AS reorder_level
            FROM stock_movements sm
            LEFT JOIN inventory_items ii
              ON ii.id = sm.inventory_item_id
            WHERE sm.source = 'demo'
              AND sm.reference_no LIKE 'DEMO-%'
              AND sm.inventory_item_id IS NOT NULL
            ORDER BY sm.inventory_item_id, sm.created_at, sm.id
        """
        raw = db.query_df(sql)
    finally:
        db.close()

    if raw.empty:
        logger.warning(
            "No source='demo' stock movements found. Run ClientDemoDataSeeder first."
        )
        return pd.DataFrame(columns=REQUIRED_COLUMNS + ["stock_out_events", "data_source"])

    panel = _panelize_genuine_movements(raw)
    if not panel.empty:
        panel["data_source"] = "demo"
    return panel

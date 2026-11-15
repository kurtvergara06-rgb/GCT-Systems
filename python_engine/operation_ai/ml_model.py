"""Neural network for scheduling recommendation scoring.

Architecture: Two-branch MLP that processes driver and bus features
separately, then fuses with trip context to produce a score (0-100).

The model learns from rule-based scorer output initially, then can be
retrained on real trip outcomes as data accumulates.
"""

import torch
import torch.nn as nn


class SchedulingScorer(nn.Module):
    """Scores a (trip, driver, bus) triple for scheduling recommendation.

    Input dimensions:
        trip_dim   = 5  (shift_encoded, departure_hour, route_encoded, est_duration, shift_match)
        driver_dim = 5  (status_encoded, has_shift, assigned_trips, assigned_minutes, has_conflict)
        bus_dim    = 5  (status_encoded, assigned_trips, assigned_minutes, has_conflict, remaining_pms_pct)
    """

    def __init__(
        self,
        trip_dim: int = 5,
        driver_dim: int = 5,
        bus_dim: int = 5,
        hidden: int = 64,
    ):
        super().__init__()

        self.driver_net = nn.Sequential(
            nn.Linear(driver_dim, hidden),
            nn.ReLU(),
            nn.BatchNorm1d(hidden),
            nn.Dropout(0.2),
            nn.Linear(hidden, hidden // 2),
            nn.ReLU(),
        )

        self.bus_net = nn.Sequential(
            nn.Linear(bus_dim, hidden),
            nn.ReLU(),
            nn.BatchNorm1d(hidden),
            nn.Dropout(0.2),
            nn.Linear(hidden, hidden // 2),
            nn.ReLU(),
        )

        fusion_dim = (hidden // 2) + (hidden // 2) + trip_dim

        self.fusion = nn.Sequential(
            nn.Linear(fusion_dim, hidden),
            nn.ReLU(),
            nn.BatchNorm1d(hidden),
            nn.Dropout(0.2),
            nn.Linear(hidden, hidden // 2),
            nn.ReLU(),
            nn.Linear(hidden // 2, 1),
            nn.Sigmoid(),
        )

    def forward(
        self,
        trip_feat: torch.Tensor,
        driver_feat: torch.Tensor,
        bus_feat: torch.Tensor,
    ) -> torch.Tensor:
        """Return score in range [0, 100]."""
        d = self.driver_net(driver_feat)
        b = self.bus_net(bus_feat)
        fused = torch.cat([d, b, trip_feat], dim=-1)
        return self.fusion(fused) * 100.0

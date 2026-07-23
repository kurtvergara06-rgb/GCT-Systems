import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    initializeAnalyticsTabs();
    initializeForecastPeriod();
    initializeCharts();
});


/* =========================================================
   ANALYTICS TYPE TABS
   Static frontend only for now.
========================================================= */

function initializeAnalyticsTabs() {
    const tabs = document.querySelectorAll('[data-analytics-tab]');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((item) => {
                item.classList.remove('active');
            });

            tab.classList.add('active');

            /*
             * Static frontend for now.
             *
             * Later we can use:
             * - AJAX
             * - Laravel endpoint
             * - dynamic content replacement
             *
             * depending on:
             * descriptive
             * diagnostic
             * predictive
             * prescriptive
             */
        });
    });
}


/* =========================================================
   DAILY / WEEKLY / MONTHLY
========================================================= */

function initializeForecastPeriod() {
    const buttons = document.querySelectorAll(
        '[data-period]'
    );

    const predictedDistance = document.getElementById(
        'predictedDistance'
    );

    const averageDistance = document.getElementById(
        'averageDistance'
    );

    const staticValues = {
        daily: {
            distance: '1,245 KM',
            average: '78 KM'
        },

        weekly: {
            distance: '8,720 KM',
            average: '545 KM'
        },

        monthly: {
            distance: '35,480 KM',
            average: '2,218 KM'
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const period = button.dataset.period;

            buttons.forEach((item) => {
                item.classList.remove('active');
            });

            button.classList.add('active');

            if (!staticValues[period]) {
                return;
            }

            predictedDistance.textContent =
                staticValues[period].distance;

            averageDistance.textContent =
                staticValues[period].average;
        });
    });
}


/* =========================================================
   CHARTS
========================================================= */

function initializeCharts() {
    createFleetMileageChart();
    createVehicleUtilizationChart();
}


/* =========================================================
   FLEET MILEAGE FORECAST
========================================================= */

function createFleetMileageChart() {
    const canvas = document.getElementById(
        'fleetMileageChart'
    );

    if (!canvas) {
        return;
    }

    new Chart(canvas, {
        type: 'line',

        data: {
            labels: [
                'Jun 15',
                'Jun 22',
                'Jun 29',
                'Jul 6',
                'Jul 13',
                'Jul 20',
                'Jul 27',
                'Aug 3'
            ],

            datasets: [
                {
                    label: 'Historical',

                    data: [
                        240,
                        470,
                        360,
                        720,
                        470,
                        690,
                        null,
                        null
                    ],

                    borderWidth: 2.5,

                    tension: 0.35,

                    pointRadius: 0,

                    spanGaps: false
                },

                {
                    label: 'Forecast',

                    data: [
                        null,
                        null,
                        null,
                        null,
                        null,
                        690,
                        760,
                        900
                    ],

                    borderWidth: 2.5,

                    borderDash: [
                        6,
                        5
                    ],

                    tension: 0.35,

                    pointRadius: 0
                }
            ]
        },

        options: {
            responsive: true,

            maintainAspectRatio: false,

            interaction: {
                intersect: false,
                mode: 'index'
            },

            plugins: {
                legend: {
                    display: true,

                    position: 'bottom',

                    labels: {
                        boxWidth: 20,
                        boxHeight: 2,
                        usePointStyle: false,

                        font: {
                            size: 10
                        }
                    }
                }
            },

            scales: {
                x: {
                    grid: {
                        display: false
                    },

                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                },

                y: {
                    beginAtZero: true,

                    suggestedMax: 1000,

                    ticks: {
                        stepSize: 200,

                        font: {
                            size: 9
                        }
                    },

                    grid: {
                        drawBorder: false
                    }
                }
            }
        }
    });
}


/* =========================================================
   VEHICLE UTILIZATION
========================================================= */

function createVehicleUtilizationChart() {
    const canvas = document.getElementById(
        'vehicleUtilizationChart'
    );

    if (!canvas) {
        return;
    }

    new Chart(canvas, {
        type: 'bar',

        data: {
            labels: [
                'ABC-1234',
                'GHI-9012',
                'DEF-5678',
                'MNO-7890',
                'XYZ-3456'
            ],

            datasets: [
                {
                    label: 'Expected Distance',

                    data: [
                        890,
                        810,
                        740,
                        690,
                        620
                    ],

                    borderRadius: 5,

                    borderSkipped: false,

                    barThickness: 13
                }
            ]
        },

        options: {
            indexAxis: 'y',

            responsive: true,

            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    callbacks: {
                        label(context) {
                            return `${context.raw} KM`;
                        }
                    }
                }
            },

            scales: {
                x: {
                    beginAtZero: true,

                    suggestedMax: 1000,

                    ticks: {
                        font: {
                            size: 9
                        },

                        callback(value) {
                            return value.toLocaleString();
                        }
                    },

                    title: {
                        display: true,

                        text: 'Expected distance (KM)',

                        font: {
                            size: 9
                        }
                    }
                },

                y: {
                    grid: {
                        display: false
                    },

                    ticks: {
                        color: '#0f172a',

                        font: {
                            size: 9,
                            weight: '600'
                        }
                    }
                }
            }
        }
    });
}
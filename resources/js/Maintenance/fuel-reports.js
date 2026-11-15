import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {

  const page =
    document.querySelector(
      '.fuel-page'
    );

  if (!page) {
    return;
  }


  /* =========================================================
     REFERENCES
  ========================================================= */

  const gpsUrl =
    page.dataset.gpsUrl;


  const fuelModal =
    document.getElementById(
      'fuelModal'
    );


  const fuelViewModal =
    document.getElementById(
      'fuelViewModal'
    );


  const openFuelModalButton =
    document.getElementById(
      'openFuelModal'
    );


  const closeFuelModalButton =
    document.getElementById(
      'closeFuelModal'
    );


  const cancelFuelModalButton =
    document.getElementById(
      'cancelFuelModal'
    );


  const closeFuelViewModalButton =
    document.getElementById(
      'closeFuelViewModal'
    );


  const closeFuelViewButton =
    document.getElementById(
      'closeFuelViewButton'
    );


  const fuelForm =
    document.getElementById(
      'fuelForm'
    );


  const fuelFormMethod =
    document.getElementById(
      'fuelFormMethod'
    );


  const fuelModalTitle =
    document.getElementById(
      'fuelModalTitle'
    );


  const saveFuelText =
    document.getElementById(
      'saveFuelText'
    );


  const saveFuelRecord =
    document.getElementById(
      'saveFuelRecord'
    );


  const reportDateInput =
    document.getElementById(
      'fuelReportDate'
    );


  const busNoInput =
    document.getElementById(
      'fuelBusNo'
    );


  const driverNameInput =
    document.getElementById(
      'fuelDriverName'
    );


  const fuelLitersInput =
    document.getElementById(
      'fuelLiters'
    );


  const distanceInput =
    document.getElementById(
      'fuelDistanceKm'
    );


  const remarksInput =
    document.getElementById(
      'fuelRemarks'
    );


  const useManualDistanceInput =
    document.getElementById(
      'useManualDistance'
    );


  const manualDistanceFields =
    document.getElementById(
      'manualDistanceFields'
    );


  const manualDistanceReasonInput =
    document.getElementById(
      'manualDistanceReason'
    );


  const gpsStatusCard =
    document.getElementById(
      'gpsStatusCard'
    );


  const gpsStatusTitle =
    document.getElementById(
      'gpsStatusTitle'
    );


  const gpsStatusMessage =
    document.getElementById(
      'gpsStatusMessage'
    );


  const gpsStatusDetails =
    document.getElementById(
      'gpsStatusDetails'
    );


  const gpsDistanceValue =
    document.getElementById(
      'gpsDistanceValue'
    );


  const gpsIdlingValue =
    document.getElementById(
      'gpsIdlingValue'
    );


  const efficiencyValue =
    document.getElementById(
      'efficiencyValue'
    );


  const efficiencyStatus =
    document.getElementById(
      'efficiencyStatus'
    );


  let gpsDistance = 0;
  let gpsRecordFound = false;
  let gpsLookupController = null;

  let efficiencyChart = null;
  let usageChart = null;


  /* =========================================================
     MODALS
  ========================================================= */

  const openModal = modal => {

    if (!modal) {
      return;
    }

    modal.classList.add(
      'show'
    );

    document.body.style.overflow =
      'hidden';

  };


  const closeModal = modal => {

    if (!modal) {
      return;
    }

    modal.classList.remove(
      'show'
    );


    const addModalOpen =
      fuelModal?.classList.contains(
        'show'
      );


    const viewModalOpen =
      fuelViewModal?.classList.contains(
        'show'
      );


    if (
      !addModalOpen &&
      !viewModalOpen
    ) {
      document.body.style.overflow =
        '';
    }

  };


  /* =========================================================
     GPS STATUS
  ========================================================= */

  const setGpsStatus = (
    state,
    title,
    message,
    distance = 0,
    idling = 0
  ) => {

    if (
      !gpsStatusCard ||
      !gpsStatusTitle ||
      !gpsStatusMessage ||
      !gpsStatusDetails ||
      !gpsDistanceValue ||
      !gpsIdlingValue
    ) {
      return;
    }


    gpsStatusCard.classList.remove(
      'idle',
      'loading',
      'success',
      'error',
      'manual'
    );


    gpsStatusCard.classList.add(
      state
    );


    gpsStatusTitle.textContent =
      title;


    gpsStatusMessage.textContent =
      message;


    if (
      state ===
      'success'
    ) {

      gpsStatusDetails.hidden =
        false;


      gpsDistanceValue.textContent =
        `${Number(distance).toFixed(2)} km`;


      gpsIdlingValue.textContent =
        `${Number(idling || 0)} min`;

    } else {

      gpsStatusDetails.hidden =
        true;


      gpsDistanceValue.textContent =
        '0.00 km';


      gpsIdlingValue.textContent =
        '0 min';

    }

  };


  /* =========================================================
     EFFICIENCY
  ========================================================= */

  const getEfficiencyStatus =
    kmPerLiter => {

      if (
        kmPerLiter >= 6
      ) {
        return 'Efficient';
      }


      if (
        kmPerLiter >= 4
      ) {
        return 'Normal';
      }


      if (
        kmPerLiter > 0
      ) {
        return 'Inefficient';
      }


      return 'No Data';

    };


  const updateSaveButtonState =
    () => {

      if (!saveFuelRecord) {
        return;
      }


      const hasBus =
        Boolean(
          busNoInput?.value
        );


      const hasDate =
        Boolean(
          reportDateInput?.value
        );


      const fuelLiters =
        Number.parseFloat(
          fuelLitersInput?.value ||
          '0'
        );


      let distanceValid =
        false;


      if (
        useManualDistanceInput?.checked
      ) {

        const manualDistance =
          Number.parseFloat(
            distanceInput?.value ||
            '0'
          );


        const reason =
          manualDistanceReasonInput
            ?.value
            .trim()
          || '';


        distanceValid =
          manualDistance > 0 &&
          reason.length > 0;

      } else {

        distanceValid =
          gpsRecordFound &&
          gpsDistance > 0;

      }


      saveFuelRecord.disabled =
        !(
          hasBus &&
          hasDate &&
          fuelLiters > 0 &&
          distanceValid
        );

    };


  const updateEfficiencyPreview =
    () => {

      if (
        !efficiencyValue ||
        !efficiencyStatus
      ) {
        return;
      }


      const fuelLiters =
        Number.parseFloat(
          fuelLitersInput?.value ||
          '0'
        );


      let distance = 0;


      if (
        useManualDistanceInput?.checked
      ) {

        distance =
          Number.parseFloat(
            distanceInput?.value ||
            '0'
          );

      } else if (
        gpsRecordFound
      ) {

        distance =
          gpsDistance;

      }


      const kmPerLiter =
        fuelLiters > 0 &&
        distance > 0
          ? distance / fuelLiters
          : 0;


      const status =
        getEfficiencyStatus(
          kmPerLiter
        );


      efficiencyValue.textContent =
        kmPerLiter.toFixed(2);


      efficiencyStatus.textContent =
        status;


      efficiencyStatus.className =
        `badge ${status
          .toLowerCase()
          .replaceAll(
            ' ',
            '-'
          )}`;


      updateSaveButtonState();

    };


  /* =========================================================
     RESET FORM
  ========================================================= */

  const resetFuelForm =
    () => {

      if (!fuelForm) {
        return;
      }


      fuelForm.reset();


      fuelForm.action =
        fuelForm.dataset.storeUrl ||
        '';


      if (fuelFormMethod) {
        fuelFormMethod.value =
          'POST';
      }


      if (fuelModalTitle) {
        fuelModalTitle.textContent =
          'Add Fuel Record';
      }


      if (saveFuelText) {
        saveFuelText.textContent =
          'Save Fuel Record';
      }


      fuelForm.dataset.confirmTitle =
        'Save Fuel Record?';


      fuelForm.dataset.confirmMessage =
        'The system will calculate the fuel efficiency using the selected distance.';


      fuelForm.dataset.confirmButton =
        'Yes, Save Record';


      fuelForm.dataset.confirmType =
        'create';


      const today =
        new Date()
          .toISOString()
          .split('T')[0];


      if (reportDateInput) {
        reportDateInput.value =
          today;
      }


      gpsDistance = 0;

      gpsRecordFound =
        false;


      if (
        useManualDistanceInput
      ) {
        useManualDistanceInput.checked =
          false;
      }


      if (
        manualDistanceFields
      ) {
        manualDistanceFields.hidden =
          true;
      }


      if (
        distanceInput
      ) {
        distanceInput.required =
          false;

        distanceInput.value =
          '';
      }


      if (
        manualDistanceReasonInput
      ) {
        manualDistanceReasonInput.required =
          false;

        manualDistanceReasonInput.value =
          '';
      }


      setGpsStatus(
        'idle',
        'Select a bus and date',
        'The system will search for a processed GPS record.'
      );


      updateEfficiencyPreview();

    };


  /* =========================================================
     GPS LOOKUP
  ========================================================= */

  const lookupGpsDistance =
    async () => {

      if (
        useManualDistanceInput?.checked
      ) {
        return;
      }


      const busNo =
        busNoInput?.value;


      const reportDate =
        reportDateInput?.value;


      gpsDistance = 0;

      gpsRecordFound =
        false;


      if (
        !busNo ||
        !reportDate
      ) {

        setGpsStatus(
          'idle',
          'Select a bus and date',
          'The system will search for a processed GPS record.'
        );


        updateEfficiencyPreview();

        return;

      }


      if (!gpsUrl) {

        setGpsStatus(
          'error',
          'GPS lookup unavailable',
          'The GPS lookup route is not configured.'
        );


        updateEfficiencyPreview();

        return;

      }


      if (
        gpsLookupController
      ) {
        gpsLookupController.abort();
      }


      gpsLookupController =
        new AbortController();


      setGpsStatus(
        'loading',
        'Searching GPS mileage',
        'Please wait while the system searches for a processed GPS record.'
      );


      updateSaveButtonState();


      const params =
        new URLSearchParams({
          bus_no: busNo,
          report_date: reportDate,
        });


      try {

        const response =
          await fetch(
            `${gpsUrl}?${params.toString()}`,
            {
              method: 'GET',

              headers: {
                Accept:
                  'application/json',

                'X-Requested-With':
                  'XMLHttpRequest',
              },

              signal:
                gpsLookupController.signal,
            }
          );


        const data =
          await response.json();


        if (
          !response.ok
        ) {

          throw new Error(
            data.message ||
            'Unable to search for GPS mileage.'
          );

        }


        if (
          !data.found
        ) {

          setGpsStatus(
            'error',
            'No GPS mileage found',
            data.message ||
            'Upload and process a GPS Mileage Report for this bus and date.'
          );


          updateEfficiencyPreview();

          return;

        }


        gpsDistance =
          Number.parseFloat(
            data.distance_km ||
            '0'
          );


        gpsRecordFound =
          gpsDistance > 0;


        const tripPeriod =
          [
            data.beginning_at,
            data.ending_at,
          ]
            .filter(Boolean)
            .join(' to ');


        setGpsStatus(
          'success',
          'GPS mileage found',
          tripPeriod ||
          'A matching processed GPS record was found.',
          gpsDistance,
          data.idling_minutes || 0
        );


        updateEfficiencyPreview();

      } catch (error) {

        if (
          error.name ===
          'AbortError'
        ) {
          return;
        }


        gpsDistance = 0;

        gpsRecordFound =
          false;


        setGpsStatus(
          'error',
          'GPS lookup failed',
          error.message ||
          'Unable to connect to the GPS lookup.'
        );


        updateEfficiencyPreview();

      }

    };


  /* =========================================================
     MANUAL DISTANCE
  ========================================================= */

  const toggleManualDistance =
    () => {

      const isManual =
        useManualDistanceInput
          ?.checked
        ?? false;


      if (
        manualDistanceFields
      ) {
        manualDistanceFields.hidden =
          !isManual;
      }


      if (
        distanceInput
      ) {
        distanceInput.required =
          isManual;
      }


      if (
        manualDistanceReasonInput
      ) {
        manualDistanceReasonInput.required =
          isManual;
      }


      if (
        isManual
      ) {

        setGpsStatus(
          'manual',
          'Manual distance enabled',
          'Enter the distance and explain why GPS data is not being used.'
        );

      } else {

        if (
          distanceInput
        ) {
          distanceInput.value =
            '';
        }


        if (
          manualDistanceReasonInput
        ) {
          manualDistanceReasonInput.value =
            '';
        }


        lookupGpsDistance();

      }


      updateEfficiencyPreview();

    };


  /* =========================================================
     EDIT FORM
  ========================================================= */

  const fillEditForm =
    button => {

      if (!fuelForm) {
        return;
      }


      resetFuelForm();


      if (
        fuelModalTitle
      ) {
        fuelModalTitle.textContent =
          'Edit Fuel Record';
      }


      if (
        saveFuelText
      ) {
        saveFuelText.textContent =
          'Update Fuel Record';
      }


      fuelForm.action =
        button.dataset.updateUrl ||
        '';


      if (
        fuelFormMethod
      ) {
        fuelFormMethod.value =
          'PUT';
      }


      fuelForm.dataset.confirmTitle =
        'Update Fuel Record?';


      fuelForm.dataset.confirmMessage =
        'The fuel efficiency will be recalculated using the updated information.';


      fuelForm.dataset.confirmButton =
        'Yes, Update Record';


      fuelForm.dataset.confirmType =
        'update';


      if (
        reportDateInput
      ) {
        reportDateInput.value =
          button.dataset.reportDate ||
          '';
      }


      if (
        busNoInput
      ) {
        busNoInput.value =
          button.dataset.busNo ||
          '';
      }


      if (
        driverNameInput
      ) {
        driverNameInput.value =
          button.dataset.driverName ||
          '';
      }


      if (
        fuelLitersInput
      ) {
        fuelLitersInput.value =
          button.dataset.fuelLiters ||
          '';
      }


      if (
        remarksInput
      ) {
        remarksInput.value =
          button.dataset.remarks ||
          '';
      }


      const distanceSource =
        button.dataset.distanceSource ||
        'GPS';


      if (
        distanceSource ===
        'Manual'
      ) {

        if (
          useManualDistanceInput
        ) {
          useManualDistanceInput.checked =
            true;
        }


        if (
          manualDistanceFields
        ) {
          manualDistanceFields.hidden =
            false;
        }


        if (
          distanceInput
        ) {
          distanceInput.required =
            true;

          distanceInput.value =
            button.dataset.distanceKm ||
            '';
        }


        if (
          manualDistanceReasonInput
        ) {
          manualDistanceReasonInput.required =
            true;

          manualDistanceReasonInput.value =
            button.dataset.manualDistanceReason ||
            '';
        }


        setGpsStatus(
          'manual',
          'Manual distance selected',
          'This record currently uses manually entered distance.'
        );


        updateEfficiencyPreview();

      } else {

        if (
          useManualDistanceInput
        ) {
          useManualDistanceInput.checked =
            false;
        }


        if (
          manualDistanceFields
        ) {
          manualDistanceFields.hidden =
            true;
        }


        if (
          distanceInput
        ) {
          distanceInput.required =
            false;
        }


        if (
          manualDistanceReasonInput
        ) {
          manualDistanceReasonInput.required =
            false;
        }


        lookupGpsDistance();

      }


      openModal(
        fuelModal
      );

    };


  /* =========================================================
     VIEW
  ========================================================= */

  const fillViewModal =
    button => {

      const setText = (
        id,
        value,
        fallback = '—'
      ) => {

        const element =
          document.getElementById(
            id
          );


        if (!element) {
          return;
        }


        const normalizedValue =
          value !== undefined &&
          value !== null &&
          String(value).trim() !== ''
            ? value
            : fallback;


        element.textContent =
          normalizedValue;

      };


      setText(
        'viewFuelDate',
        button.dataset.reportDate
      );


      setText(
        'viewFuelBus',
        button.dataset.busNo
      );


      setText(
        'viewFuelDriver',
        button.dataset.driverName
      );


      setText(
        'viewFuelDistance',
        button.dataset.distanceKm
          ? `${Number(button.dataset.distanceKm).toFixed(2)} km`
          : '—'
      );


      setText(
        'viewFuelLiters',
        button.dataset.fuelLiters
          ? `${Number(button.dataset.fuelLiters).toFixed(2)} L`
          : '—'
      );


      setText(
        'viewFuelEfficiency',
        button.dataset.kmPerLiter
          ? `${Number(button.dataset.kmPerLiter).toFixed(2)} km/L`
          : '—'
      );


      setText(
        'viewFuelSource',
        button.dataset.distanceSource
      );


      setText(
        'viewFuelStatus',
        button.dataset.status
      );


      setText(
        'viewGpsDate',
        button.dataset.gpsDate
      );


      setText(
        'viewIdlingMinutes',
        button.dataset.idlingMinutes
          ? `${button.dataset.idlingMinutes} min`
          : '—'
      );


      setText(
        'viewManualReason',
        button.dataset.manualDistanceReason
      );


      setText(
        'viewFuelRemarks',
        button.dataset.remarks
      );


      openModal(
        fuelViewModal
      );

    };


  /* =========================================================
     EVENTS
  ========================================================= */

  openFuelModalButton
    ?.addEventListener(
      'click',
      () => {

        resetFuelForm();

        openModal(
          fuelModal
        );

      }
    );


  closeFuelModalButton
    ?.addEventListener(
      'click',
      () => {

        closeModal(
          fuelModal
        );

      }
    );


  cancelFuelModalButton
    ?.addEventListener(
      'click',
      () => {

        closeModal(
          fuelModal
        );

      }
    );


  closeFuelViewModalButton
    ?.addEventListener(
      'click',
      () => {

        closeModal(
          fuelViewModal
        );

      }
    );


  closeFuelViewButton
    ?.addEventListener(
      'click',
      () => {

        closeModal(
          fuelViewModal
        );

      }
    );


  fuelModal
    ?.addEventListener(
      'click',
      event => {

        if (
          event.target ===
          fuelModal
        ) {

          closeModal(
            fuelModal
          );

        }

      }
    );


  fuelViewModal
    ?.addEventListener(
      'click',
      event => {

        if (
          event.target ===
          fuelViewModal
        ) {

          closeModal(
            fuelViewModal
          );

        }

      }
    );


  busNoInput
    ?.addEventListener(
      'change',
      lookupGpsDistance
    );


  reportDateInput
    ?.addEventListener(
      'change',
      lookupGpsDistance
    );


  fuelLitersInput
    ?.addEventListener(
      'input',
      updateEfficiencyPreview
    );


  distanceInput
    ?.addEventListener(
      'input',
      updateEfficiencyPreview
    );


  manualDistanceReasonInput
    ?.addEventListener(
      'input',
      updateSaveButtonState
    );


  useManualDistanceInput
    ?.addEventListener(
      'change',
      toggleManualDistance
    );


  document
    .querySelectorAll(
      '[data-edit-fuel]'
    )
    .forEach(
      button => {

        button.addEventListener(
          'click',
          () => {

            fillEditForm(
              button
            );

          }
        );

      }
    );


  document
    .querySelectorAll(
      '[data-view-fuel]'
    )
    .forEach(
      button => {

        button.addEventListener(
          'click',
          () => {

            fillViewModal(
              button
            );

          }
        );

      }
    );


  document.addEventListener(
    'keydown',
    event => {

      if (
        event.key !==
        'Escape'
      ) {
        return;
      }


      closeModal(
        fuelModal
      );


      closeModal(
        fuelViewModal
      );

    }
  );


  /* =========================================================
     VALIDATION REOPEN
  ========================================================= */

  const validationAlert =
    document.querySelector(
      '.fuel-alert.error'
    );


  const hasOldInput =
    Boolean(
      busNoInput?.value
    )
    ||
    Boolean(
      fuelLitersInput?.value
    )
    ||
    Boolean(
      driverNameInput?.value
    )
    ||
    Boolean(
      distanceInput?.value
    );


  if (
    validationAlert &&
    hasOldInput
  ) {

    if (
      useManualDistanceInput
        ?.checked
    ) {

      toggleManualDistance();

    } else {

      lookupGpsDistance();

    }


    openModal(
      fuelModal
    );

  } else {

    resetFuelForm();

  }



});


/* =========================================================
   FUEL ANALYTICS - TOP 10 READABLE CHARTS
========================================================= */
const parseFuelAnalytics = () => {
  const source = document.getElementById('fuelAnalyticsData');

  if (!source) {
    return null;
  }

  try {
    return JSON.parse(source.textContent.trim());
  } catch (error) {
    console.error('Unable to parse fuel analytics data.', error);
    return null;
  }
};

const fuelChartFont = {
  family: 'Poppins',
  size: 10,
};

const fuelLegendOptions = {
  position: 'bottom',
  labels: {
    usePointStyle: true,
    boxWidth: 8,
    padding: 16,
    color: '#475569',
    font: {
      family: 'Poppins',
      size: 10,
      weight: '600',
    },
  },
};

const getFuelRows = data => {
  const labels = Array.isArray(data.labels) ? data.labels : [];
  const efficiency = Array.isArray(data.efficiency) ? data.efficiency : [];
  const distance = Array.isArray(data.distance) ? data.distance : [];
  const fuel = Array.isArray(data.fuel) ? data.fuel : [];

  return labels.map((label, index) => ({
    label,
    efficiency: Number(efficiency[index] || 0),
    distance: Number(distance[index] || 0),
    fuel: Number(fuel[index] || 0),
  }));
};

const showFuelChartEmptyState = (canvas, message) => {
  const container = canvas?.closest('.fuel-chart-container');

  if (!container || container.querySelector('.fuel-chart-empty-state')) {
    return;
  }

  const state = document.createElement('div');
  state.className = 'fuel-chart-empty-state';
  state.innerHTML = `
    <i class="fa-solid fa-chart-line"></i>
    <strong>No chart data yet</strong>
    <p>${message}</p>
  `;
  container.appendChild(state);
};

const clearFuelChartEmptyState = canvas => {
  canvas?.closest('.fuel-chart-container')
    ?.querySelector('.fuel-chart-empty-state')
    ?.remove();
};

const fleetAverageReferencePlugin = {
  id: 'fuelFleetAverageReference',
  afterDraw(chart, args, options) {
    const average = Number(options?.value || 0);
    const xScale = chart.scales.x;
    const area = chart.chartArea;

    if (!average || !xScale || !area) {
      return;
    }

    const x = xScale.getPixelForValue(average);
    const ctx = chart.ctx;

    ctx.save();
    ctx.strokeStyle = '#d89b00';
    ctx.lineWidth = 2;
    ctx.setLineDash([6, 5]);
    ctx.beginPath();
    ctx.moveTo(x, area.top);
    ctx.lineTo(x, area.bottom);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.restore();
  },
};

const renderEfficiencyChart = data => {
  const canvas = document.getElementById('fuelEfficiencyChart');

  if (!canvas) {
    return;
  }

  Chart.getChart(canvas)?.destroy();
  clearFuelChartEmptyState(canvas);

  const fleetAverage = Number(data.fleetAverage || 0);
  const rows = getFuelRows(data)
    .filter(row => row.efficiency > 0)
    .sort((a, b) => b.efficiency - a.efficiency)
    .slice(0, 10);

  if (!rows.length) {
    showFuelChartEmptyState(canvas, 'Save fuel entries to generate vehicle efficiency rankings.');
    return;
  }

  new Chart(canvas, {
    type: 'bar',
    plugins: [fleetAverageReferencePlugin],
    data: {
      labels: rows.map(row => row.label),
      datasets: [{
        label: 'Efficiency (km/L)',
        data: rows.map(row => row.efficiency),
        backgroundColor: 'rgba(11, 64, 181, 0.82)',
        borderColor: '#0b40b5',
        borderWidth: 1,
        borderRadius: 5,
        barThickness: 14,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 350 },
      interaction: { mode: 'nearest', intersect: false },
      layout: { padding: { right: 18 } },
      plugins: {
        legend: fuelLegendOptions,
        fuelFleetAverageReference: { value: fleetAverage },
        tooltip: {
callbacks: {
  label(context) {
    return `Efficiency: ${Number(context.raw || 0).toFixed(2)} km/L`;
  },
},
        },
      },
      scales: {
        x: {
beginAtZero: true,
suggestedMax: Math.max(7, ...rows.map(row => row.efficiency)) + 0.5,
title: {
  display: true,
  text: 'km/L',
  color: '#64748b',
  font: { ...fuelChartFont, weight: '600' },
},
ticks: { color: '#64748b', font: fuelChartFont },
grid: { color: 'rgba(148, 163, 184, 0.18)' },
        },
        y: {
grid: { display: false },
ticks: {
  color: '#334155',
  font: { family: 'Poppins', size: 10, weight: '600' },
},
        },
      },
    },
  });
};

const renderUsageChart = data => {
  const canvas = document.getElementById('fuelUsageChart');

  if (!canvas) {
    return;
  }

  Chart.getChart(canvas)?.destroy();
  clearFuelChartEmptyState(canvas);

  const rows = getFuelRows(data)
    .filter(row => row.distance > 0 || row.fuel > 0)
    .sort((a, b) => b.distance - a.distance)
    .slice(0, 10);

  if (!rows.length) {
    showFuelChartEmptyState(canvas, 'Save fuel entries to compare distance and fuel usage.');
    return;
  }

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: rows.map(row => row.label),
      datasets: [
        {
type: 'bar',
label: 'Distance (km)',
data: rows.map(row => row.distance),
backgroundColor: 'rgba(11, 64, 181, 0.82)',
borderColor: '#0b40b5',
borderWidth: 1,
borderRadius: 5,
barThickness: 18,
yAxisID: 'distanceAxis',
        },
        {
type: 'line',
label: 'Fuel Used (L)',
data: rows.map(row => row.fuel),
borderColor: '#e2a900',
backgroundColor: '#ffc400',
borderWidth: 2.25,
pointRadius: 3,
pointHoverRadius: 5,
pointBackgroundColor: '#ffc400',
pointBorderColor: '#d89b00',
tension: 0.25,
yAxisID: 'fuelAxis',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 350 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: fuelLegendOptions,
        tooltip: {
callbacks: {
  label(context) {
    const value = Number(context.raw || 0).toFixed(2);
    return context.dataset.label.startsWith('Distance')
      ? `Distance: ${value} km`
      : `Fuel Used: ${value} L`;
  },
},
        },
      },
      scales: {
        x: {
grid: { display: false },
ticks: {
  color: '#475569',
  autoSkip: false,
  maxRotation: 38,
  minRotation: 38,
  font: { family: 'Poppins', size: 9, weight: '600' },
},
        },
        distanceAxis: {
type: 'linear',
position: 'left',
beginAtZero: true,
title: {
  display: true,
  text: 'Distance (km)',
  color: '#64748b',
  font: { ...fuelChartFont, weight: '600' },
},
ticks: { color: '#64748b', font: fuelChartFont },
grid: { color: 'rgba(148, 163, 184, 0.18)' },
        },
        fuelAxis: {
type: 'linear',
position: 'right',
beginAtZero: true,
title: {
  display: true,
  text: 'Fuel Used (L)',
  color: '#64748b',
  font: { ...fuelChartFont, weight: '600' },
},
ticks: { color: '#64748b', font: fuelChartFont },
grid: { drawOnChartArea: false },
        },
      },
    },
  });
};

const enhanceFuelModal = () => {
  const modal = document.querySelector('#fuelModal .fuel-modal');
  const header = modal?.querySelector('.fuel-modal-header');

  if (!modal || !header || header.dataset.enhanced === 'true') {
    return;
  }

  header.dataset.enhanced = 'true';
  modal.classList.add('fuel-modal-job-order-style');

  const heading = header.querySelector(':scope > div');

  if (heading) {
    const icon = document.createElement('div');
    icon.className = 'fuel-modal-title-icon';
    icon.innerHTML = '<i class="fa-solid fa-gas-pump"></i>';
    header.insertBefore(icon, heading);
  }
};

window.addEventListener('load', () => {
  if (!document.querySelector('.fuel-page')) {
    return;
  }

  enhanceFuelModal();

  window.requestAnimationFrame(() => {
    const data = parseFuelAnalytics();

    if (!data) {
      return;
    }

    renderEfficiencyChart(data);
    renderUsageChart(data);
  });
});

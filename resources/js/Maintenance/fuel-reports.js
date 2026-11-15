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
     CHARTS
  ========================================================= */

  const initializeFuelCharts =
    () => {

      const dataElement =
        document.getElementById(
          'fuelAnalyticsData'
        );


      if (
        !dataElement
      ) {
        return;
      }


      let data;


      try {

        data =
          JSON.parse(
            dataElement
              .textContent
              .trim()
          );

      } catch (error) {

        console.error(
          'Invalid fuel analytics JSON.',
          error
        );

        return;

      }


      const labels =
        Array.isArray(
          data.labels
        )
          ? data.labels
          : [];


      const efficiency =
        Array.isArray(
          data.efficiency
        )
          ? data.efficiency
              .map(Number)
          : [];


      const distance =
        Array.isArray(
          data.distance
        )
          ? data.distance
              .map(Number)
          : [];


      const fuel =
        Array.isArray(
          data.fuel
        )
          ? data.fuel
              .map(Number)
          : [];


      const fleetAverage =
        Number(
          data.fleetAverage ||
          0
        );


      const efficiencyCanvas =
        document.getElementById(
          'fuelEfficiencyChart'
        );


      if (
        efficiencyCanvas &&
        labels.length > 0
      ) {

        if (
          efficiencyChart
        ) {
          efficiencyChart.destroy();
        }


        efficiencyChart =
          new Chart(
            efficiencyCanvas,
            {
              type: 'bar',

              data: {
                labels,

                datasets: [
                  {
                    label:
                      'Vehicle Efficiency',

                    data:
                      efficiency,

                    backgroundColor:
                      'rgba(11, 64, 181, 0.72)',

                    borderColor:
                      'rgb(11, 64, 181)',

                    borderWidth:
                      1,

                    borderRadius:
                      8,

                    maxBarThickness:
                      52,
                  },

                  {
                    type:
                      'line',

                    label:
                      'Fleet Average',

                    data:
                      labels.map(
                        () =>
                          fleetAverage
                      ),

                    borderColor:
                      'rgb(255, 196, 0)',

                    backgroundColor:
                      'rgba(255, 196, 0, 0.12)',

                    borderWidth:
                      2,

                    borderDash:
                      [6, 5],

                    pointRadius:
                      0,

                    pointHoverRadius:
                      0,

                    tension:
                      0,
                  },
                ],
              },

              options: {
                responsive:
                  true,

                maintainAspectRatio:
                  false,

                interaction: {
                  mode:
                    'index',

                  intersect:
                    false,
                },

                plugins: {
                  legend: {
                    position:
                      'bottom',
                  },

                  tooltip: {
                    callbacks: {
                      label(context) {

                        return `${context.dataset.label}: ${Number(
                          context.raw
                        ).toFixed(2)} km/L`;

                      },
                    },
                  },
                },

                scales: {
                  x: {
                    grid: {
                      display:
                        false,
                    },
                  },

                  y: {
                    beginAtZero:
                      true,

                    title: {
                      display:
                        true,

                      text:
                        'KM/L',

                      color:
                        '#64748b',
                    },

                    grid: {
                      color:
                        'rgba(148, 163, 184, 0.18)',
                    },
                  },
                },
              },
            }
          );

      }


      const usageCanvas =
        document.getElementById(
          'fuelUsageChart'
        );


      if (
        usageCanvas &&
        labels.length > 0
      ) {

        if (
          usageChart
        ) {
          usageChart.destroy();
        }


        usageChart =
          new Chart(
            usageCanvas,
            {
              type: 'bar',

              data: {
                labels,

                datasets: [
                  {
                    label:
                      'Distance',

                    data:
                      distance,

                    backgroundColor:
                      'rgba(11, 64, 181, 0.72)',

                    borderColor:
                      'rgb(11, 64, 181)',

                    borderWidth:
                      1,

                    borderRadius:
                      8,

                    maxBarThickness:
                      42,

                    yAxisID:
                      'distanceAxis',
                  },

                  {
                    label:
                      'Fuel Used',

                    data:
                      fuel,

                    backgroundColor:
                      'rgba(255, 196, 0, 0.78)',

                    borderColor:
                      'rgb(202, 138, 4)',

                    borderWidth:
                      1,

                    borderRadius:
                      8,

                    maxBarThickness:
                      42,

                    yAxisID:
                      'fuelAxis',
                  },
                ],
              },

              options: {
                responsive:
                  true,

                maintainAspectRatio:
                  false,

                interaction: {
                  mode:
                    'index',

                  intersect:
                    false,
                },

                plugins: {
                  legend: {
                    position:
                      'bottom',
                  },

                  tooltip: {
                    callbacks: {
                      label(context) {

                        const value =
                          Number(
                            context.raw ||
                            0
                          );


                        if (
                          context.dataset.label ===
                          'Distance'
                        ) {

                          return `Distance: ${value.toFixed(2)} km`;

                        }


                        return `Fuel Used: ${value.toFixed(2)} L`;

                      },
                    },
                  },
                },

                scales: {
                  x: {
                    grid: {
                      display:
                        false,
                    },
                  },

                  distanceAxis: {
                    type:
                      'linear',

                    position:
                      'left',

                    beginAtZero:
                      true,

                    title: {
                      display:
                        true,

                      text:
                        'Distance (km)',

                      color:
                        '#64748b',
                    },

                    grid: {
                      color:
                        'rgba(148, 163, 184, 0.18)',
                    },
                  },

                  fuelAxis: {
                    type:
                      'linear',

                    position:
                      'right',

                    beginAtZero:
                      true,

                    title: {
                      display:
                        true,

                      text:
                        'Fuel (L)',

                      color:
                        '#64748b',
                    },

                    grid: {
                      drawOnChartArea:
                        false,
                    },
                  },
                },
              },
            }
          );

      }

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


  initializeFuelCharts();

});